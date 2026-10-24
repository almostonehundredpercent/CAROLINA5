<?php

use App\Models\Booking;
use App\Models\Review;
use App\Models\Room;
use App\Models\User;

test('room cards show only published scores and new rooms clearly show there are no reviews yet', function () {
    $reviewedRoom = Room::create([
        'name' => 'Room One', 'slug' => 'room-one-review-test', 'room_type' => 'Fan room',
        'description' => 'A private fan room.', 'beds' => 1, 'guests' => 1,
        'price_per_night' => 450, 'is_active' => true,
    ]);
    $newRoom = Room::create([
        'name' => 'Room Two', 'slug' => 'room-two-review-test', 'room_type' => 'Fan room',
        'description' => 'A second private fan room.', 'beds' => 1, 'guests' => 1,
        'price_per_night' => 500, 'is_active' => true,
    ]);
    $guest = User::factory()->create();

    $createReview = function (Room $room, int $rating, string $status, string $comment) use ($guest) {
        $booking = Booking::create([
            'user_id' => $guest->id,
            'room_id' => $room->id,
            'check_in' => '2026-11-01',
            'check_out' => '2026-11-02',
            'guests' => 1,
            'nights' => 1,
            'total_amount' => $room->price_per_night,
            'payment_method' => 'cash',
            'status' => 'confirmed',
        ]);

        Review::create([
            'booking_id' => $booking->id,
            'room_id' => $room->id,
            'user_id' => $guest->id,
            'guest_name' => $guest->name,
            'rating' => $rating,
            'comment' => $comment,
            'status' => $status,
            'approved_at' => $status === 'approved' ? now() : null,
        ]);
    };

    $createReview($reviewedRoom, 4, 'approved', 'A lovely quiet stay.');
    $createReview($newRoom, 5, 'pending', 'This review is not public yet.');

    foreach ([route('rooms.index'), route('home')] as $url) {
        $this->get($url)
            ->assertOk()
            ->assertSee('Rated 4.0 out of 5 from 1 review', false)
            ->assertSee('aria-label="No reviews yet"', false);
    }

    $this->get(route('rooms.show', $reviewedRoom))
        ->assertOk()
        ->assertSee('A lovely quiet stay.')
        ->assertDontSee('This review is not public yet.');

    $this->get(route('rooms.show', $newRoom))
        ->assertOk()
        ->assertSee('No verified reviews yet')
        ->assertDontSee('This review is not public yet.');
});
