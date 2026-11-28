<?php

use App\Models\Booking;
use App\Models\Payment;
use App\Models\Room;
use App\Models\User;
use App\Support\StayPricing;

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

test('guest booking management offers stay extensions for active stays', function () {
    $booking = disabledStayChangeBooking(disabledStayChangeRoom());

    $this->post(route('bookings.lookup.submit'), [
        'email' => $booking->guest_email,
        'reference' => $booking->reference,
    ])->assertRedirect(route('bookings.lookup.show', $booking));
    $this->get(route('bookings.lookup.show', $booking))
        ->assertOk()->assertSee('Extend your stay')
        ->assertDontSee('Continue to GCash');
});

test('staff bookings offer stay extensions for active stays', function () {
    $admin = User::factory()->create(['is_admin' => true]);
    $booking = disabledStayChangeBooking(disabledStayChangeRoom());

    $this->actingAsStaff($admin)->get(route('admin.bookings.show', $booking))
        ->assertOk()
        ->assertSee($booking->reference)
        ->assertDontSee('Check in early')
        ->assertSee('Extend stay hours')
        ->assertDontSee('Choose extra time and payment');
});

test('obsolete stay time change routes remain unavailable', function () {
    $admin = User::factory()->create(['is_admin' => true]);
    $booking = disabledStayChangeBooking(disabledStayChangeRoom());
    $originalCheckout = $booking->check_out_at->copy();

    $this->patch('/booking-lookup/'.$booking->id.'/extend', [
        'email' => $booking->guest_email,
        'reference' => $booking->reference,
        'hours' => 1,
    ])->assertStatus(405);

    $this->actingAsStaff($admin)->post('/admin/bookings/'.$booking->id.'/time-adjustments', [
        'type' => 'late_checkout',
        'hours' => 1,
        'payment_method' => 'gcash',
    ])->assertNotFound();

    expect(Payment::where('booking_id', $booking->id)->exists())->toBeFalse()
        ->and($booking->fresh()->check_out_at->equalTo($originalCheckout))->toBeTrue();
});

test('a registered guest can extend an active stay from their bookings', function () {
    $guest = User::factory()->create(['is_admin' => false, 'staff_role' => 'guest']);
    $booking = disabledStayChangeBooking(disabledStayChangeRoom());
    $booking->update(['user_id' => $guest->id]);
    $oldCheckout = $booking->check_out_at->copy();

    $this->actingAs($guest)->get(route('bookings.index'))
        ->assertOk()->assertSee('Manage stay')->assertSee('Extend hours or request early check-out')
        ->assertSee('stay-tools-'.$booking->id)->assertSee('Add hours')
        ->assertDontSee('href="'.route('bookings.lookup.show', $booking).'"', false)
        ->assertSee('Settle your remaining balance');
    $this->get(route('bookings.lookup.show', $booking))->assertOk()->assertSee('Extend your stay');
    $this->post(route('bookings.lookup.extend', $booking), ['hours' => 2])->assertRedirect();

    $booking->refresh();
    expect($booking->check_out_at->equalTo($oldCheckout->addHours(2)))->toBeTrue()
        ->and((float) $booking->total_amount)->toBe((float) (1000 + 2 * StayPricing::EXTRA_HOUR_RATE));
});

test('front desk can extend an active stay and a viewer cannot', function () {
    $booking = disabledStayChangeBooking(disabledStayChangeRoom());
    $desk = User::factory()->create(['is_admin' => false, 'staff_role' => 'front_desk']);
    $viewer = User::factory()->create(['is_admin' => false, 'staff_role' => 'viewer']);

    $this->actingAsStaff($desk)->get(route('admin.bookings.show', $booking))->assertOk()->assertSee('Extend stay hours');
    $this->get(route('admin.frontdesk'))->assertOk()->assertSee('Extend hours');
    $this->post(route('admin.bookings.extend', $booking), ['hours' => 1])->assertRedirect();
    expect((float) $booking->fresh()->total_amount)->toBe((float) (1000 + StayPricing::EXTRA_HOUR_RATE));

    $this->actingAsStaff($viewer)->post(route('admin.bookings.extend', $booking), ['hours' => 1])->assertForbidden();
});

test('an extension cannot consume the following booking cleaning time', function () {
    $room = disabledStayChangeRoom();
    $booking = disabledStayChangeBooking($room);
    $nextCheckIn = $booking->check_out_at->copy()->addHours(2);
    Booking::create([
        'room_id' => $room->id, 'guest_name' => 'Next Guest', 'guest_email' => 'next@example.test',
        'check_in' => $nextCheckIn->toDateString(), 'check_out' => $nextCheckIn->copy()->addDay()->toDateString(),
        'check_in_at' => $nextCheckIn, 'check_out_at' => $nextCheckIn->copy()->addDay(),
        'guests' => 1, 'hours' => 24, 'nights' => 1, 'total_amount' => 1000,
        'payment_method' => 'cash', 'payment_status' => 'paid', 'status' => 'confirmed',
    ]);
    $admin = User::factory()->create(['is_admin' => true]);

    $this->actingAsStaff($admin)->post(route('admin.bookings.extend', $booking), ['hours' => 1])
        ->assertSessionHasErrors('extension');
    expect((float) $booking->fresh()->total_amount)->toBe(1000.0);
});

test('a guest cannot extend another account’s stay or add invalid hours', function () {
    $owner = User::factory()->create(['is_admin' => false, 'staff_role' => 'guest']);
    $otherGuest = User::factory()->create(['is_admin' => false, 'staff_role' => 'guest']);
    $booking = disabledStayChangeBooking(disabledStayChangeRoom());
    $booking->update(['user_id' => $owner->id]);

    $this->actingAs($otherGuest)->post(route('bookings.lookup.extend', $booking), ['hours' => 1])->assertForbidden();
    $this->actingAs($owner)->post(route('bookings.lookup.extend', $booking), ['hours' => 0])->assertSessionHasErrors('hours');
    expect((float) $booking->fresh()->total_amount)->toBe(1000.0);
});
