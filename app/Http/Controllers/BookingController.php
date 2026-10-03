<?php

namespace App\Http\Controllers;

use App\Mail\BookingConfirmation;
use App\Mail\BookingUpdate;
use App\Mail\NewBookingRequest;
use App\Models\ActivityLog;
use App\Models\Booking;
use App\Models\GuestRestriction;
use App\Models\Payment;
use App\Models\Review;
use App\Models\Room;
use App\Models\RoomBlock;
use App\Services\PayMongoCheckout;
use App\Support\BookingSelection;
use App\Support\PromoPricing;
use Carbon\Carbon;
use Illuminate\Database\QueryException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class BookingController extends Controller
{
    public function create(Request $request, Room $room)
    {
        if (! $room->is_active) {
            abort(404);
        }
        $blockedRanges = $this->blockedRanges($room);

        $key = 'booking-submission:'.$room->id;
        $submissionToken = $request->session()->get($key) ?: (string) Str::uuid();
        $request->session()->put($key, $submissionToken);
        $bookingSearch = BookingSelection::query($request);
        $propertyToday = Carbon::now('Asia/Manila')->toDateString();
        $hourlyDate = old('hourly_date', $bookingSearch['check_in'] ?? $propertyToday);
        $selectedHours = BookingSelection::hours($bookingSearch, $room);
        $allowedHours = $room->rental_hours ? array_unique([$room->rental_hours, 48, 72, 96, 120, 168, 720]) : [3, 6, 12, 24, 48, 72, 96, 120, 168, 720];
        $selectionNotice = in_array($selectedHours, $allowedHours, true) ? null : 'The searched duration is not offered for this room. Choose one of its available stay packages below.';
        $selectedHours = in_array($selectedHours, $allowedHours, true) ? $selectedHours : ($room->rental_hours ?: 3);
        $hourlyBlockedSlots = $this->hourlyBlockedSlots($room);

        return view('bookings.create', [
            'room' => $room,
            'isGuest' => $request->boolean('guest'),
            'bookingMode' => $room->rental_hours ? 'hourly' : ($bookingSearch['mode'] ?? (isset($bookingSearch['stay']) ? 'hourly' : 'dates')),
            'bookingSearch' => $bookingSearch,
            'propertyToday' => $propertyToday,
            'selectedHours' => $selectedHours,
            'allowedHours' => $allowedHours,
            'selectedTime' => $bookingSearch['check_in_time'] ?? ($room->default_check_in_time ? substr($room->default_check_in_time, 0, 5) : '12:00'),
            'selectedGuests' => isset($bookingSearch['guests']) && (int) $bookingSearch['guests'] <= $room->guests ? (int) $bookingSearch['guests'] : 1,
            'selectionNotice' => $selectionNotice,
            'blockedRanges' => $blockedRanges,
            'hourlyBlockedSlots' => $hourlyBlockedSlots,
            'hourlyDate' => $hourlyDate,
            'hourlyBookedWindows' => $this->bookedWindowsForDate($hourlyBlockedSlots, $hourlyDate),
            'submissionToken' => $submissionToken,
            'roomPromos' => $room->promoCodes()->where('promo_codes.is_active', true)
                ->where(fn ($query) => $query->whereNull('starts_at')->orWhere('starts_at', '<=', now()))
                ->where(fn ($query) => $query->whereNull('ends_at')->orWhere('ends_at', '>=', now()))
                ->where(fn ($query) => $query->whereNull('usage_limit')->orWhereColumn('times_used', '<', 'usage_limit'))
                ->get(),
        ]);
    }

    public function availability(Request $request, Room $room)
    {
        if ($request->filled('start')) {
            $data = $request->validate(['start' => 'required|date', 'end' => 'required|date|after:start']);
            $start = Carbon::parse($data['start']);
            $end = Carbon::parse($data['end']);
            $available = $room->is_active && ! $room->bookings()->blocking()->overlapping($start, $end)->exists()
                && ! $room->blocks()->overlapping($start, $end)->exists();

            return response()->json(['available' => $available])->header('Cache-Control', 'no-store');
        }

        return response()->json(['ranges' => $this->blockedRanges($room)->values(), 'slots' => $this->hourlyBlockedSlots($room)->values()]);
    }

    public function promoQuote(Request $request, Room $room)
    {
        $data = $request->validate([
            'promo_code' => ['required', 'string', 'max:40'],
            'booking_type' => ['required', 'in:dates,hourly'],
            'hours' => ['nullable', 'integer', 'min:1', 'max:8760'],
            'check_in' => ['nullable', 'date'],
            'check_out' => ['nullable', 'date', 'after:check_in'],
        ]);

        if ($data['booking_type'] === 'hourly') {
            $hours = (int) ($data['hours'] ?? 0);
            abort_if($hours < 1, 422, 'Choose a stay duration first.');
            $original = $room->rental_hours
                ? round($room->price_per_night * match ($hours) {
                    48 => 2, 72 => 3, 96 => 4, 120 => 5, 168 => 7, 720 => 30, default => 1
                }, 2)
                : round(($room->price_per_night / 24) * $hours, 2);
        } else {
            abort_if(empty($data['check_in']) || empty($data['check_out']), 422, 'Choose check-in and check-out dates first.');
            $nights = Carbon::parse($data['check_in'])->diffInDays(Carbon::parse($data['check_out']));
            $hours = $nights * 24;
            $original = $nights * $room->price_per_night;
        }

        $quote = PromoPricing::quote($data['promo_code'], $room, $hours, $original);

        return response()->json([
            'code' => $quote['promo']->code,
            'name' => $quote['promo']->name,
            'original' => $quote['original'],
            'discount' => $quote['discount'],
            'total' => $quote['total'],
            'message' => $quote['promo']->name.' applied. You save ₱'.number_format($quote['discount'], 2).'.',
        ])->header('Cache-Control', 'no-store');
    }

    private function blockedRanges(Room $room)
    {
        $ranges = $room->bookings()
            ->blocking()
            ->whereDate('check_out', '>=', Carbon::now('Asia/Manila')->toDateString())
            ->orderBy('check_in')
            ->get(['check_in', 'check_out'])
            ->map(fn (Booking $booking) => [
                'start' => $booking->check_in->toDateString(),
                'end' => $booking->check_out->toDateString(),
            ]);
        $room->blocks()->where('ends_at', '>', now())->get()->each(fn (RoomBlock $block) => $ranges->push(['start' => $block->starts_at->toDateString(), 'end' => $block->ends_at->copy()->addDay()->toDateString()]));

        return $ranges;

    }

    private function hourlyBlockedSlots(Room $room)
    {
        $slots = $room->bookings()
            ->blocking()
            ->whereDate('check_out', '>=', Carbon::now('Asia/Manila')->toDateString())
            ->orderBy('check_in')
            ->get()
            ->map(function (Booking $booking) {
                $start = $booking->check_in_at ?? $booking->check_in->copy()->startOfDay();
                $end = $booking->check_out_at ?? $booking->check_out->copy()->startOfDay();

                return ['start' => $start->toIso8601String(), 'end' => $end->toIso8601String(), 'booking_type' => $booking->booking_type, 'nights' => $booking->nights];
            });
        $room->blocks()->where('ends_at', '>', now())->get()->each(fn (RoomBlock $block) => $slots->push(['start' => $block->starts_at->toIso8601String(), 'end' => $block->ends_at->toIso8601String()]));

        return $slots;
    }

    private function bookedWindowsForDate($slots, string $date): array
    {
        // Datetimes are persisted as UTC-formatted values representing the
        // property's local wall-clock time. Keep the picker on that convention.
        $timezone = 'UTC';
        $dayStart = Carbon::parse($date, $timezone)->startOfDay();
        $dayEnd = $dayStart->copy()->addDay();

        return collect($slots)->map(function (array $slot) use ($dayStart, $dayEnd, $timezone) {
            $start = Carbon::parse($slot['start']);
            $end = Carbon::parse($slot['end']);
            $windowStart = max($start->timestamp, $dayStart->timestamp);
            $windowEnd = min($end->timestamp, $dayEnd->timestamp);

            if ($windowStart >= $windowEnd) {
                return null;
            }

            if ($windowStart === $dayStart->timestamp && $windowEnd === $dayEnd->timestamp) {
                return 'All day';
            }

            $format = function (int $timestamp) use ($timezone): string {
                $time = Carbon::createFromTimestamp($timestamp, $timezone);

                return $time->format('i') === '00' ? $time->format('g A') : $time->format('g:i A');
            };

            return $format($windowStart).' – '.$format($windowEnd);
        })->filter()->unique()->values()->all();
    }

    public function store(Request $request, Room $room)
    {
        if (! $room->is_active) {
            abort(404);
        }
        if ($request->filled('guest_email')) {
            $request->merge(['guest_email' => strtolower(trim((string) $request->input('guest_email')))]);
        }
        if ($request->filled('guest_phone')) {
            $request->merge(['guest_phone' => preg_replace('/[\s()\-]/', '', (string) $request->input('guest_phone'))]);
        }
        if ($request->filled('promo_code')) {
            $request->merge(['promo_code' => Str::upper(trim((string) $request->input('promo_code')))]);
        }
        $rules = ['booking_type' => 'required|in:dates,hourly', 'guests' => 'required|integer|min:1|max:'.$room->guests, 'children_count' => 'nullable|integer|min:0|max:10|lte:guests', 'pets_count' => 'nullable|integer|min:0|max:5', 'special_request' => 'nullable|string|max:500', 'promo_code' => 'nullable|string|max:40', 'checkout_type' => 'required|in:guest,account', 'submission_token' => 'required|uuid', 'terms_accepted' => 'accepted'];
        if ($request->input('booking_type') === 'hourly') {
            $rules += ['hourly_date' => 'required|date|after_or_equal:'.Carbon::now('Asia/Manila')->toDateString(), 'check_in_time' => ['required', 'date_format:H:i', 'regex:/^(0[6-9]|1[0-9]|2[0-3]):00$/'], 'hours' => 'required|integer|in:'.($room->rental_hours ? implode(',', array_unique([$room->rental_hours, 48, 72, 96, 120, 168, 720])) : '3,6,12,24,48,72,96,120,168,720')];
        } else {
            $rules += ['check_in' => 'required|date|after_or_equal:'.Carbon::now('Asia/Manila')->toDateString(), 'check_out' => 'required|date|after:check_in'];
        }
        if ($request->input('checkout_type') === 'guest') {
            $rules += ['guest_name' => 'required|string|max:255', 'guest_email' => 'required|email:rfc|max:255', 'guest_phone' => ['required', 'regex:/^(?:\\+63|63|0)9\\d{9}$/'], 'terms_accepted' => 'accepted'];
        }
        $data = $request->validate($rules);
        $restrictionEmail = $data['checkout_type'] === 'guest' ? ($data['guest_email'] ?? null) : $request->user()?->email;
        $restrictionPhone = $data['checkout_type'] === 'guest' ? ($data['guest_phone'] ?? null) : null;
        $guestKey = $restrictionEmail ? 'email:'.strtolower(trim($restrictionEmail)) : 'phone:'.preg_replace('/\D+/', '', (string) $restrictionPhone);
        abort_if(GuestRestriction::where('guest_key', $guestKey)->whereNull('removed_at')->exists(), 422, 'This reservation cannot be accepted online. Please contact Carolina directly.');
        $existing = Booking::where('submission_token', $data['submission_token'])->first();
        if ($existing) {
            return redirect()->route('bookings.receipt', $existing)->with('success', 'Your reservation request was already received.');
        }
        if ($data['checkout_type'] === 'account' && ! $request->user()) {
            return redirect()->route('login')->with('success', 'Please sign in to use account checkout.');
        }
        if ($data['booking_type'] === 'hourly') {
            $checkInAt = Carbon::parse($data['hourly_date'].' '.$data['check_in_time']);
            $checkOutAt = $checkInAt->copy()->addHours((int) $data['hours']);
            $nights = max(1, (int) ceil($data['hours'] / 24));
            $total = $room->rental_hours
                ? round($room->price_per_night * match ((int) $data['hours']) {
                    48 => 2, 72 => 3, 96 => 4, 120 => 5, 168 => 7, 720 => 30, default => 1
                }, 2)
                : round(($room->price_per_night / 24) * $data['hours'], 2);
            $data['check_in'] = $checkInAt->toDateString();
            $data['check_out'] = $checkOutAt->toDateString();
            $data['check_in_at'] = $checkInAt;
            $data['check_out_at'] = $checkOutAt;
        } else {
            $checkInAt = Carbon::parse($data['check_in'])->startOfDay();
            $checkOutAt = Carbon::parse($data['check_out'])->startOfDay();
            $nights = $checkInAt->diffInDays($checkOutAt);
            $total = $nights * $room->price_per_night;
            $data['check_in_at'] = $checkInAt;
            $data['check_out_at'] = $checkOutAt;
        }
        $guestData = $data['checkout_type'] === 'guest' ? ['guest_name' => $data['guest_name'], 'guest_email' => $data['guest_email'], 'guest_phone' => $data['guest_phone']] : [];
        try {
            $booking = DB::transaction(function () use ($room, $data, $guestData, $request, $nights, $total, $checkInAt, $checkOutAt) {
                $lockedRoom = Room::whereKey($room->id)->lockForUpdate()->firstOrFail();
                abort_if(! $lockedRoom->is_active, 422, 'This room is unavailable.');
                $taken = $lockedRoom->bookings()->blocking()->overlapping($checkInAt, $checkOutAt)->exists()
                    || $lockedRoom->blocks()->overlapping($checkInAt, $checkOutAt)->exists();
                if ($taken) {
                    throw ValidationException::withMessages(['availability' => 'Those dates or hours are reserved. Please choose another time.']);
                }

                $hours = $data['booking_type'] === 'hourly' ? (int) $data['hours'] : $nights * 24;
                $pricing = ! empty($data['promo_code']) ? PromoPricing::quote($data['promo_code'], $lockedRoom, $hours, $total, true) : null;
                $booking = Booking::create($data + $guestData + [
                    'user_id' => $request->user()?->id,
                    'room_id' => $lockedRoom->id,
                    'promo_code_id' => $pricing ? $pricing['promo']->id : null,
                    'promo_code' => $pricing ? $pricing['promo']->code : null,
                    'nights' => $nights,
                    'total_amount' => $pricing ? $pricing['total'] : $total,
                    'original_amount' => $pricing ? $pricing['original'] : null,
                    'discount_amount' => $pricing ? $pricing['discount'] : 0,
                    'add_ons' => [],
                    'hold_expires_at' => now()->addMinutes(Booking::holdMinutes()),
                    'payment_method' => 'cash',
                    'status' => 'pending',
                ]);
                if ($pricing) {
                    $pricing['promo']->increment('times_used');
                }

                return $booking;
            });
        } catch (QueryException $exception) {
            $booking = Booking::where('submission_token', $data['submission_token'])->first();
            if (! $booking) {
                throw $exception;
            }

            return redirect()->route('bookings.receipt', $booking)->with('success', 'Your reservation request was already received.');
        }
        $request->session()->forget('booking-submission:'.$room->id);
        try {
            $this->log($booking, $request->user()?->id, 'booking_created', 'Booking created and awaiting staff confirmation.');
        } catch (\Throwable $exception) {
            report($exception);
        }
        $request->session()->put('guest_booking_reference', $booking->reference);
        $email = $booking->guest_email ?? $request->user()?->email;
        if ($email) {
            try {
                Mail::to($email)->queue((new BookingConfirmation($booking))->onConnection('database')->onQueue('mail'));
            } catch (\Throwable $exception) {
                report($exception);
            }
        }
        $staffEmail = config('mail.notifications.address');
        if (filter_var($staffEmail, FILTER_VALIDATE_EMAIL) && strcasecmp((string) $staffEmail, (string) $email) !== 0) {
            try {
                Mail::to($staffEmail)->queue((new NewBookingRequest($booking))->onConnection('database')->onQueue('mail'));
            } catch (\Throwable $exception) {
                report($exception);
            }
        }

        return redirect()->route('bookings.receipt', $booking)->with('success', 'Reservation request recorded. Carolina will confirm availability before your stay is final.');
    }

    public function index(Request $request)
    {
        return view('bookings.index', ['bookings' => $request->user()->bookings()->with(['room', 'review'])->latest()->get()]);
    }

    public function submitReview(Request $request, Booking $booking)
    {
        abort_unless($booking->user_id === $request->user()?->id, 403);
        abort_if($booking->status === 'cancelled' || ! $booking->checked_out_at, 422, 'Reviews are available after check-out.');
        abort_if($booking->review()->exists(), 422, 'You have already reviewed this stay.');

        $data = $request->validate([
            'rating' => 'required|integer|between:1,5',
            'cleanliness_rating' => 'nullable|integer|between:1,5',
            'comfort_rating' => 'nullable|integer|between:1,5',
            'value_rating' => 'nullable|integer|between:1,5',
            'comment' => 'nullable|string|max:750',
        ]);

        Review::create($data + [
            'booking_id' => $booking->id,
            'room_id' => $booking->room_id,
            'user_id' => $request->user()->id,
            'guest_name' => $request->user()->name,
            'status' => 'pending',
        ]);
        $this->log($booking, $request->user()->id, 'review_submitted', 'Guest submitted a review for staff approval.');

        return back()->with('success', 'Thank you. Your review was submitted for approval.');
    }

    public function receipt(Request $request, Booking $booking)
    {
        $this->authorizeBookingAccess($request, $booking);

        return view('bookings.receipt', compact('booking'));
    }

    public function confirmation(Request $request, Booking $booking)
    {
        $isOwner = $booking->user_id && $request->user() && $booking->user_id === $request->user()->id;
        $isGuestSession = $booking->user_id === null && $request->session()->get('guest_booking_reference') === $booking->reference;
        abort_unless($isOwner || $isGuestSession || $request->user()?->is_admin, 403);

        return view('bookings.confirmation', compact('booking'));
    }

    public function startPayMongoCheckout(Request $request, Booking $booking, PayMongoCheckout $checkout)
    {
        $this->authorizeBookingAccess($request, $booking);
        abort_if($booking->hasExpiredHold(), 422, 'This reservation hold expired. Please choose an available stay or contact Carolina before paying.');
        abort_if($booking->status === 'cancelled', 422, 'Cancelled reservations cannot be paid online.');
        abort_if($booking->payment_status === 'paid', 422, 'This reservation has already been paid.');

        $attemptId = (string) Str::uuid();

        try {
            $session = $checkout->create($booking->loadMissing(['room', 'user']), $attemptId);
        } catch (\Throwable $exception) {
            Log::warning('PayMongo checkout could not be started.', [
                'attempt_id' => $attemptId,
                'booking_id' => $booking->id,
                'exception' => $exception::class,
            ]);

            if ($request->expectsJson()) {
                return response()->json(['message' => 'Online GCash checkout is unavailable right now. Please try again shortly.'], 503);
            }

            return back()->withErrors(['payment' => 'Online GCash checkout is unavailable right now. Please try again later.']);
        }

        Payment::create([
            'booking_id' => $booking->id,
            'amount' => $booking->total_amount,
            'method' => 'gcash',
            'status' => 'pending',
            'reference' => 'paymongo:'.$session['id'],
            'notes' => 'PayMongo test checkout session.',
        ]);
        $booking->update(['payment_method' => 'gcash', 'payment_status' => 'pending']);
        $this->log($booking, $request->user()?->id, 'payment_checkout_started', 'Guest started a PayMongo test GCash checkout.');

        if ($request->expectsJson()) {
            return response()->json(['checkout_url' => $session['url']]);
        }

        return redirect()->away($session['url']);
    }

    public function returnFromPayMongo(Request $request, Booking $booking, PayMongoCheckout $checkout)
    {
        $this->authorizeBookingAccess($request, $booking);
        $pendingPayment = $booking->payments()
            ->where('method', 'gcash')
            ->where('status', 'pending')
            ->where('reference', 'like', 'paymongo:%')
            ->latest('id')
            ->first();
        if (! $pendingPayment) {
            return redirect()->route('bookings.receipt', $booking)->withErrors(['payment' => 'No pending online payment was found for this reservation.']);
        }
        if ($request->query('outcome') === 'cancelled') {
            return redirect()->route('bookings.receipt', $booking)->with('success', 'The GCash test checkout was cancelled. Your reservation remains pending.');
        }

        try {
            $session = $checkout->retrieve(substr($pendingPayment->reference, strlen('paymongo:')));
        } catch (\Throwable $exception) {
            report($exception);

            return redirect()->route('bookings.receipt', $booking)->withErrors(['payment' => 'We could not confirm the test payment yet. Please try again in a moment.']);
        }
        if (! $checkout->isPaid($session)) {
            return redirect()->route('bookings.receipt', $booking)->withErrors(['payment' => 'The test payment was not completed. You can try GCash checkout again.']);
        }

        $wasRecorded = DB::transaction(function () use ($booking, $pendingPayment) {
            Room::whereKey($booking->room_id)->lockForUpdate()->firstOrFail();
            $lockedBooking = Booking::whereKey($booking->id)->lockForUpdate()->firstOrFail();
            $payment = Payment::whereKey($pendingPayment->id)->lockForUpdate()->firstOrFail();
            if ($payment->status === 'paid') {
                return false;
            }
            $expired = $lockedBooking->hasExpiredHold();
            $payment->update(['status' => 'paid', 'paid_at' => now(), 'notes' => 'PayMongo test payment confirmed by server lookup.']);
            $lockedBooking->update(['payment_method' => 'gcash', 'payment_status' => 'paid', 'paid_at' => now()] + ($expired ? [
                'status' => 'cancelled', 'cancelled_at' => now(), 'cancellation_reason' => 'Payment returned after the reservation hold expired; staff resolution required.',
            ] : []));

            return true;
        });
        if ($wasRecorded) {
            $booking->refresh();
            $this->log($booking, $request->user()?->id, 'payment_paid', 'PayMongo test GCash payment confirmed by server lookup.');
            $this->emailUpdate($booking, 'Your Carolina test payment was received', 'Your GCash test payment has been recorded. Staff will still review your reservation.');
        }

        $booking->refresh();

        return redirect()->route('bookings.receipt', $booking)->with('success', $booking->status === 'cancelled'
            ? 'Test payment recorded, but this reservation is no longer held. Contact Carolina for staff resolution; your room was not re-reserved.'
            : 'GCash test payment confirmed. Staff will still review your reservation.');
    }

    public function cancel(Request $request, Booking $booking)
    {
        abort_unless($booking->user_id === $request->user()?->id, 403);
        $refundEligible = $this->cancelBeforeCheckIn($booking);

        return back()->with('success', 'Your booking has been cancelled. The room is available for those dates again.'.($refundEligible ? ' If you have already paid, this cancellation is eligible for a refund.' : ' This cancellation is non-refundable because it was made less than 3 days before check-in.'));
    }

    public function cancelGuest(Request $request, Booking $booking)
    {
        if ($request->filled('email')) {
            $request->merge(['email' => strtolower(trim((string) $request->input('email')))]);
        }
        $data = $request->validate(['email' => 'required|email:rfc|max:255', 'reference' => 'required|string']);
        abort_unless(
            $booking->user_id === null
                && strcasecmp($booking->reference, trim($data['reference'])) === 0
                && strcasecmp((string) $booking->guest_email, trim($data['email'])) === 0,
            403
        );

        $refundEligible = $this->cancelBeforeCheckIn($booking);
        session()->flash('success', 'Your booking has been cancelled. The room is available for those dates again.'.($refundEligible ? ' If you have already paid, this cancellation is eligible for a refund.' : ' This cancellation is non-refundable because it was made less than 3 days before check-in.'));

        return view('bookings.lookup-result', ['booking' => $booking->fresh('room'), 'lookupEmail' => $data['email']]);
    }

    public function extendGuest(Request $request, Booking $booking)
    {
        if ($request->filled('email')) {
            $request->merge(['email' => strtolower(trim((string) $request->input('email')))]);
        }
        $data = $request->validate(['email' => 'required|email:rfc|max:255', 'reference' => 'required|string', 'hours' => 'required|integer|in:6,12,22,24,48,72,96,120,168']);
        abort_unless($booking->user_id === null && strcasecmp($booking->reference, trim($data['reference'])) === 0 && strcasecmp((string) $booking->guest_email, trim($data['email'])) === 0, 403);
        if ($booking->status !== 'confirmed' || $booking->checked_out_at) {
            return back()->withErrors(['hours' => 'Extensions are available only for confirmed bookings that have not checked out.']);
        }
        $end = $booking->check_out_at ?? $booking->check_out->copy()->startOfDay();
        $newEnd = $end->copy()->addHours((int) $data['hours']);
        $conflict = Booking::where('room_id', $booking->room_id)->whereKeyNot($booking->id)->blocking()->overlapping($end, $newEnd)->exists() || RoomBlock::where('room_id', $booking->room_id)->overlapping($end, $newEnd)->exists();
        if ($conflict) {
            return back()->withErrors(['hours' => 'The room is not available for that extension length.']);
        }
        $baseHours = $booking->room->rental_hours ?: 24;
        $extra = round($booking->room->price_per_night * ($data['hours'] / $baseHours), 2);
        $booking->update(['check_out_at' => $newEnd, 'check_out' => $newEnd->toDateString(), 'hours' => ($booking->hours ?? 0) + $data['hours'], 'nights' => max(1, (int) ceil(($booking->hours + $data['hours']) / 24)), 'total_amount' => $booking->total_amount + $extra]);
        $this->log($booking, null, 'stay_extended', "Guest extended the stay by {$data['hours']} hours.");
        $this->emailUpdate($booking->fresh('room'), 'Your Carolina stay was extended', "Your stay was extended by {$data['hours']} hours. The updated total is included below.");

        return back()->with('success', 'Your stay was extended. Your updated receipt total is now available.');
    }

    public function lookupForm()
    {
        return view('bookings.lookup');
    }

    public function lookup(Request $request)
    {
        if ($request->filled('email')) {
            $request->merge(['email' => strtolower(trim((string) $request->input('email')))]);
        } $data = $request->validate(['email' => 'required|email:rfc|max:255', 'reference' => 'required|string']);
        $booking = Booking::with('room')->where('reference', strtoupper(trim($data['reference'])))->where(function ($query) use ($data) {
            $query->where('guest_email', $data['email'])->orWhereHas('user', fn ($user) => $user->where('email', $data['email']));
        })->first();
        if (! $booking) {
            return back()->withErrors(['reference' => 'No booking matches that email and reference code.']);
        } $request->session()->put('guest_booking_reference', $booking->reference);

        return view('bookings.lookup-result', ['booking' => $booking, 'lookupEmail' => $data['email']]);
    }

    private function cancelBeforeCheckIn(Booking $booking): bool
    {
        abort_if($booking->status === 'cancelled', 422, 'This booking has already been cancelled.');
        abort_if($booking->check_in->isToday() || $booking->check_in->isPast(), 422, 'This reservation can no longer be cancelled online after check-in day begins.');
        $booking->update(['status' => 'cancelled', 'cancelled_at' => now(), 'cancellation_reason' => 'Cancelled by guest.']);
        $refundEligible = $booking->isRefundEligible();
        $this->log($booking, null, 'booking_cancelled', 'Booking cancelled by guest.');
        $this->emailUpdate($booking, 'Your Carolina booking was cancelled', 'Your reservation has been cancelled and the room is available again.'.($refundEligible ? ' If you have already paid, this cancellation is eligible for a refund.' : ' This cancellation is non-refundable because it was made less than 3 days before check-in.'));

        return $refundEligible;
    }

    private function authorizeBookingAccess(Request $request, Booking $booking): void
    {
        $isOwner = $booking->user_id && $request->user() && $booking->user_id === $request->user()->id;
        $isGuestSession = $booking->user_id === null && $request->session()->get('guest_booking_reference') === $booking->reference;
        abort_unless($isOwner || $isGuestSession || $request->user()?->is_admin, 403);
    }

    private function log(Booking $booking, ?int $userId, string $event, string $description): void
    {
        ActivityLog::create(['booking_id' => $booking->id, 'user_id' => $userId, 'event' => $event, 'description' => $description]);
    }

    private function emailUpdate(Booking $booking, string $subject, string $message): void
    {
        $email = $booking->guest_email ?? $booking->user?->email;
        if (! $email) {
            return;
        }
        try {
            Mail::to($email)->send(new BookingUpdate($booking, $subject, $message));
        } catch (\Throwable $exception) {
            report($exception);
        }
    }
}
