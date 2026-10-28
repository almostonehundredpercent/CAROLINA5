<?php

use App\Models\Booking;
use App\Models\Room;
use App\Models\RoomBlock;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;

function availabilityTestRoom(string $name = 'Availability Test Room'): Room
{
    return Room::create([
        'name' => $name,
        'slug' => Str::slug($name),
        'room_type' => 'Standard',
        'description' => 'A room used to test inventory protection.',
        'beds' => 1,
        'guests' => 2,
        'price_per_night' => 1000,
        'is_active' => true,
        'operational_status' => 'available',
    ]);
}

test('every room card exposes its own booked-date calendar', function () {
    $rooms = collect([
        availabilityTestRoom('Availability Room One'),
        availabilityTestRoom('Availability Room Two'),
    ]);

    $html = $this->get(route('rooms.index'))->assertOk()->getContent();

    expect(substr_count($html, 'class="room-card-availability"'))->toBe($rooms->count());
    foreach ($rooms as $room) {
        expect($html)->toContain('data-availability-url="'.route('rooms.availability', $room).'"');
    }
});

function availabilityTestBooking(Room $room, Carbon $start, Carbon $end): Booking
{
    return Booking::create([
        'room_id' => $room->id,
        'guest_name' => 'Reserved guest',
        'guest_email' => 'reserved@example.com',
        'guest_phone' => '09171234567',
        'check_in' => $start->toDateString(),
        'check_out' => $end->toDateString(),
        'check_in_at' => $start,
        'check_out_at' => $end,
        'guests' => 1,
        'nights' => 1,
        'total_amount' => 1000,
        'payment_method' => 'cash',
        'status' => 'confirmed',
    ]);
}

test('room operation scheduling rejects a reservation overlap', function () {
    $room = availabilityTestRoom();
    $admin = User::factory()->create(['is_admin' => true]);
    $start = now()->addDays(4)->setTime(10, 0);
    availabilityTestBooking($room, $start, $start->copy()->addHours(6));
    $this->actingAsStaff($admin)
        ->patch(route('admin.rooms.status', $room), [
            'operational_status' => 'maintenance',
            'operational_starts_at' => $start->copy()->addHour()->format('Y-m-d H:i'),
            'operational_until' => $start->copy()->addHours(2)->format('Y-m-d H:i'),
            'notes' => 'Overlapping repair',
        ])
        ->assertSessionHasErrors('operational_until');

    expect(RoomBlock::where('room_id', $room->id)->exists())->toBeFalse();
});

test('room operation scheduling rejects an overlapping room block', function () {
    $room = availabilityTestRoom();
    $admin = User::factory()->create(['is_admin' => true]);
    $start = now()->addDays(4)->setTime(10, 0);
    RoomBlock::create([
        'room_id' => $room->id,
        'status' => 'cleaning',
        'starts_at' => $start,
        'ends_at' => $start->copy()->addHours(2),
    ]);

    $this->actingAsStaff($admin)
        ->patch(route('admin.rooms.status', $room), [
            'operational_status' => 'maintenance',
            'operational_starts_at' => $start->copy()->addHour()->format('Y-m-d H:i'),
            'operational_until' => $start->copy()->addHours(3)->format('Y-m-d H:i'),
        ])
        ->assertSessionHasErrors('operational_until');

    expect(RoomBlock::where('room_id', $room->id)->count())->toBe(1);
});

test('hourly booking picker names the exact reserved window for its selected date', function () {
    $this->travelTo(Carbon::parse('2026-09-29 01:00:00', 'UTC'));
    $room = availabilityTestRoom();
    $room->update(['rental_hours' => 6]);
    $start = Carbon::parse('2026-09-29 13:00:00', 'Asia/Manila');
    availabilityTestBooking($room, $start, $start->copy()->addHours(6));

    $response = $this->get(route('bookings.create', ['room' => $room, 'guest' => 1]));
    $response
        ->assertOk()
        ->assertSee('Booked times on Sep 29')
        ->assertSee('1 PM – 7 PM');

    $this->get(route('rooms.availability', [
        'room' => $room,
        'start' => '2026-09-29T14:00:00Z',
        'end' => '2026-09-29T20:00:00Z',
    ]))->assertJson(['available' => false]);
});

