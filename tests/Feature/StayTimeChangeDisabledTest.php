<?php

use App\Models\Booking;
use App\Models\Payment;
use App\Models\Room;
use App\Models\User;

function disabledStayChangeRoom(): Room
{
    return Room::create([
        'name' => 'Stay Change Test Room',
        'slug' => 'stay-change-test-room',
        'room_type' => 'Standard',
        'description' => 'A test room.',
        'beds' => 1,
        'guests' => 2,
        'price_per_night' => 1000,
        'rental_hours' => 24,
        'is_active' => true,
        'operational_status' => 'available',
    ]);
}

function disabledStayChangeBooking(Room $room): Booking
{
    $start = now()->subHour();

    return Booking::create([
        'room_id' => $room->id,
        'guest_name' => 'Test Guest',
        'guest_email' => 'guest@example.test',
        'reference' => 'CAR-TIMEDIS',
        'check_in' => $start->toDateString(),
        'check_out' => $start->copy()->addDay()->toDateString(),
        'check_in_at' => $start,
        'check_out_at' => $start->copy()->addDay(),
        'checked_in_at' => $start,
        'guests' => 1,
        'hours' => 24,
        'nights' => 1,
        'total_amount' => 1000,
        'payment_method' => 'gcash',
        'payment_status' => 'paid',
        'paid_at' => now(),
        'status' => 'confirmed',
    ]);
}

test('guest booking management no longer offers stay extensions', function () {
    $booking = disabledStayChangeBooking(disabledStayChangeRoom());

    $this->post(route('bookings.lookup.submit'), [
        'email' => $booking->guest_email,
        'reference' => $booking->reference,
    ])->assertOk()
        ->assertDontSee('Extend your stay')
        ->assertDontSee('Continue to GCash');
});

test('staff bookings no longer offer early check-in or extra checkout time', function () {
    $admin = User::factory()->create(['is_admin' => true]);
    $booking = disabledStayChangeBooking(disabledStayChangeRoom());

    $this->actingAsStaff($admin)->get(route('admin.bookings'))
        ->assertOk()
        ->assertSee($booking->reference)
        ->assertDontSee('Check in early')
        ->assertDontSee('Add checkout time')
        ->assertDontSee('Choose extra time and payment');
});

test('guest and staff cannot start new stay time changes', function () {
    $admin = User::factory()->create(['is_admin' => true]);
    $booking = disabledStayChangeBooking(disabledStayChangeRoom());
    $originalCheckout = $booking->check_out_at->copy();

    $this->patch('/booking-lookup/'.$booking->id.'/extend', [
        'email' => $booking->guest_email,
        'reference' => $booking->reference,
        'hours' => 1,
    ])->assertNotFound();

    $this->actingAsStaff($admin)->post('/admin/bookings/'.$booking->id.'/time-adjustments', [
        'type' => 'late_checkout',
        'hours' => 1,
        'payment_method' => 'gcash',
    ])->assertNotFound();

    expect(Payment::where('booking_id', $booking->id)->exists())->toBeFalse()
        ->and($booking->fresh()->check_out_at->equalTo($originalCheckout))->toBeTrue();
});
