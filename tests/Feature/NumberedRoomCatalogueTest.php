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
