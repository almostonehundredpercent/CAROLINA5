<?php

use App\Models\Room;
use App\Models\User;
use Illuminate\Support\Facades\DB;

test('public pages advertise the Carolina logo as their browser favicon', function () {
    $this->get(route('home'))
        ->assertOk()
        ->assertSee('rel="icon" type="image/png" href="'.asset('images/carolina-favicon.png').'"', false);

    expect(file_exists(public_path('images/carolina-favicon.png')))->toBeTrue();
});

test('CAROL decision support advertises the Carolina logo as its browser favicon', function () {
    $admin = User::factory()->create(['is_admin' => true]);

    $this->actingAsStaff($admin)
        ->get(route('admin.decision-support'))
        ->assertOk()
        ->assertSee('rel="icon" type="image/png" href="'.asset('images/carolina-favicon.png').'"', false);
});

test('public header places a Book now button between Contact and the dark mode toggle', function () {
    $response = $this->get(route('home'))->assertOk();
    $navigationStart = strpos($response->getContent(), '<nav id="primary-navigation">');
    $navigationEnd = strpos($response->getContent(), '</nav>', $navigationStart);
    $navigation = substr($response->getContent(), $navigationStart, $navigationEnd - $navigationStart);

    $contactPosition = strpos($navigation, '>Contact</a>');
    $bookNowPosition = strpos($navigation, '>Book now</a>');
    $themeTogglePosition = strpos($navigation, '<button class="theme-toggle nav-theme-toggle"');

    expect($navigation)->toContain('class="nav-button nav-book-now" href="'.route('booking.start').'"')
        ->and($contactPosition)->not->toBeFalse()
        ->and($bookNowPosition)->not->toBeFalse()
        ->and($themeTogglePosition)->not->toBeFalse()
        ->and($contactPosition)->toBeLessThan($bookNowPosition)
        ->and($bookNowPosition)->toBeLessThan($themeTogglePosition);
});

test('signed-in header keeps Book now directly beside the dark mode toggle', function (array $attributes, bool $isAdmin) {
    $user = User::factory()->create($attributes);
    $response = $this->actingAs($user)->get(route('home'))->assertOk()->assertSee('My bookings');
    $navigationStart = strpos($response->getContent(), '<nav id="primary-navigation">');
    $navigationEnd = strpos($response->getContent(), '</nav>', $navigationStart);
    $navigation = substr($response->getContent(), $navigationStart, $navigationEnd - $navigationStart);
    $bookNowPosition = strpos($navigation, '>Book now</a>');
    $bookNowEnd = $bookNowPosition + strlen('>Book now</a>');
    $themeTogglePosition = strpos($navigation, '<button class="theme-toggle nav-theme-toggle"');

    expect(trim(substr($navigation, $bookNowEnd, $themeTogglePosition - $bookNowEnd)))->toBe('');

    if ($isAdmin) {
        $response->assertSee('>Admin</a>', false);
    }
})->with([
    'signed-in guest' => [['is_admin' => false, 'staff_role' => 'guest'], false],
    'administrator' => [['is_admin' => true, 'staff_role' => 'admin'], true],
]);

test('homepage renders its booking search and featured cards from one room query', function () {
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
        ->and($response->getContent())->toContain('class="home-booking-search"', 'name="check_in"', 'name="stay"', 'name="check_in_time"', 'name="guests"', 'name="sort"', 'Check availability')
        ->and($response->getContent())->not->toContain('home-room-picker')
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

    expect($xpath->evaluate('count(//head/link[@rel="preload"][@as="image"][@imagesrcset])'))->toBe(1.0)
        ->and($xpath->evaluate('string(//head/link[@rel="preload"][@as="image"]/@imagesrcset)'))->toContain('lobby-1-900.jpg', 'lobby-1-1800.jpg')
        ->and($xpath->evaluate('count(//picture[@class="brand-logo-picture"]/source[@type="image/webp"][@srcset])'))->toBe(2.0)
        ->and($xpath->evaluate('count(//head/link[contains(@href, "home-premium.css")])'))->toBe(1.0)
        ->and($xpath->evaluate('string(//div[contains(@class, "home-room-carousel")]//img[1]/@loading)'))->toBe('lazy')
        ->and($xpath->evaluate('string(//div[contains(@class, "home-room-carousel")]//img[1]/@fetchpriority)'))->not->toBe('high');
});

test('homepage hero rotates the three local Carolina photos without upscaled fake 4K variants', function () {
    $html = $this->get(route('home'))->assertOk()->getContent();
    $document = new DOMDocument;
    @$document->loadHTML('<?xml encoding="UTF-8"?>'.$html);
    $xpath = new DOMXPath($document);
    $slides = $xpath->query('//section[contains(concat(" ", normalize-space(@class), " "), " home-hero ")]//img[contains(concat(" ", normalize-space(@class), " "), " home-hero-slide ")]');

    expect($slides)->toHaveCount(3)
        ->and($xpath->evaluate('count(//section[contains(concat(" ", normalize-space(@class), " "), " home-hero ")]//*[contains(concat(" ", normalize-space(@class), " "), " home-hero-controls ")])'))->toBe(0.0);

    foreach (['lobby-1', 'lobby-2', 'lobby-3'] as $index => $name) {
        $image = getimagesize(public_path("images/home-hero/{$name}-1800.jpg"));
        expect($image[0])->toBe(1800)
            ->and($slides->item($index)->getAttribute('srcset'))->toContain("{$name}-900.jpg 900w", "{$name}-1800.jpg 1800w")
            ->and($slides->item($index)->getAttribute('srcset'))->not->toContain('-4k.jpg');
    }
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

test('locally hosted room cards offer WebP sources and keep their JPEG fallback', function () {
    Room::create([
        'name' => 'Local photo room',
        'slug' => 'local-photo-room',
        'description' => 'A test stay.',
        'room_type' => 'Standard',
        'beds' => 1,
        'guests' => 2,
        'price_per_night' => 450,
        'image_url' => '/images/rooms/room-7-angle-1.jpg',
        'is_active' => true,
    ]);

    foreach ([route('home'), route('rooms.index')] as $url) {
        $response = $this->get($url)->assertOk();
        $document = new DOMDocument;
        @$document->loadHTML('<?xml encoding="UTF-8"?>'.$response->getContent());
        $xpath = new DOMXPath($document);
        $image = $xpath->query('//img[@alt="Local photo room"]')->item(0);
        $source = $xpath->query('//picture/source[@type="image/webp" and contains(@srcset, "room-7-angle-1-480.webp")]')->item(0);

        expect($image)->not->toBeNull()
            ->and($image->getAttribute('src'))->toContain('/images/rooms/room-7-angle-1.jpg')
            ->and($source)->not->toBeNull()
            ->and($source->getAttribute('srcset'))->toContain('room-7-angle-1-480.webp', 'room-7-angle-1-800.webp');
    }
});

test('rooms page styles load before body content to prevent late page styling', function () {
    $html = $this->get(route('rooms.index'))->assertOk()->getContent();
    $bodyPosition = strpos($html, '<body>');
    $roomsStylesPosition = strpos($html, 'css/rooms-premium.css');
    $promoStylesPosition = strpos($html, 'css/promo-campaign.css');

    expect($bodyPosition)->not->toBeFalse()
        ->and($roomsStylesPosition)->not->toBeFalse()
        ->and($promoStylesPosition)->not->toBeFalse()
        ->and($roomsStylesPosition)->toBeLessThan($bodyPosition)
        ->and($promoStylesPosition)->toBeLessThan($bodyPosition);
});
