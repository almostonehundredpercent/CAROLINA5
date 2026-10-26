<?php

use App\Models\Room;

test('room prices can be sorted either way without losing the guest stay search', function () {
    foreach ([['Premium room', 900], ['Budget room', 450], ['Comfort room', 699]] as [$name, $price]) {
        Room::create([
            'name' => $name,
            'slug' => str($name)->slug()->toString(),
            'description' => 'Price sorting test room.',
            'room_type' => 'Standard',
            'beds' => 1,
            'guests' => 2,
            'price_per_night' => $price,
            'is_active' => true,
        ]);
    }

    $search = ['check_in' => now()->addDays(4)->toDateString(), 'stay' => '6', 'check_in_time' => '14:00'];
    $lowestFirst = $this->get(route('rooms.index', $search + ['sort' => 'price_asc']))->assertOk();
    $lowestFirst->assertSee('Sort by price')->assertSee('Lowest first')->assertSee('name="sort"', false);
    expect($lowestFirst->viewData('rooms')->pluck('name')->all())
        ->toBe(['Budget room', 'Comfort room', 'Premium room'])
        ->and($lowestFirst->viewData('filters')['sort'])->toBe('price_asc')
        ->and($lowestFirst->viewData('filters')['check_in_time'])->toBe('14:00');

    $highestFirst = $this->get(route('rooms.index', $search + ['sort' => 'price_desc']))->assertOk();
    $highestFirst->assertSee('Highest first');
    expect($highestFirst->viewData('rooms')->pluck('name')->all())
        ->toBe(['Premium room', 'Comfort room', 'Budget room']);
});
