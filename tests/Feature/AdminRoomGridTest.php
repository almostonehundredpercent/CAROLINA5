<?php

use App\Models\Room;
use App\Models\User;
use Database\Seeders\RoomSeeder;

test('admin room board shows every active room together without group navigation', function () {
    $this->seed(RoomSeeder::class);

    Room::create([
        'name' => 'Room 19',
        'slug' => 'room-19',
        'room_type' => 'Fan room',
        'description' => 'Additional room for the board.',
        'beds' => 1,
        'guests' => 1,
        'price_per_night' => 450,
        'is_active' => true,
        'operational_status' => 'available',
    ]);

    $admin = User::factory()->create(['is_admin' => true]);
    $html = $this->actingAsStaff($admin)
        ->get(route('admin.rooms'))
        ->assertOk()
        ->getContent();

    preg_match_all('/<button class="room-slot [^"]+"[^>]*>/s', $html, $matches);
    $slots = $matches[0];

    expect($slots)->toHaveCount(19);
    foreach ($slots as $index => $slot) {
        expect($slot)->toContain('data-room-target="'.($index + 1).'"')
            ->not->toMatch('/\shidden(?:\s|>|=)/');
    }

    expect($html)->not->toContain('id="rooms-previous-page"')
        ->not->toContain('id="rooms-next-page"');
    expect($html)->toContain('id="room-detail-19"')
        ->toContain('data-room-detail="19"');
});
