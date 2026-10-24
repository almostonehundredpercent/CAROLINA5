<?php

use App\Models\Booking;
use App\Models\BookingTimeAdjustment;
use App\Models\Payment;
use App\Models\Room;
use App\Models\RoomBlock;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;

function timeAdjustmentRoom(string $slug = 'time-adjustment-room'): Room
{
    return Room::create([
        'name' => 'Time Adjustment Room', 'slug' => $slug, 'room_type' => 'Standard',
        'description' => 'Test room.', 'beds' => 1, 'guests' => 2, 'price_per_night' => 1200,
        'rental_hours' => 24, 'is_active' => true, 'operational_status' => 'available',
    ]);
}

function timeAdjustmentBooking(Room $room, array $attributes = []): Booking
{
    $checkIn = now()->addHours(2)->startOfHour();
    $attributes += [
        'room_id' => $room->id, 'guest_name' => 'Test Guest', 'guest_email' => 'test@example.test',
        'check_in' => $checkIn->toDateString(), 'check_out' => $checkIn->copy()->addHours(24)->toDateString(),
        'check_in_at' => $checkIn, 'check_out_at' => $checkIn->copy()->addHours(24), 'guests' => 1,
        'hours' => 24, 'nights' => 1, 'total_amount' => 1200, 'payment_method' => 'cash',
        'payment_status' => 'paid', 'paid_at' => now(), 'status' => 'confirmed',
    ];

    return Booking::create($attributes);
}

beforeEach(function () {
    config()->set('services.paymongo.mode', 'test');
    config()->set('services.paymongo.secret_key', 'sk_test_example');
    config()->set('booking.hold_minutes', 30);
});

test('time adjustment quote uses the configured room hourly rate', function () {
    $booking = timeAdjustmentBooking(timeAdjustmentRoom());

    expect(BookingTimeAdjustment::quoteAmount($booking, 1))->toBe(50.0)
        ->and(BookingTimeAdjustment::quoteAmount($booking, 2))->toBe(100.0);
});

test('an expired time adjustment hold no longer blocks the room', function () {
    $room = timeAdjustmentRoom();
    $booking = timeAdjustmentBooking($room);
    $block = RoomBlock::create([
        'room_id' => $room->id, 'status' => 'adjustment_hold', 'starts_at' => now()->addHour(),
        'ends_at' => now()->addHours(2), 'hold_expires_at' => now()->subMinute(), 'notes' => 'Temporary test hold.',
    ]);

    expect($room->blocks()->overlapping(now()->addMinutes(90), now()->addMinutes(150))->exists())->toBeFalse();
    expect($booking->exists)->toBeTrue();
    expect($block->exists)->toBeTrue();
});

test('a guest extension remains unchanged until PayMongo confirms the extra payment', function () {
    $room = timeAdjustmentRoom('guest-extension-room');
    $checkIn = now()->subHours(2)->startOfHour();
    $booking = timeAdjustmentBooking($room, [
        'user_id' => null, 'reference' => 'CAR-TESTEXT', 'check_in' => $checkIn->toDateString(),
        'check_out' => $checkIn->copy()->addHours(24)->toDateString(), 'check_in_at' => $checkIn,
        'check_out_at' => $checkIn->copy()->addHours(24), 'checked_in_at' => $checkIn,
    ]);
    Http::fake(['https://api.paymongo.com/v1/checkout_sessions' => Http::response([
        'data' => ['id' => 'cs_extension_test', 'attributes' => ['checkout_url' => 'https://checkout.paymongo.test/cs_extension_test']],
    ])]);

    $this->patch(route('bookings.lookup.extend', $booking), [
        'email' => $booking->guest_email, 'reference' => $booking->reference, 'hours' => 24,
    ])->assertRedirect('https://checkout.paymongo.test/cs_extension_test');

    expect($booking->fresh()->check_out_at->equalTo($checkIn->copy()->addHours(24)))->toBeTrue()
        ->and((float) $booking->fresh()->total_amount)->toBe(1200.0)
        ->and(Payment::where('booking_id', $booking->id)->where('status', 'pending')->count())->toBe(1)
        ->and(RoomBlock::where('room_id', $room->id)->where('status', 'adjustment_hold')->count())->toBe(1);
});

