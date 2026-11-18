<?php

namespace App\Http\Controllers;

use App\Models\DecisionSupportSeason;
use App\Models\DecisionSupportSetting;
use App\Models\PromoCode;
use App\Models\Room;
use App\Services\OperationalDecisionSupport;
use Illuminate\Http\Request;

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

        return view('admin.decision-support', [
            'threshold' => $threshold,
            'seasons' => $seasons,
            'opportunities' => $opportunities,
            'pendingPromoCount' => PromoCode::where('review_status', 'pending')->count(),
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
