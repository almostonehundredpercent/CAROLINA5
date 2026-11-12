<?php

use App\Models\Room;

test('room details provide their room photo to the enlarge viewer', function () {
    $room = Room::create([
        'name' => 'Image viewer room',
        'slug' => 'image-viewer-room',
        'room_type' => 'Standard',
        'description' => 'A test room photo.',
        'image_url' => '/images/room-viewer-test.jpg',
        'beds' => 1,
        'guests' => 2,
        'price_per_night' => 450,
        'rental_hours' => 6,
        'is_active' => true,
    ]);

    $this->get(route('rooms.show', $room))
        ->assertOk()
        ->assertSee('id="room-gallery-data" type="application/json"', false)
        ->assertSee('"images":["\/images\/room-viewer-test.jpg"]', false)
        ->assertSee('room-gallery.css', false)
        ->assertSee('room-gallery.js', false);
});

test('room details provide a back link with the room catalogue as a safe fallback', function () {
    $room = Room::create([
        'name' => 'Back link room',
        'slug' => 'back-link-room',
        'room_type' => 'Standard',
        'description' => 'A room for testing its back navigation.',
        'image_url' => '/images/back-link-room.jpg',
        'beds' => 1,
        'guests' => 2,
        'price_per_night' => 450,
        'rental_hours' => 6,
        'is_active' => true,
    ]);

    $this->get(route('rooms.show', $room))
        ->assertOk()
        ->assertSee('data-site-back', false)
        ->assertSee('href="'.route('rooms.index').'"', false)
        ->assertSee('Back to previous page');
});