test('staff can add one checkout hour with a cash payment recorded as received', function () {
    Carbon::setTestNow('2026-10-24 10:00:00');
    $admin = User::factory()->create(['is_admin' => true]);
    $room = timeAdjustmentRoom('staff-extension-room');
    $checkIn = now()->subHours(4);
    $booking = timeAdjustmentBooking($room, [
        'check_in' => $checkIn->toDateString(), 'check_out' => now()->addHours(4)->toDateString(),
        'check_in_at' => $checkIn, 'check_out_at' => now()->addHours(4), 'checked_in_at' => $checkIn,
        'hours' => 24, 'nights' => 1,
    ]);

    $this->actingAsStaff($admin)->post(route('admin.bookings.time-adjustments', $booking), [
        'type' => 'late_checkout', 'hours' => 1, 'payment_method' => 'cash',
    ])->assertRedirect();

    expect($booking->fresh()->check_out_at->equalTo(now()->addHours(5)))->toBeTrue()
        ->and((float) $booking->fresh()->total_amount)->toBe(1250.0)
        ->and(Payment::where('booking_id', $booking->id)->where('status', 'paid')->where('method', 'cash')->count())->toBe(1)
        ->and(BookingTimeAdjustment::where('booking_id', $booking->id)->where('status', 'applied')->count())->toBe(1);
    Carbon::setTestNow();
});

test('staff can check in early after cash is received and availability is held', function () {
    Carbon::setTestNow('2026-10-24 10:00:00');
    $admin = User::factory()->create(['is_admin' => true]);
    $room = timeAdjustmentRoom('staff-early-arrival-room');
    $checkIn = now()->addHour();
    $booking = timeAdjustmentBooking($room, [
        'check_in' => $checkIn->toDateString(), 'check_out' => $checkIn->copy()->addHours(24)->toDateString(),
        'check_in_at' => $checkIn, 'check_out_at' => $checkIn->copy()->addHours(24), 'hours' => 24,
    ]);

    $this->actingAsStaff($admin)->post(route('admin.bookings.time-adjustments', $booking), [
        'type' => 'early_checkin', 'hours' => 1, 'payment_method' => 'cash',
    ])->assertRedirect();

    expect($booking->fresh()->checked_in_at->equalTo(now()))->toBeTrue()
        ->and($booking->fresh()->check_in_at->equalTo(now()))->toBeTrue()
        ->and((float) $booking->fresh()->total_amount)->toBe(1250.0);
    Carbon::setTestNow();
});

test('the bookings page offers short and priced time-change actions for paid stays', function () {
    Carbon::setTestNow('2026-10-24 10:00:00');
    $admin = User::factory()->create(['is_admin' => true]);
    $earlyRoom = timeAdjustmentRoom('early-arrival-controls-room');
    $arrival = timeAdjustmentBooking($earlyRoom, [
        'check_in' => now()->addHour()->toDateString(), 'check_out' => now()->addHours(25)->toDateString(),
        'check_in_at' => now()->addHour(), 'check_out_at' => now()->addHours(25),
    ]);
    $activeRoom = timeAdjustmentRoom('active-stay-controls-room');
    $checkIn = now()->subHours(4);
    $active = timeAdjustmentBooking($activeRoom, [
        'check_in' => $checkIn->toDateString(), 'check_out' => now()->addHours(4)->toDateString(),
        'check_in_at' => $checkIn, 'check_out_at' => now()->addHours(4), 'checked_in_at' => $checkIn,
    ]);

    $this->actingAsStaff($admin)->get(route('admin.bookings'))
        ->assertOk()
        ->assertSee('Check in early')
        ->assertSee('Add checkout time')
        ->assertSee('Cash received')
        ->assertSee('Pay by GCash')
        ->assertSee('₱50.00')
        ->assertSee($arrival->reference)
        ->assertSee($active->reference);
    Carbon::setTestNow();
});

