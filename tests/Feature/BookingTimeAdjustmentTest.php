<?php

use App\Models\Booking;
use App\Models\BookingTimeAdjustment;
use App\Models\Payment;
use App\Models\Room;
use App\Models\RoomBlock;
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
    $checkIn = now()->subHour()->startOfHour();
    $attributes += [
        'room_id' => $room->id, 'guest_name' => 'Test Guest', 'guest_email' => 'test@example.test',
        'reference' => 'CAR-TIMEPAY', 'check_in' => $checkIn->toDateString(),
        'check_out' => $checkIn->copy()->addHours(24)->toDateString(),
        'check_in_at' => $checkIn, 'check_out_at' => $checkIn->copy()->addHours(24),
        'checked_in_at' => $checkIn, 'guests' => 1, 'hours' => 24, 'nights' => 1,
        'total_amount' => 1200, 'payment_method' => 'gcash', 'payment_status' => 'paid',
        'paid_at' => now(), 'status' => 'confirmed',
    ];

    return Booking::create($attributes);
}

function pendingTimeAdjustmentCheckout(Booking $booking, bool $expired = false): BookingTimeAdjustment
{
    $previousStart = $booking->check_in_at;
    $previousEnd = $booking->check_out_at;
    $proposedEnd = $previousEnd->copy()->addHour();
    $expiresAt = $expired ? now()->subMinute() : now()->addMinutes(20);
    $block = RoomBlock::create([
        'room_id' => $booking->room_id, 'status' => 'adjustment_hold', 'starts_at' => $previousEnd,
        'ends_at' => $proposedEnd, 'hold_expires_at' => $expiresAt, 'notes' => 'Existing checkout hold.',
    ]);
    $payment = Payment::create([
        'booking_id' => $booking->id, 'amount' => 50, 'method' => 'gcash', 'status' => 'pending',
        'reference' => 'paymongo:cs_existing_addon',
    ]);

    return BookingTimeAdjustment::create([
        'booking_id' => $booking->id, 'room_block_id' => $block->id, 'payment_id' => $payment->id,
        'type' => 'late_checkout', 'hours' => 1, 'amount' => 50,
        'previous_start' => $previousStart, 'previous_end' => $previousEnd,
        'proposed_start' => $previousStart, 'proposed_end' => $proposedEnd,
        'status' => 'pending', 'expires_at' => $expiresAt,
    ]);
}

beforeEach(function () {
    config()->set('services.paymongo.mode', 'test');
    config()->set('services.paymongo.secret_key', 'sk_test_example');
});

test('legacy time-adjustment quote uses the configured room hourly rate', function () {
    $booking = timeAdjustmentBooking(timeAdjustmentRoom());

    expect(BookingTimeAdjustment::quoteAmount($booking, 1))->toBe(50.0)
        ->and(BookingTimeAdjustment::quoteAmount($booking, 2))->toBe(100.0);
});

test('expired legacy time-adjustment holds no longer block the room', function () {
    $room = timeAdjustmentRoom();
    $booking = timeAdjustmentBooking($room);
    $block = RoomBlock::create([
        'room_id' => $room->id, 'status' => 'adjustment_hold', 'starts_at' => now()->addHour(),
        'ends_at' => now()->addHours(2), 'hold_expires_at' => now()->subMinute(), 'notes' => 'Expired hold.',
    ]);

    expect($room->blocks()->overlapping(now()->addMinutes(90), now()->addMinutes(150))->exists())->toBeFalse()
        ->and($booking->exists)->toBeTrue()
        ->and($block->exists)->toBeTrue();
});

test('a previously started PayMongo time-change payment can still finish safely', function () {
    $booking = timeAdjustmentBooking(timeAdjustmentRoom());
    $adjustment = pendingTimeAdjustmentCheckout($booking);
    Http::fake(['https://api.paymongo.com/v1/checkout_sessions/cs_existing_addon' => Http::response([
        'data' => ['id' => 'cs_existing_addon', 'attributes' => [
            'livemode' => false,
            'reference_number' => $booking->reference,
            'payments' => [['attributes' => ['amount' => 5000, 'currency' => 'PHP', 'status' => 'paid']]],
        ]],
    ])]);

    $this->withSession(['guest_booking_reference' => $booking->reference])
        ->get(route('bookings.paymongo.return', [
            'booking' => $booking, 'outcome' => 'success', 'adjustment' => $adjustment->id,
        ]))
        ->assertRedirect(route('bookings.receipt', $booking));

    expect($adjustment->fresh()->status)->toBe('applied')
        ->and($booking->fresh()->check_out_at->equalTo($adjustment->proposed_end))->toBeTrue()
        ->and((float) $booking->fresh()->total_amount)->toBe(1250.0)
        ->and(Payment::whereKey($adjustment->payment_id)->firstOrFail()->status)->toBe('paid');
});

test('a late payment for an existing time-change checkout is saved for staff review', function () {
    $booking = timeAdjustmentBooking(timeAdjustmentRoom());
    $adjustment = pendingTimeAdjustmentCheckout($booking, expired: true);
    Http::fake(['https://api.paymongo.com/v1/checkout_sessions/cs_existing_addon' => Http::response([
        'data' => ['id' => 'cs_existing_addon', 'attributes' => [
            'livemode' => false,
            'reference_number' => $booking->reference,
            'payments' => [['attributes' => ['amount' => 5000, 'currency' => 'PHP', 'status' => 'paid']]],
        ]],
    ])]);

    $this->withSession(['guest_booking_reference' => $booking->reference])
        ->get(route('bookings.paymongo.return', [
            'booking' => $booking, 'outcome' => 'success', 'adjustment' => $adjustment->id,
        ]))
        ->assertRedirect(route('bookings.receipt', $booking));

    expect($adjustment->fresh()->status)->toBe('needs_review')
        ->and($booking->fresh()->check_out_at->equalTo($adjustment->previous_end))->toBeTrue()
        ->and((float) $booking->fresh()->total_amount)->toBe(1200.0)
        ->and(Payment::whereKey($adjustment->payment_id)->firstOrFail()->status)->toBe('paid');
});