test('hourly bookings align property wall time with availability checks', function () {
    Mail::fake();
    $this->travelTo(Carbon::parse('2026-09-29 01:00:00', 'UTC'));
    $room = availabilityTestRoom();
    $room->update(['rental_hours' => 6]);

    $this->post(route('bookings.store', $room), [
        'checkout_type' => 'guest',
        'booking_type' => 'hourly',
        'hourly_date' => '2026-09-29',
        'check_in_time' => '13:00',
        'hours' => 6,
        'guests' => 1,
        'guest_name' => 'Local Time Guest',
        'guest_email' => 'localtime@example.com',
        'guest_phone' => '09171234567',
        'terms_accepted' => '1',
        'submission_token' => (string) Str::uuid(),
    ])->assertSessionHasNoErrors();

    $booking = Booking::where('guest_email', 'localtime@example.com')->firstOrFail();
    expect($booking->check_in_at->utc()->format('Y-m-d H:i'))->toBe('2026-09-29 13:00');

    $this->get(route('rooms.availability', [
        'room' => $room,
        'start' => '2026-09-29T14:00:00Z',
        'end' => '2026-09-29T20:00:00Z',
    ]))->assertJson(['available' => false]);
});

test('availability rejects past check-in dates and times using property time', function () {
    $this->travelTo(Carbon::parse('2026-10-28 12:00:00', 'UTC'));
    $room = availabilityTestRoom();

    $this->get(route('rooms.availability', [
        'room' => $room,
        'start' => '2026-10-27T00:00:00Z',
        'end' => '2026-10-28T00:00:00Z',
        'booking_type' => 'dates',
    ]))->assertJson(['available' => false, 'reason' => 'past']);

    $this->get(route('rooms.availability', [
        'room' => $room,
        'start' => '2026-10-28T18:00:00Z',
        'end' => '2026-10-28T21:00:00Z',
        'booking_type' => 'hourly',
    ]))->assertJson(['available' => false, 'reason' => 'past']);

    $this->get(route('rooms.availability', [
        'room' => $room,
        'start' => '2026-10-28T00:00:00Z',
        'end' => '2026-10-29T00:00:00Z',
        'booking_type' => 'dates',
    ]))->assertJson(['available' => true]);
});

test('booking submission rejects an hourly check-in time that has passed today', function () {
    Mail::fake();
    $this->travelTo(Carbon::parse('2026-10-28 12:00:00', 'UTC'));
    $room = availabilityTestRoom();
    $room->update(['rental_hours' => 6]);

    $this->post(route('bookings.store', $room), [
        'checkout_type' => 'guest',
        'booking_type' => 'hourly',
        'hourly_date' => '2026-10-28',
        'check_in_time' => '18:00',
        'hours' => 6,
        'guests' => 1,
        'guest_name' => 'Past Time Guest',
        'guest_email' => 'pasttime@example.com',
        'guest_phone' => '09171234567',
        'terms_accepted' => '1',
        'submission_token' => (string) Str::uuid(),
    ])->assertSessionHasErrors('check_in_time');

    expect(Booking::where('guest_email', 'pasttime@example.com')->exists())->toBeFalse();
});

test('booking submission rejects a check-in date that has already passed', function () {
    Mail::fake();
    $this->travelTo(Carbon::parse('2026-10-28 12:00:00', 'UTC'));
    $room = availabilityTestRoom();

    $this->post(route('bookings.store', $room), [
        'checkout_type' => 'guest',
        'booking_type' => 'dates',
        'check_in' => '2026-10-27',
        'check_out' => '2026-10-29',
        'guests' => 1,
        'guest_name' => 'Past Date Guest',
        'guest_email' => 'pastdate@example.com',
        'guest_phone' => '09171234567',
        'terms_accepted' => '1',
        'submission_token' => (string) Str::uuid(),
    ])->assertSessionHasErrors('check_in');

    expect(Booking::where('guest_email', 'pastdate@example.com')->exists())->toBeFalse();
});
