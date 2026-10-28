<?php

use App\Models\Booking;
use App\Models\Room;
use App\Models\RoomBlock;
use App\Models\User;

function statusTestRoom(string $name): Room
{
    return Room::create([
        'name' => $name,
        'slug' => str($name)->slug()->toString(),
        'room_type' => 'Standard',
        'description' => 'Room status test.',
        'beds' => 1,
        'guests' => 2,
        'price_per_night' => 450,
        'is_active' => true,
        'operational_status' => 'available',
    ]);
}

function statusTestBooking(Room $room, $start, $end): void
{
    Booking::create([
        'room_id' => $room->id,
        'guest_name' => 'Status Guest',
        'guest_email' => 'status@example.com',
        'check_in' => $start->toDateString(),
        'check_out' => $end->toDateString(),
        'check_in_at' => $start,
        'check_out_at' => $end,
        'guests' => 1,
        'nights' => 1,
        'total_amount' => 450,
        'payment_method' => 'cash',
        'status' => 'confirmed',
    ]);
}

function statusTestPropertyNow(): Carbon\Carbon
{
    $propertyClock = Carbon\Carbon::now('Asia/Manila');

    return Carbon\Carbon::parse($propertyClock->format('Y-m-d H:i:s'), 'UTC');
}

test('guest room results show booked cleaning and maintenance rooms as unavailable for the selected stay', function () {
    $start = now()->addDays(3)->setTime(13, 0);
    $end = $start->copy()->addHours(6);
    $booked = statusTestRoom('Booked Room');
    $cleaning = statusTestRoom('Cleaning Room');
    $maintenance = statusTestRoom('Maintenance Room');
    $free = statusTestRoom('Free Room');
    statusTestBooking($booked, $start, $end);
    foreach (['cleaning' => $cleaning, 'maintenance' => $maintenance] as $status => $room) {
        RoomBlock::create(['room_id' => $room->id, 'status' => $status, 'starts_at' => $start, 'ends_at' => $end]);
    }

    $response = $this->get(route('rooms.index', [
        'check_in' => $start->toDateString(), 'check_in_time' => '13:00', 'stay' => '6',
    ]))->assertOk();
    $rooms = $response->viewData('rooms')->keyBy('name');

    expect($rooms)->toHaveCount(4)
        ->and($rooms['Booked Room']->availability_reason)->toBe('Booked')
        ->and($rooms['Cleaning Room']->availability_reason)->toBe('Cleaning')
        ->and($rooms['Maintenance Room']->availability_reason)->toBe('Maintenance')
        ->and($rooms['Free Room']->availability_reason)->toBeNull();
    $response->assertSee('Unavailable')->assertSee('Free Room');

    $this->get(route('rooms.show', ['room' => $booked, 'check_in' => $start->toDateString(), 'check_in_time' => '13:00', 'stay' => '6']))
        ->assertOk()->assertSee('Unavailable for this stay')->assertDontSee('Continue as guest');

    // A future reservation must not make the room appear occupied right now.
    expect($this->get(route('rooms.index'))->viewData('rooms')->firstWhere('id', $booked->id)->availability_reason)->toBeNull();
});

test('room detail shows the next full-stay opening after consecutive reservations and cleaning', function () {
    $room = statusTestRoom('Next Opening Room');
    $room->update(['rental_hours' => 6]);
    $start = now()->addDays(3)->setTime(13, 0);
    statusTestBooking($room, $start, $start->copy()->addHours(6));
    RoomBlock::create([
        'room_id' => $room->id, 'status' => 'cleaning',
        'starts_at' => $start->copy()->addHours(6), 'ends_at' => $start->copy()->addHours(7)->addMinutes(20),
    ]);
    statusTestBooking($room, $start->copy()->addHours(8), $start->copy()->addHours(14));

    $response = $this->get(route('rooms.show', [
        'room' => $room, 'check_in' => $start->toDateString(), 'check_in_time' => '13:00', 'stay' => '6',
    ]))->assertOk();

    $response->assertSee('Next available for a 6-hour stay')
        ->assertSee($start->copy()->addDay()->setTime(6, 0)->format('M j, Y'))
        ->assertSee('6:00 AM')
        ->assertDontSee('Status Guest');
});

test('room detail does not promise an opening for an unscheduled manual closure', function () {
    $room = statusTestRoom('Closed Room');
    $room->update(['operational_status' => 'maintenance', 'operational_until' => null]);

    $this->get(route('rooms.show', $room))->assertOk()
        ->assertSee('Next available time has not been scheduled')
        ->assertDontSee('Continue as guest');
});

