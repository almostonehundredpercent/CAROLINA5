<?php

use App\Models\Room;
use Illuminate\Support\Facades\DB;

test('public header places a Book now button between Contact and the dark mode toggle', function () {
    $response = $this->get(route('home'))->assertOk();
    $navigationStart = strpos($response->getContent(), '<nav id="primary-navigation">');
    $navigationEnd = strpos($response->getContent(), '</nav>', $navigationStart);
    $navigation = substr($response->getContent(), $navigationStart, $navigationEnd - $navigationStart);

    $contactPosition = strpos($navigation, '>Contact</a>');
    $bookNowPosition = strpos($navigation, '>Book now</a>');
    $themeTogglePosition = strpos($navigation, 'class="theme-toggle nav-theme-toggle"');

    expect($navigation)->toContain('class="nav-button" href="'.route('rooms.index').'"')
        ->and($contactPosition)->not->toBeFalse()
        ->and($bookNowPosition)->not->toBeFalse()
        ->and($themeTogglePosition)->not->toBeFalse()
        ->and($contactPosition)->toBeLessThan($bookNowPosition)
        ->and($bookNowPosition)->toBeLessThan($themeTogglePosition);
});

test('homepage renders its room picker and featured cards from one room query', function () {
    foreach ([700, 450, 900] as $index => $price) {
        Room::create([
            'name' => 'Test room '.($index + 1),
            'slug' => 'test-room-'.($index + 1),
            'description' => 'A test stay.',
            'room_type' => 'Standard',
            'beds' => 1,
            'guests' => 2,
            'price_per_night' => $price,
            'is_active' => true,
        ]);
    }

    $roomQueries = [];
    DB::listen(function ($query) use (&$roomQueries) {
        if (str_contains(strtolower($query->sql), 'from "rooms"')) {
            $roomQueries[] = $query->sql;
        }
    });

    $response = $this->get(route('home'))->assertOk();

    expect($roomQueries)->toHaveCount(1)
        ->and($response->viewData('availabilityRooms')->pluck('name')->all())
        ->toBe(['Test room 1', 'Test room 2', 'Test room 3'])
        ->and($response->viewData('featuredRooms')->pluck('name')->all())
        ->toBe(['Test room 2', 'Test room 1', 'Test room 3']);
});

test('homepage prioritizes its hero image and defers below-the-fold room cards', function () {
    Room::create([
        'name' => 'Test room',
        'slug' => 'test-room',
        'description' => 'A test stay.',
        'room_type' => 'Standard',
        'beds' => 1,
        'guests' => 2,
        'price_per_night' => 450,
        'image_url' => 'https://images.unsplash.com/photo-test?auto=format&fit=crop&w=1000&q=80',
        'is_active' => true,
    ]);

    $response = $this->get(route('home'))->assertOk();
    $document = new DOMDocument;
    @$document->loadHTML('<?xml encoding="UTF-8"?>'.$response->getContent());
    $xpath = new DOMXPath($document);

    expect($xpath->evaluate('count(//head/link[@rel="preload"][@as="image"][@media="(max-width: 800px)"])'))->toBe(1.0)
        ->and($xpath->evaluate('count(//head/link[@rel="preload"][@as="image"][@media="(min-width: 801px)"])'))->toBe(1.0)
        ->and($xpath->evaluate('count(//head/link[contains(@href, "home-premium.css")])'))->toBe(1.0)
        ->and($xpath->evaluate('string(//div[contains(@class, "home-room-carousel")]//img[1]/@loading)'))->toBe('lazy')
        ->and($xpath->evaluate('string(//div[contains(@class, "home-room-carousel")]//img[1]/@fetchpriority)'))->not->toBe('high');
});

test('room photos provide smaller responsive variants only for supported Unsplash URLs', function () {
    $photo = 'https://images.unsplash.com/photo-test?auto=format&fit=crop&w=1000&q=80';
    $room = Room::create([
        'name' => 'Photo room',
        'slug' => 'photo-room',
        'description' => 'A test stay.',
        'room_type' => 'Standard',
        'beds' => 1,
        'guests' => 2,
        'price_per_night' => 450,
        'image_url' => $photo,
        'is_active' => true,
    ]);

    expect($room->responsiveImageSrcset())->toContain('w=480', 'w=800', 'w=1000');
    $room->image_url = '/images/rooms/room-7-angle-1.jpg';
    expect($room->responsiveImageSrcset())->toBeNull();
    $room->image_url = $photo;
    $room->save();

    foreach ([route('home'), route('rooms.index')] as $url) {
        $response = $this->get($url)->assertOk();
        $document = new DOMDocument;
        @$document->loadHTML('<?xml encoding="UTF-8"?>'.$response->getContent());
        $image = (new DOMXPath($document))->query('//img[@alt="Photo room"]')->item(0);
        expect($image)->not->toBeNull()
            ->and($image->getAttribute('srcset'))->toContain('w=480', 'w=800', 'w=1000')
            ->and($image->getAttribute('sizes'))->not->toBeEmpty();
    }
});
