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
        ->assertSee('placeholder="09169907895"', false)
        ->assertSee('placeholder="e.g., Extra pillow, if available."', false);
});