test('room detail uses its normal stay length when showing the next opening right now', function () {
    $room = statusTestRoom('Current Opening Room');
    $room->update(['rental_hours' => 22]);
    $propertyNow = statusTestPropertyNow();
    $start = $propertyNow->copy()->subHour();
    $end = $propertyNow->copy()->addHours(2)->startOfHour();
    statusTestBooking($room, $start, $end);
    $expected = $end->copy();
    if ($expected->hour < 6) {
        $expected->setTime(6, 0);
    }
    if ($expected->hour > 23) {
        $expected->addDay()->setTime(6, 0);
    }

    $this->get(route('rooms.show', $room))->assertOk()
        ->assertSee('Unavailable right now')
        ->assertSee('Next available for a 22-hour stay')
        ->assertSee($expected->format('M j, Y').' at '.$expected->format('g:i A'));
});

test('rooms sold by the night show a bookable check-in date rather than an hourly opening', function () {
    $room = statusTestRoom('Nightly Opening Room');
    $start = now()->subHour();
    $end = now()->addDay()->setTime(10, 0);
    statusTestBooking($room, $start, $end);

    $this->get(route('rooms.show', $room))->assertOk()
        ->assertSee('Next available for a 1-night stay')
        ->assertSee($end->copy()->addDay()->startOfDay()->format('M j, Y').' at 12:00 AM');
});

test('admin room board marks booked cleaning and maintenance rooms unavailable', function () {
    $propertyNow = statusTestPropertyNow();
    $start = $propertyNow->copy()->subHour();
    $end = $propertyNow->copy()->addHours(6);
    $booked = statusTestRoom('Booked Room');
    $cleaning = statusTestRoom('Cleaning Room');
    $maintenance = statusTestRoom('Maintenance Room');
    statusTestRoom('Free Room');
    statusTestBooking($booked, $start, $end);
    foreach (['cleaning' => $cleaning, 'maintenance' => $maintenance] as $status => $room) {
        RoomBlock::create(['room_id' => $room->id, 'status' => $status, 'starts_at' => $start, 'ends_at' => $end]);
    }

    $admin = User::factory()->create(['is_admin' => true]);
    $html = $this->actingAsStaff($admin)->get(route('admin.rooms'))->assertOk()->getContent();
    foreach (['Booked Room' => 'Occupied', 'Cleaning Room' => 'Cleaning', 'Maintenance Room' => 'Maintenance'] as $name => $status) {
        expect($html)->toMatch('/class="room-slot not-available"[^>]*>.*?'.preg_quote($status, '/').'.*?'.preg_quote($name, '/').'/s');
    }
    expect($html)->toMatch('/class="room-slot available"[^>]*>.*?Available.*?Free Room/s');
});

test('admin room board labels a room with an upcoming booking available now', function () {
    $room = statusTestRoom('Upcoming Booked Room');
    $start = now()->addDay()->setTime(13, 0);
    statusTestBooking($room, $start, $start->copy()->addHours(6));
    $admin = User::factory()->create(['is_admin' => true]);

    $this->actingAsStaff($admin)->get(route('admin.rooms'))->assertOk()
        ->assertSee('Available</span>', false);
});

test('guest homepage and housekeeping board identify rooms blocked at the current time', function () {
    $propertyNow = statusTestPropertyNow();
    $start = $propertyNow->copy()->subHour();
    $end = $propertyNow->copy()->addHours(3);
    $booked = statusTestRoom('Current Guest Room');
    $cleaning = statusTestRoom('Current Cleaning Room');
    $maintenance = statusTestRoom('Current Maintenance Room');
    statusTestBooking($booked, $start, $end);
    foreach (['cleaning' => $cleaning, 'maintenance' => $maintenance] as $status => $room) {
        RoomBlock::create(['room_id' => $room->id, 'status' => $status, 'starts_at' => $start, 'ends_at' => $end]);
    }

    $homeRooms = $this->get(route('home'))->assertOk()->assertSee('Unavailable now')->viewData('availabilityRooms')->keyBy('name');
    expect($homeRooms['Current Guest Room']->availability_reason)->toBe('Booked')
        ->and($homeRooms['Current Cleaning Room']->availability_reason)->toBe('Cleaning')
        ->and($homeRooms['Current Maintenance Room']->availability_reason)->toBe('Maintenance');

    $housekeeping = User::factory()->create(['staff_role' => 'housekeeping']);
    $this->actingAsStaff($housekeeping)->get(route('admin.rooms'))->assertOk()
        ->assertSee('Unavailable · Cleaning')->assertSee('Unavailable · Maintenance')->assertSee('Unavailable · Occupied')
        ->assertSee('Cleaning in progress')->assertSee('Guest stay in progress');
});
