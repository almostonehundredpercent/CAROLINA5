<?php

use App\Models\Room;

test('guest booking form shows useful examples in contact and special request fields', function () {
    $room = Room::create([
        'name' => 'Guest Form Test Room',
        'slug' => 'guest-form-test-room',
        'room_type' => 'Standard',
        'description' => 'A room used to test the guest booking form.',
        'beds' => 1,
        'guests' => 2,
        'price_per_night' => 1000,
        'is_active' => true,
        'operational_status' => 'available',
    ]);

    $this->get(route('bookings.create', ['room' => $room, 'guest' => 1]))
        ->assertOk()
        ->assertSee('placeholder="e.g., Maria Santos"', false)
        ->assertSee('placeholder="e.g., maria@example.com"', false)
        ->assertSee('placeholder="09XX XXX XXXX"', false)
        ->assertSee('title="Enter a Philippine mobile number, such as 09XX XXX XXXX or +63 9XX XXX XXXX."', false)
        ->assertSee('placeholder="e.g., Extra pillow, if available."', false);
});

test('invalid guest phone is explained inline while submitted guest details are retained', function () {
    $room = Room::create([
        'name' => 'Validation Test Room',
        'slug' => 'validation-test-room',
        'room_type' => 'Standard',
        'description' => 'A room used to test booking validation.',
        'beds' => 1,
        'guests' => 2,
        'price_per_night' => 1000,
        'is_active' => true,
        'operational_status' => 'available',
    ]);
    $formUrl = route('bookings.create', ['room' => $room, 'guest' => 1]);

    $this->from($formUrl)->post(route('bookings.store', $room), [
        'checkout_type' => 'guest',
        'booking_type' => 'dates',
        'submission_token' => (string) \Illuminate\Support\Str::uuid(),
        'terms_accepted' => '1',
        'check_in' => now('Asia/Manila')->addDays(3)->toDateString(),
        'check_out' => now('Asia/Manila')->addDays(4)->toDateString(),
        'guests' => 1,
        'guest_name' => 'Maria Example',
        'guest_email' => 'maria@example.test',
        'guest_phone' => '123',
    ])->assertRedirect($formUrl)->assertSessionHasErrors('guest_phone');

    $this->get($formUrl)
        ->assertOk()
        ->assertSee('id="booking-validation-summary"', false)
        ->assertSee('The guest phone field format is invalid.')
        ->assertSee('id="guest-phone-error"', false)
        ->assertSee('name="guest_name" value="Maria Example"', false);
});
