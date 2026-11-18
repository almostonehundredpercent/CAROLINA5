<?php

namespace App\Http\Controllers;

use App\Models\DecisionSupportSeason;
use App\Models\DecisionSupportSetting;
use App\Models\Booking;
use App\Models\PromoCode;
use App\Models\Room;
use App\Models\RoomBlock;
use App\Services\OperationalDecisionSupport;
use App\Support\PropertyTime;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class DecisionSupportController extends Controller
{
    public function index(OperationalDecisionSupport $decisionSupport)
    {
        $setting = DecisionSupportSetting::find(1);
        $threshold = $setting?->low_occupancy_threshold ?? 40;
        $seasons = DecisionSupportSeason::whereDate('ends_on', '>=', today())
            ->orderBy('starts_on')->get();
        $rooms = Room::where('is_active', true)->orderBy('id')->get();
        $opportunities = $decisionSupport->promoOpportunities($rooms, $threshold, $seasons);
        $decisionSupport->generatePromoDrafts($opportunities, $seasons, $threshold);
        $promoDrafts = PromoCode::with('rooms')->whereNotNull('carol_generation_key')
            ->where('review_status', 'pending')->latest()->get();

        return view('admin.decision-support', [
            'threshold' => $threshold,
            'seasons' => $seasons,
            'opportunities' => $opportunities,
            'pendingPromoCount' => $promoDrafts->count(),
            'promoDrafts' => $promoDrafts,
            'promoOutcomes' => $decisionSupport->promoOutcomes(),
        ]);
    }

    public function updateSettings(Request $request)
    {
        $data = $request->validate([
            'low_occupancy_threshold' => ['required', 'integer', 'min:5', 'max:95'],
        ]);

        DecisionSupportSetting::updateOrCreate(['id' => 1], $data);

        return back()->with('success', 'Low-occupancy rule saved. Recommendations now use the updated threshold.');
    }

    public function resolvePaymentReminderRecommendation(
        Request $request,
        OperationalDecisionSupport $decisionSupport,
        string $decision,
    ) {
        abort_unless(in_array($decision, ['apply', 'dismiss', 'disable'], true), 404);

        if ($decision === 'apply') {
            $hasActiveRecommendation = $decisionSupport
                ->dashboardSignals(collect())
                ->contains(fn (array $signal) => ($signal['kind'] ?? null) === 'unpaid_hold_payment_reminder');
            abort_unless($hasActiveRecommendation, 409, 'This recommendation is no longer active.');
        }

        $setting = DecisionSupportSetting::findOrNew(1);
        $setting->low_occupancy_threshold ??= 40;

        if ($decision === 'apply') {
            $setting->unpaid_hold_reminders_enabled = true;
            $setting->unpaid_hold_reminder_dismissed_at = null;
            $message = 'Payment reminders are on. One email will be sent about five minutes before an unpaid hold expires.';
        } elseif ($decision === 'dismiss') {
            $setting->unpaid_hold_reminder_dismissed_at = now();
            $message = 'CAROL’s recommendation was dismissed.';
        } else {
            $setting->unpaid_hold_reminders_enabled = false;
            $setting->unpaid_hold_reminder_dismissed_at = now();
            $message = 'Payment reminders are off.';
        }

        $setting->save();

        if ($request->expectsJson()) {
            return response()->json(['message' => $message, 'completed' => true]);
        }

        return back()->with('success', $message);
    }

    public function confirmSignal(Request $request, OperationalDecisionSupport $decisionSupport, string $kind)
    {
        abort_unless(in_array($kind, [
            'elevated_cancellations',
            'no_rooms_available',
            'repeated_maintenance',
            'high_booking_demand',
        ], true), 404);

        $data = $request->validate([
            'signature' => ['required', 'regex:/^[a-f0-9]{64}$/'],
            'room_id' => ['nullable', 'integer', 'exists:rooms,id'],
        ]);
        $roomId = (int) ($data['room_id'] ?? 0);
        $dismissedSignatures = (array) $request->session()->get('carol.dismissed_signatures', []);
        if (in_array($kind, ['repeated_maintenance', 'high_booking_demand'], true)) {
            abort_unless($roomId > 0 && Room::whereKey($roomId)->where('is_active', true)->exists(), 422, 'This room recommendation is no longer available.');
            $current = $decisionSupport->dashboardSignals(collect(), $dismissedSignatures)->first(
                fn (array $signal) => ($signal['kind'] ?? null) === $kind
                    && (int) ($signal['room_id'] ?? 0) === $roomId
                    && ($signal['signature'] ?? null) === $data['signature']
            );
            abort_unless($current, 409, 'This CAROL recommendation is no longer active.');
        } elseif ($kind === 'elevated_cancellations') {
            $current = $decisionSupport->dashboardSignals(collect(), $dismissedSignatures)->first(
                fn (array $signal) => ($signal['kind'] ?? null) === $kind && ($signal['signature'] ?? null) === $data['signature']
            );
            abort_unless($current, 409, 'This CAROL recommendation is no longer active.');
        }
        $rows = match ($kind) {
            'elevated_cancellations' => Booking::with(['room', 'user'])
                ->where('status', 'cancelled')->where('created_at', '>=', now()->subDays(90))
                ->latest('created_at')->take(8)->get()
                ->map(fn (Booking $booking) => [
                    'label' => trim(($booking->room?->name ?? 'Room').' · '.($booking->reference ?? 'Booking')),
                    'detail' => ($booking->guest_name ?? $booking->user?->name ?? 'Guest').' · '.($booking->cancelled_at?->format('M j, g:i A') ?? $booking->created_at?->format('M j, g:i A') ?? 'Date unavailable'),
                ])->values(),
            'no_rooms_available' => Booking::with(['room', 'user'])
                ->where('status', 'confirmed')->whereNull('checked_in_at')->whereDate('check_in', PropertyTime::today())
                ->orderBy('check_in_at')->take(8)->get()
                ->map(fn (Booking $booking) => [
                    'label' => trim(($booking->room?->name ?? 'Room').' · '.($booking->reference ?? 'Booking')),
                    'detail' => ($booking->guest_name ?? $booking->user?->name ?? 'Guest').' · '.($booking->check_in_at?->format('g:i A') ?? 'Arrival today'),
                ])->values(),
            'repeated_maintenance' => RoomBlock::with('room')
                ->where('room_id', $roomId)->where('status', 'maintenance')->where('created_at', '>=', now()->subDays(30))
                ->latest('created_at')->take(8)->get()
                ->map(fn (RoomBlock $block) => [
                    'label' => $block->room?->name ?? 'Room maintenance',
                    'detail' => $block->starts_at?->format('M j, g:i A').' · '.($block->notes ?: 'Maintenance block recorded'),
                ])->values(),
            'high_booking_demand' => Booking::with(['room', 'user'])
                ->where('room_id', $roomId)->where('status', 'confirmed')->where('check_in_at', '>=', PropertyTime::now())
                ->orderBy('check_in_at')->take(8)->get()
                ->map(fn (Booking $booking) => [
                    'label' => trim(($booking->room?->name ?? 'Room').' · '.($booking->reference ?? 'Booking')),
                    'detail' => ($booking->guest_name ?? $booking->user?->name ?? 'Guest').' · '.($booking->check_in_at?->format('M j, g:i A') ?? 'Upcoming stay'),
                ])->values(),
        };

        if ($kind === 'high_booking_demand' && $rows->isEmpty()) {
            $rows = Booking::with(['room', 'user'])
                ->where('room_id', $roomId)->where('status', 'confirmed')
                ->latest('check_in_at')->take(8)->get()
                ->map(fn (Booking $booking) => [
                    'label' => trim(($booking->room?->name ?? 'Room').' · '.($booking->reference ?? 'Booking')),
                    'detail' => ($booking->guest_name ?? $booking->user?->name ?? 'Guest').' · '.($booking->check_in_at?->format('M j, g:i A') ?? 'Past stay'),
                ])->values();
        }

        $this->rememberSignalDismissal($request, $data['signature']);
        $message = $rows->isEmpty() ? 'No matching records remain for this suggestion.' : 'CAROL loaded the matching records here.';

        if (! $request->expectsJson()) {
            return back()->with('success', $message);
        }

        return response()->json(['message' => $message, 'rows' => $rows, 'completed' => true]);
    }

    public function dismissSignal(Request $request, string $kind)
    {
        abort_unless(in_array($kind, [
            'elevated_cancellations',
            'no_rooms_available',
            'repeated_maintenance',
            'high_booking_demand',
        ], true), 404);

        $data = $request->validate([
            'signature' => ['required', 'regex:/^[a-f0-9]{64}$/'],
            'room_id' => ['nullable', 'integer', 'exists:rooms,id'],
        ]);
        $this->rememberSignalDismissal($request, $data['signature']);
        $message = 'CAROL dismissed this recommendation until its data changes.';

        if ($request->expectsJson()) {
            return response()->json(['message' => $message, 'rows' => [], 'completed' => true]);
        }

        return back()->with('success', $message);
    }

    private function rememberSignalDismissal(Request $request, string $signature): void
    {
        $dismissed = (array) $request->session()->get('carol.dismissed_signatures', []);
        $dismissed[] = $signature;
        $request->session()->put('carol.dismissed_signatures', array_slice(array_values(array_unique($dismissed)), -100));
    }

    public function reviewPromoDraft(Request $request, PromoCode $promo, string $decision)
    {
        abort_unless(in_array($decision, ['approve', 'dismiss'], true), 404);
        abort_unless($promo->carol_generation_key, 404);

        DB::transaction(function () use ($promo, $decision): void {
            $lockedPromo = PromoCode::whereKey($promo->id)->lockForUpdate()->firstOrFail();
            abort_unless($lockedPromo->carol_generation_key, 404);
            abort_unless($lockedPromo->review_status === 'pending', 422, 'This CAROL draft has already been reviewed.');

            if ($decision === 'approve') {
                abort_unless($lockedPromo->rooms()->exists(), 422, 'This draft has no eligible rooms and cannot be activated.');
                abort_if($lockedPromo->ends_at && $lockedPromo->ends_at->lte(now()), 422, 'This CAROL promo draft has expired and cannot be activated.');
                $lockedPromo->update(['review_status' => 'approved', 'is_active' => true]);
            } else {
                $lockedPromo->update(['review_status' => 'dismissed', 'is_active' => false]);
            }
        });

        $message = $decision === 'approve'
            ? 'CAROL promo '.$promo->code.' is now active.'
            : 'CAROL promo '.$promo->code.' was dismissed.';

        if ($request->expectsJson()) {
            return response()->json(['message' => $message, 'completed' => true]);
        }

        return back()->with('success', $message);
    }

    public function storeSeason(Request $request)
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'kind' => ['required', 'in:holiday,season'],
            'starts_on' => ['required', 'date_format:Y-m-d', 'after_or_equal:today'],
            'ends_on' => ['required', 'date_format:Y-m-d', 'after_or_equal:starts_on'],
        ]);
        $data['created_by'] = $request->user()->id;

        DecisionSupportSeason::create($data);

        return back()->with('success', ucfirst($data['kind']).' dates added. Matching low-occupancy windows can now generate CAROL promo drafts.');
    }

    public function destroySeason(DecisionSupportSeason $season)
    {
        $season->delete();

        return back()->with('success', 'Seasonal window removed.');
    }
}
