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

test('the rooms page places the promotion between the booking finder and room results', function () {
    $this->seed(RoomSeeder::class);

    $response = $this->get(route('rooms.index'))
        ->assertOk()
        ->assertSeeInOrder([
            '<h1>Find a room</h1>',
            '<p>Choose your dates and stay length.</p>',
            '<form class="filter-bar rooms-search" method="GET">',
            '<h2 id="rooms-ber-promo-title">Fan Room Solo—with free breakfast.</h2>',
            '<p class="result-count">',
            '<h3>Room 1</h3>',
        ], false);

    $document = new DOMDocument;
    @$document->loadHTML($response->getContent());
    $xpath = new DOMXPath($document);
    $promotion = $xpath->query('//section[contains(concat(" ", normalize-space(@class), " "), " rooms-results ")]/section[contains(concat(" ", normalize-space(@class), " "), " ber-promo-rooms ")]')->item(0);

    expect($promotion)->not->toBeNull()
        ->and($xpath->query('//section[contains(concat(" ", normalize-space(@class), " "), " room-grid ")]//section[contains(concat(" ", normalize-space(@class), " "), " ber-promo-rooms ")]')->length)->toBe(0);
});