test('PayMongo confirms an add-on without rewriting the original booking payment state', function () {
    Carbon::setTestNow('2026-10-24 10:00:00');
    $room = timeAdjustmentRoom('paymongo-extension-room');
    $checkIn = now()->subHours(4);
    $booking = timeAdjustmentBooking($room, [
        'user_id' => null, 'reference' => 'CAR-ADDEXT1', 'check_in' => $checkIn->toDateString(),
        'check_out' => now()->addHours(4)->toDateString(), 'check_in_at' => $checkIn,
        'check_out_at' => now()->addHours(4), 'checked_in_at' => $checkIn,
    ]);
    Http::fake([
        'https://api.paymongo.com/v1/checkout_sessions' => Http::response([
            'data' => ['id' => 'cs_addon_paid', 'attributes' => ['checkout_url' => 'https://checkout.paymongo.test/cs_addon_paid']],
        ]),
        'https://api.paymongo.com/v1/checkout_sessions/cs_addon_paid' => Http::response([
            'data' => ['id' => 'cs_addon_paid', 'attributes' => [
                'livemode' => false, 'reference_number' => $booking->reference,
                'payments' => [['attributes' => ['amount' => 5000, 'currency' => 'PHP', 'status' => 'paid']]],
            ]],
        ]),
    ]);

    $this->withSession(['guest_booking_reference' => $booking->reference])
        ->patch(route('bookings.lookup.extend', $booking), [
            'email' => $booking->guest_email, 'reference' => $booking->reference, 'hours' => 1,
        ])->assertRedirect('https://checkout.paymongo.test/cs_addon_paid');
    $adjustment = BookingTimeAdjustment::where('booking_id', $booking->id)->firstOrFail();
    $this->withSession(['guest_booking_reference' => $booking->reference])
        ->get(route('bookings.paymongo.return', ['booking' => $booking, 'outcome' => 'success', 'adjustment' => $adjustment->id]))
        ->assertRedirect(route('bookings.receipt', $booking))
        ->assertSessionHas('success', fn ($message) => str_contains($message, 'extra stay time is paid and confirmed'));

    expect($booking->fresh()->check_out_at->equalTo(now()->addHours(5)))->toBeTrue()
        ->and((float) $booking->fresh()->total_amount)->toBe(1250.0)
        ->and($booking->fresh()->payment_status)->toBe('paid')
        ->and($adjustment->fresh()->status)->toBe('applied')
        ->and(Payment::where('booking_id', $booking->id)->where('method', 'gcash')->where('status', 'paid')->count())->toBe(1);
    Carbon::setTestNow();
});

test('an unavailable hour cannot be sold as an added checkout time', function () {
    Carbon::setTestNow('2026-10-24 10:00:00');
    $admin = User::factory()->create(['is_admin' => true]);
    $room = timeAdjustmentRoom('conflicting-extension-room');
    $checkIn = now()->subHours(4);
    $booking = timeAdjustmentBooking($room, [
        'check_in' => $checkIn->toDateString(), 'check_out' => now()->addHours(4)->toDateString(),
        'check_in_at' => $checkIn, 'check_out_at' => now()->addHours(4), 'checked_in_at' => $checkIn,
    ]);
    $nextCheckIn = now()->addHours(4)->addMinutes(30);
    timeAdjustmentBooking($room, [
        'check_in' => $nextCheckIn->toDateString(), 'check_out' => $nextCheckIn->copy()->addHours(24)->toDateString(),
        'check_in_at' => $nextCheckIn, 'check_out_at' => $nextCheckIn->copy()->addHours(24),
    ]);

    $this->actingAsStaff($admin)->post(route('admin.bookings.time-adjustments', $booking), [
        'type' => 'late_checkout', 'hours' => 1, 'payment_method' => 'cash',
    ])->assertSessionHasErrors('hours');

    expect($booking->fresh()->check_out_at->equalTo(now()->addHours(4)))->toBeTrue()
        ->and((float) $booking->fresh()->total_amount)->toBe(1200.0)
        ->and(BookingTimeAdjustment::where('booking_id', $booking->id)->exists())->toBeFalse()
        ->and(Payment::where('booking_id', $booking->id)->exists())->toBeFalse();
    Carbon::setTestNow();
});

