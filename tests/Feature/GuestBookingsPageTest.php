<?php

use App\Models\Booking;
use App\Models\Room;
use App\Models\User;

test('a checked-out guest booking renders separate action and review sections', function () {
    $user = User::factory()->create(['staff_role' => 'guest', 'is_admin' => false]);
    $room = Room::create([
        'name' => 'My bookings layout room',
        'slug' => 'my-bookings-layout-room',
        'room_type' => 'Standard',
        'description' => 'A room for the guest bookings layout test.',
        'beds' => 1,
        'guests' => 2,
        'price_per_night' => 450,
        'is_active' => true,
        'operational_status' => 'available',
    ]);
    Booking::create([
        'user_id' => $user->id,
        'room_id' => $room->id,
        'guest_name' => 'Guest Layout Test',
        'guest_email' => $user->email,
        'guest_phone' => '09XX XXX XXXX',
        'check_in' => '2026-10-21',
        'check_out' => '2026-10-23',
        'check_in_at' => '2026-10-21 06:00:00',
        'check_out_at' => '2026-10-23 06:00:00',
        'booking_type' => 'overnight',
        'hours' => 48,
        'guests' => 1,
        'nights' => 2,
        'total_amount' => 900,
        'status' => 'confirmed',
        'payment_status' => 'paid',
        'payment_method' => 'gcash',
        'checked_out_at' => '2026-10-23 06:00:00',
    ]);

    $response = $this->actingAs($user)->get(route('bookings.index'))
        ->assertOk()
        ->assertSee('class="booking-actions"', false)
        ->assertSee('class="booking-review-area"', false)
        ->assertSee('View details')
        ->assertSee('Leave a review');

    $document = new DOMDocument;
    @$document->loadHTML($response->getContent());
    $xpath = new DOMXPath($document);
    expect($xpath->query('//fieldset[contains(concat(" ", normalize-space(@class), " "), " review-rating ")]')->length)->toBe(4);

    foreach (['rating', 'cleanliness_rating', 'comfort_rating', 'value_rating'] as $name) {
        expect($xpath->query('//input[@type="radio" and @name="'.$name.'"]')->length)->toBe(5);
    }

    expect($xpath->query('//input[@type="radio" and @name="rating" and @required]')->length)->toBe(5)
        ->and($xpath->query('//input[@type="radio" and @name!="rating" and @required]')->length)->toBe(0)
        ->and($xpath->query('//select[@name="rating" or @name="cleanliness_rating" or @name="comfort_rating" or @name="value_rating"]')->length)->toBe(0);
});
