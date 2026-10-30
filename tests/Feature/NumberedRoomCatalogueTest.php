<?php

use App\Models\Room;
use Database\Seeders\RoomSeeder;

test('the room catalogue seeds and displays eighteen numbered rooms in groups of six', function () {
    $this->seed(RoomSeeder::class);

    $rooms = Room::where('is_active', true)->orderBy('id')->get();
    expect($rooms)->toHaveCount(18)
        ->and($rooms->pluck('name')->all())->toBe(array_map(fn (int $number): string => 'Room '.$number, range(1, 18)));

    $response = $this->get(route('rooms.index'))->assertOk();
    $response->assertSee('Show next six rooms')
        ->assertSee('Group 1 of 3');
    expect(substr_count($response->getContent(), 'data-room-page='))->toBe(3);
});

test('the rooms page keeps the finder compact and places the promotion after the first room row', function () {
    $this->seed(RoomSeeder::class);

    $this->get(route('rooms.index'))
        ->assertOk()
        ->assertSeeInOrder([
            '<h1>Find a room</h1>',
            '<p>Choose your dates and stay length.</p>',
            '<form class="filter-bar rooms-search" method="GET">',
            '<p class="result-count">',
            '<h3>Room 1</h3>',
            '<h3>Room 3</h3>',
            '<h2 id="rooms-ber-promo-title">Fan Room Solo—with free breakfast.</h2>',
            '<h3>Room 4</h3>',
        ], false);
});
