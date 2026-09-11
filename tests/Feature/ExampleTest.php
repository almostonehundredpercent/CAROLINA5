<?php

use App\Models\Booking;
use App\Models\Room;
use App\Models\RoomBlock;
use App\Models\User;
use Illuminate\Support\Facades\Mail;

function testRoom(): Room
{
    return Room::create(['name' => 'Test Room', 'slug' => 'test-room', 'room_type' => 'Standard', 'description' => 'A test room.', 'beds' => 1, 'guests' => 2, 'price_per_night' => 1000, 'is_active' => true, 'operational_status' => 'available']);
}

function confirmedBooking(Room $room, $start, $end): Booking
{
    return Booking::create(['room_id' => $room->id, 'guest_name' => 'Confirmed guest', 'guest_email' => 'guest@example.com', 'guest_phone' => '09171234567', 'check_in' => $start->toDateString(), 'check_out' => $end->toDateString(), 'check_in_at' => $start, 'check_out_at' => $end, 'guests' => 1, 'nights' => 1, 'total_amount' => 1000, 'payment_method' => 'cash', 'status' => 'confirmed']);
}

test('the public home page loads with a Carolina title', function () {
    $this->get('/')->assertOk()->assertSee('Carolina');
});

test('an administrator can sign in with a fresh session', function () {
    $admin = User::factory()->create(['is_admin' => true, 'password' => 'password']);
    $this->post(route('login.submit'), ['email' => $admin->email, 'password' => 'password'])
        ->assertRedirect(route('admin.dashboard'));
    $this->assertAuthenticatedAs($admin);
});

test('a reservation request stores exact times without blocking availability until confirmed', function () {
    Mail::fake();
    $room = testRoom();
    $checkIn = now()->addDays(3)->startOfDay();
    $this->post(route('bookings.store', $room), ['checkout_type' => 'guest', 'booking_type' => 'dates', 'check_in' => $checkIn->toDateString(), 'check_out' => $checkIn->copy()->addDays(2)->toDateString(), 'guests' => 2, 'children_count' => 1, 'pets_count' => 1, 'guest_name' => 'Test Guest', 'guest_email' => 'guest@example.com', 'guest_phone' => '09171234567', 'terms_accepted' => '1'])->assertRedirect()->assertSessionHasNoErrors();
    $booking = Booking::firstOrFail();
    expect($booking->status)->toBe('pending')->and($booking->hold_expires_at)->toBeNull()->and($booking->check_in_at->toDateString())->toBe($checkIn->toDateString())->and($booking->children_count)->toBe(1)->and($booking->pets_count)->toBe(1);
    $this->get(route('rooms.index', ['check_in' => $checkIn->toDateString(), 'check_out' => $checkIn->copy()->addDay()->toDateString()]))->assertOk()->assertSee('Test Room');
});

test('confirmed bookings and scheduled room blocks both prevent an overlap', function () {
    $room = testRoom();
    $start = now()->addDays(4)->setTime(10, 0);
    confirmedBooking($room, $start, $start->copy()->addHours(6));
    expect($room->bookings()->blocking()->overlapping($start->copy()->addHour(), $start->copy()->addHours(2))->exists())->toBeTrue();
    RoomBlock::create(['room_id' => $room->id, 'status' => 'maintenance', 'starts_at' => $start->copy()->addDay(), 'ends_at' => $start->copy()->addDay()->addHour(), 'notes' => 'Repair']);
    expect($room->blocks()->overlapping($start->copy()->addDay()->addMinutes(15), $start->copy()->addDay()->addMinutes(45))->exists())->toBeTrue();
});

test('staff cannot confirm a request that now conflicts with a block', function () {
    Mail::fake();
    $room = testRoom();
    $start = now()->addDays(5)->startOfDay();
    $request = Booking::create(['room_id' => $room->id, 'guest_name' => 'Request', 'guest_email' => 'request@example.com', 'guest_phone' => '09171234567', 'check_in' => $start->toDateString(), 'check_out' => $start->copy()->addDay()->toDateString(), 'check_in_at' => $start, 'check_out_at' => $start->copy()->addDay(), 'guests' => 1, 'nights' => 1, 'total_amount' => 1000, 'payment_method' => 'cash', 'status' => 'pending']);
    RoomBlock::create(['room_id' => $room->id, 'status' => 'cleaning', 'starts_at' => $start, 'ends_at' => $start->copy()->addHour()]);
    $admin = User::factory()->create(['is_admin' => true]);
    $this->actingAs($admin)->patch(route('admin.bookings.update', $request), ['status' => 'confirmed'])->assertSessionHasErrors('status');
    expect($request->fresh()->status)->toBe('pending');
});
