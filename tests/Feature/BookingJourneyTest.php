<?php

use App\Models\Booking;
use App\Models\Room;
use App\Models\User;
use Carbon\Carbon;

function journeyRoom(): Room
{
    return Room::create(['name' => 'Journey room', 'slug' => 'journey-room', 'room_type' => 'Standard', 'description' => 'Test room', 'beds' => 1, 'guests' => 2, 'price_per_night' => 450, 'rental_hours' => 6, 'is_active' => true, 'operational_status' => 'available']);
}

function journeyDocument(string $html): DOMXPath
{
    $document = new DOMDocument;
    @$document->loadHTML('<?xml encoding="UTF-8">'.$html);

    return new DOMXPath($document);
}

test('room search choices survive the detail and guest booking journey', function () {
    $this->travelTo(Carbon::parse('2026-10-03 01:00:00', 'UTC'));
    $room = journeyRoom();
    $choices = ['check_in' => '2026-10-05', 'stay' => '6', 'check_in_time' => '13:00', 'guests' => '2'];
    $search = $this->get(route('rooms.index', $choices))->assertOk();
    $detailUrl = journeyDocument($search->getContent())->evaluate('string(//div[contains(@class,"rooms-card-footer")]/a/@href)');
    parse_str(parse_url($detailUrl, PHP_URL_QUERY) ?? '', $detailQuery);
    expect($detailQuery)->toBe($choices);
    $detail = $this->get($detailUrl)->assertOk();
    $bookingUrl = journeyDocument($detail->getContent())->evaluate('string(//div[@class="reservation-actions"]/a[1]/@href)');
    $form = $this->get($bookingUrl)->assertOk();
    $document = journeyDocument($form->getContent());
    expect($document->evaluate('string(//input[@name="hourly_date"]/@value)'))->toBe('2026-10-05')
        ->and($document->evaluate('string(//input[@name="check_in_time"]/@value)'))->toBe('13:00')
        ->and($document->evaluate('string(//input[@name="hours"]/@value)'))->toBe('6')
        ->and($document->evaluate('string(//input[@name="guests"]/@value)'))->toBe('2');
});

test('the hourly field and its first summary use Manila today before UTC midnight', function () {
    $this->travelTo(Carbon::parse('2026-10-02 18:00:00', 'UTC'));
    $room = journeyRoom();
    $response = $this->get(route('bookings.create', ['room' => $room, 'guest' => 1]))->assertOk();
    expect(journeyDocument($response->getContent())->evaluate('string(//input[@name="hourly_date"]/@value)'))->toBe('2026-10-03');
    $response->assertSee('Booked times on Oct 3');
});

test('signing in from a room returns to the selected booking', function () {
    $this->travelTo(Carbon::parse('2026-10-03 01:00:00', 'UTC'));
    $room = journeyRoom();
    $user = User::factory()->create(['staff_role' => 'guest', 'is_admin' => false, 'password' => 'password']);
    $choices = ['check_in' => '2026-10-05', 'stay' => '6', 'check_in_time' => '13:00', 'guests' => '2'];
    $detail = $this->get(route('rooms.show', ['room' => $room] + $choices))->assertOk();
    $loginUrl = journeyDocument($detail->getContent())->evaluate('string(//div[@class="reservation-actions"]/a[2]/@href)');
    $this->get($loginUrl)->assertOk();
    $this->post(route('login.submit'), ['email' => $user->email, 'password' => 'password'])
        ->assertRedirect(route('bookings.create', ['room' => $room] + $choices));
});

test('invalid search values cannot prefill an invalid booking', function () {
    $this->travelTo(Carbon::parse('2026-10-03 01:00:00', 'UTC'));
    $room = journeyRoom();
    $response = $this->get(route('bookings.create', ['room' => $room, 'guest' => 1, 'check_in' => '2026-02-31', 'stay' => '-100', 'check_in_time' => '99:99', 'guests' => '999']))->assertOk();
    $document = journeyDocument($response->getContent());
    expect($document->evaluate('string(//input[@name="hourly_date"]/@value)'))->toBe('2026-10-03')
        ->and($document->evaluate('string(//input[@name="hours"]/@value)'))->toBe('6')
        ->and($document->evaluate('string(//input[@name="guests"]/@value)'))->toBe('1');
});