test('an expired checkout hold is released before staff starts a fresh time change', function () {
    Carbon::setTestNow('2026-10-24 10:00:00');
    $admin = User::factory()->create(['is_admin' => true]);
    $room = timeAdjustmentRoom('renewed-extension-room');
    $checkIn = now()->subHours(4);
    $booking = timeAdjustmentBooking($room, [
        'check_in' => $checkIn->toDateString(), 'check_out' => now()->addHours(4)->toDateString(),
        'check_in_at' => $checkIn, 'check_out_at' => now()->addHours(4), 'checked_in_at' => $checkIn,
    ]);
    Http::fake(['https://api.paymongo.com/v1/checkout_sessions' => Http::response([
        'data' => ['id' => 'cs_expired_addon', 'attributes' => ['checkout_url' => 'https://checkout.paymongo.test/cs_expired_addon']],
    ])]);

    $this->actingAsStaff($admin)->post(route('admin.bookings.time-adjustments', $booking), [
        'type' => 'late_checkout', 'hours' => 1, 'payment_method' => 'gcash',
    ])->assertRedirect('https://checkout.paymongo.test/cs_expired_addon');
    $first = BookingTimeAdjustment::where('booking_id', $booking->id)->firstOrFail();
    $first->update(['expires_at' => now()->subMinute()]);

    $this->actingAsStaff($admin)->post(route('admin.bookings.time-adjustments', $booking), [
        'type' => 'late_checkout', 'hours' => 1, 'payment_method' => 'cash',
    ])->assertRedirect();

    expect($first->fresh()->status)->toBe('expired')
        ->and(BookingTimeAdjustment::where('booking_id', $booking->id)->where('status', 'applied')->count())->toBe(1)
        ->and($booking->fresh()->check_out_at->equalTo(now()->addHours(5)))->toBeTrue();
    Carbon::setTestNow();
});

test('a late PayMongo add-on payment is recorded for staff without changing an expired stay', function () {
    Carbon::setTestNow('2026-10-24 10:00:00');
    $room = timeAdjustmentRoom('late-addon-payment-room');
    $checkIn = now()->subHours(4);
    $booking = timeAdjustmentBooking($room, [
        'user_id' => null, 'reference' => 'CAR-LATEPAY', 'check_in' => $checkIn->toDateString(),
        'check_out' => now()->addHours(4)->toDateString(), 'check_in_at' => $checkIn,
        'check_out_at' => now()->addHours(4), 'checked_in_at' => $checkIn,
    ]);
    Http::fake([
        'https://api.paymongo.com/v1/checkout_sessions' => Http::response([
            'data' => ['id' => 'cs_addon_late', 'attributes' => ['checkout_url' => 'https://checkout.paymongo.test/cs_addon_late']],
        ]),
        'https://api.paymongo.com/v1/checkout_sessions/cs_addon_late' => Http::response([
            'data' => ['id' => 'cs_addon_late', 'attributes' => [
                'livemode' => false, 'reference_number' => $booking->reference,
                'payments' => [['attributes' => ['amount' => 5000, 'currency' => 'PHP', 'status' => 'paid']]],
            ]],
        ]),
    ]);
    $this->withSession(['guest_booking_reference' => $booking->reference])
        ->patch(route('bookings.lookup.extend', $booking), [
            'email' => $booking->guest_email, 'reference' => $booking->reference, 'hours' => 1,
        ])->assertRedirect();
    $adjustment = BookingTimeAdjustment::where('booking_id', $booking->id)->firstOrFail();
    $adjustment->update(['expires_at' => now()->subMinute()]);

    $this->withSession(['guest_booking_reference' => $booking->reference])
        ->get(route('bookings.paymongo.return', ['booking' => $booking, 'outcome' => 'success', 'adjustment' => $adjustment->id]))
        ->assertRedirect(route('bookings.receipt', $booking))
        ->assertSessionHas('success', fn ($message) => str_contains($message, 'needs staff attention'));

    expect($booking->fresh()->check_out_at->equalTo(now()->addHours(4)))->toBeTrue()
        ->and((float) $booking->fresh()->total_amount)->toBe(1200.0)
        ->and(Payment::where('booking_id', $booking->id)->where('status', 'paid')->count())->toBe(1)
        ->and($adjustment->fresh()->status)->toBe('needs_review');
    Carbon::setTestNow();
});
