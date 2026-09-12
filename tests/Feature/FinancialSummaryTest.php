<?php

use App\Models\Booking;
use App\Models\Payment;
use App\Models\Room;
use App\Support\FinancialSummary;

function financeBooking(string $status, int $amount): Booking
{
    $room = Room::create([
        'name' => 'Finance room ' . uniqid(), 'slug' => 'finance-' . uniqid(), 'room_type' => 'Test',
        'description' => 'Financial calculation test room.', 'beds' => 1, 'guests' => 2,
        'price_per_night' => $amount, 'is_active' => true,
    ]);

    return Booking::create([
        'room_id' => $room->id, 'guest_name' => 'Finance guest', 'guest_email' => uniqid() . '@example.test',
        'guest_phone' => '09171234567', 'check_in' => now()->addDay()->toDateString(),
        'check_out' => now()->addDays(2)->toDateString(), 'check_in_at' => now()->addDay()->startOfHour(),
        'check_out_at' => now()->addDays(2)->startOfHour(), 'guests' => 1, 'nights' => 1,
        'total_amount' => $amount, 'payment_method' => 'cash', 'payment_status' => 'pending', 'status' => $status,
    ]);
}

test('the payment ledger distinguishes booked value, payments, refunds, and outstanding balances', function () {
    $unpaid = financeBooking('confirmed', 1000);
    $paid = financeBooking('confirmed', 900);
    $cancelled = financeBooking('cancelled', 700);

    Payment::create(['booking_id' => $paid->id, 'amount' => 900, 'method' => 'cash', 'status' => 'paid', 'paid_at' => now()]);
    Payment::create(['booking_id' => $paid->id, 'amount' => 200, 'method' => 'cash', 'status' => 'refunded', 'paid_at' => now()]);

    $metrics = FinancialSummary::forBookings(Booking::with('payments')->get());

    expect($metrics)->toMatchArray([
        'booked_value' => 1900.0,
        'confirmed_value' => 1900.0,
        'paid_revenue' => 900.0,
        'refunds' => 200.0,
        'net_collected_revenue' => 700.0,
        'outstanding_balance' => 1200.0,
    ]);
});

test('cancelled reservations never leave an outstanding balance', function () {
    $booking = financeBooking('cancelled', 1000);

    expect(FinancialSummary::forBooking($booking)['outstanding'])->toBe(0.0);
});