test('a searched full day is not silently changed into a short room package', function () {
    $this->travelTo(Carbon::parse('2026-10-03 01:00:00', 'UTC'));
    $room = journeyRoom();
    $this->get(route('bookings.create', ['room' => $room, 'guest' => 1, 'stay' => 'day']))
        ->assertOk()->assertSee('The searched duration is not offered for this room.');
});

test('monthly search uses the same thirty day duration as the booking package', function () {
    $this->travelTo(Carbon::parse('2026-10-03 01:00:00', 'UTC'));
    $room = journeyRoom();
    Booking::create(['room_id' => $room->id, 'guest_name' => 'Future guest', 'guest_email' => 'future@example.test', 'payment_method' => 'cash', 'status' => 'confirmed', 'guests' => 1, 'nights' => 1, 'total_amount' => 450, 'check_in' => '2026-11-04', 'check_out' => '2026-11-04', 'check_in_at' => '2026-11-04 13:00:00', 'check_out_at' => '2026-11-04 19:00:00']);
    $this->get(route('rooms.index', ['check_in' => '2026-10-05', 'stay' => 'month', 'check_in_time' => '13:00']))
        ->assertOk()->assertSee($room->name);
});

test('search defaults travel with a date only search link', function () {
    $this->travelTo(Carbon::parse('2026-10-03 01:00:00', 'UTC'));
    journeyRoom();
    $response = $this->get(route('rooms.index', ['check_in' => '2026-10-05']))->assertOk();
    $detailUrl = journeyDocument($response->getContent())->evaluate('string(//div[contains(@class,"rooms-card-footer")]/a/@href)');
    parse_str(parse_url($detailUrl, PHP_URL_QUERY) ?? '', $query);
    expect($query)->toBe(['check_in' => '2026-10-05', 'stay' => 'day', 'check_in_time' => '12:00']);
});

test('an explicitly selected date booking mode survives sign in', function () {
    $this->travelTo(Carbon::parse('2026-10-03 01:00:00', 'UTC'));
    $room = journeyRoom();
    $room->update(['rental_hours' => null]);
    $user = User::factory()->create(['staff_role' => 'guest', 'is_admin' => false, 'password' => 'password']);
    $choices = ['check_in' => '2026-10-05', 'stay' => 'day', 'mode' => 'dates'];
    $response = $this->get(route('bookings.create', ['room' => $room, 'guest' => 1] + $choices))->assertOk();
    $loginUrl = journeyDocument($response->getContent())->evaluate('string(//div[@class="checkout-choice"]/a/@href)');
    expect($loginUrl)->toContain('mode=dates');
    $this->get($loginUrl)->assertOk();
    $this->post(route('login.submit'), ['email' => $user->email, 'password' => 'password'])
        ->assertRedirect(route('bookings.create', ['room' => $room] + $choices));
});

test('native fallback controls agree with the selected stay values', function () {
    $this->travelTo(Carbon::parse('2026-10-03 01:00:00', 'UTC'));
    $room = journeyRoom();
    $room->update(['rental_hours' => null]);
    $response = $this->get(route('bookings.create', ['room' => $room, 'guest' => 1, 'check_in' => '2026-10-05', 'stay' => '12', 'check_in_time' => '13:00', 'guests' => 2]))->assertOk();
    $fallback = journeyDocument($response->getContent());
    expect($fallback->evaluate('string(//noscript//input[@name="hourly_date"]/@value)'))->toBe('2026-10-05')
        ->and($fallback->evaluate('string(//noscript//input[@name="check_in_time"]/@value)'))->toBe('13:00')
        ->and($fallback->evaluate('string(//noscript//select[@name="hours"]/option[@selected]/@value)'))->toBe('12');
});
