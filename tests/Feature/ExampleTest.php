<?php

use App\Models\Booking;
use App\Models\Room;
use Illuminate\Support\Facades\Mail;

function testRoom(): Room
{
    return Room::create([
        'name' => 'Test Room', 'slug' => 'test-room', 'room_type' => 'Standard',
        'description' => 'A test room.', 'beds' => 1, 'guests' => 2,
        'price_per_night' => 1000, 'is_active' => true, 'operational_status' => 'available',
    ]);
}

test('the public home page loads', function () {
    $this->get('/')->assertOk();
});

test('a guest request creates a short staff-review hold with exact timestamps', function () {
    Mail::fake();
    $room = testRoom();
    $checkIn = now()->addDays(3)->startOfDay();

    $this->post(route('bookings.store', $room), [
        'checkout_type' => 'guest', 'booking_type' => 'dates', 'check_in' => $checkIn->toDateString(),
        'check_out' => $checkIn->copy()->addDays(2)->toDateString(), 'guests' => 2,
        'guest_name' => 'Test Guest', 'guest_email' => 'guest@gmail.com', 'guest_phone' => '09171234567',
        'terms_accepted' => '1',
    ])->assertRedirect()->assertSessionHasNoErrors();

    $booking = Booking::firstOrFail();
    expect($booking->status)->toBe('pending')
        ->and($booking->hold_expires_at)->not->toBeNull()
        ->and($booking->check_in_at->toDateString())->toBe($checkIn->toDateString())
        ->and($booking->check_out_at->toDateString())->toBe($checkIn->copy()->addDays(2)->toDateString());
});

test('expired reservation requests no longer block a room', function () {
    $room = testRoom();
    Booking::create([
        'room_id' => $room->id, 'guest_name' => 'Old request', 'guest_email' => 'old@example.com',
        'guest_phone' => '09171234567', 'check_in' => now()->addDays(4)->toDateString(),
        'check_out' => now()->addDays(5)->toDateString(), 'check_in_at' => now()->addDays(4)->startOfDay(),
        'check_out_at' => now()->addDays(5)->startOfDay(), 'guests' => 1, 'nights' => 1,
        'total_amount' => 1000, 'payment_method' => 'cash', 'status' => 'pending',
        'hold_expires_at' => now()->subMinute(),
    ]);

    Booking::releaseExpiredHolds();
    expect(Booking::first()->fresh()->status)->toBe('cancelled');
});
