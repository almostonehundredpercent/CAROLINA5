<?php

use App\Mail\BookingConfirmation;
use App\Mail\NewBookingRequest;
use App\Models\Booking;
use App\Models\Room;
use App\Models\RoomBlock;
use App\Models\User;
use App\Notifications\QueuedResetPassword;
use App\Notifications\QueuedVerifyEmail;
use Illuminate\Notifications\SendQueuedNotifications;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Str;

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

test('legacy date-only stays block hourly arrivals but allow checkout boundaries', function () {
    $room = testRoom();
    $day = now()->addDays(4)->startOfDay();
    $booking = confirmedBooking($room, $day, $day->copy()->addDay());
    $booking->update(['check_in_at' => null, 'check_out_at' => null]);
    expect($room->bookings()->blocking()->overlapping($day->copy()->addHours(6), $day->copy()->addHours(12))->exists())->toBeTrue();
    expect($room->bookings()->blocking()->overlapping($day->copy()->subDay(), $day)->exists())->toBeFalse();
    expect($room->bookings()->blocking()->overlapping($day->copy()->addDay(), $day->copy()->addDays(2))->exists())->toBeFalse();
});

test('an administrator can sign in with a fresh session', function () {
    $admin = User::factory()->create(['is_admin' => true, 'password' => 'password']);
    $this->post(route('login.submit'), ['email' => $admin->email, 'password' => 'password'])
        ->assertRedirect(route('admin.frontdesk'));
    $this->assertAuthenticatedAs($admin);
});

test('a new account receives an email verification notification', function () {
    Notification::fake();

    $this->post(route('register.submit'), [
        'name' => 'New Carolina Guest',
        'email' => 'new.guest@example.com',
        'password' => 'password123',
        'password_confirmation' => 'password123',
    ])->assertRedirect(route('verification.notice'));

    $user = User::where('email', 'new.guest@example.com')->firstOrFail();
    Notification::assertSentTo($user, QueuedVerifyEmail::class);
});

test('custom-domain email addresses are accepted consistently', function () {
    Queue::fake();
    $email = 'DKJLVNSRSUNUWVKJMZ@KJKPC.NET';

    $this->post(route('register.submit'), [
        'name' => 'Custom Domain Guest', 'email' => $email,
        'password' => 'password123', 'password_confirmation' => 'password123',
    ])->assertRedirect(route('verification.notice'))->assertSessionHasNoErrors();

    $user = User::where('email', 'dkjlvnsrsunuwvkjmz@kjkpc.net')->firstOrFail();
    expect($user->email)->toBe('dkjlvnsrsunuwvkjmz@kjkpc.net');
    $this->post(route('password.email'), ['email' => $email])->assertRedirect()->assertSessionHasNoErrors();
    $user->sendPasswordResetNotification('test-token');
    Queue::assertPushed(SendQueuedNotifications::class,
        fn ($job) => $job->notification instanceof QueuedResetPassword);
});

test('an email copied with an escaped at sign is accepted', function () {
    Queue::fake();

    $this->post(route('register.submit'), [
        'name' => 'Copied Address Guest',
        'email' => 'dkjlvnsrsunuwvkjmz\\@kjkpc.net',
        'password' => 'password123',
        'password_confirmation' => 'password123',
    ])->assertRedirect(route('verification.notice'))->assertSessionHasNoErrors();

    $this->assertDatabaseHas('users', ['email' => 'dkjlvnsrsunuwvkjmz@kjkpc.net']);
});

test('registration schedules email without contacting the mail server', function () {
    Queue::fake();
    $this->post(route('register.submit'), [
        'name' => 'Queue Test', 'email' => 'queue@example.com',
        'password' => 'password123', 'password_confirmation' => 'password123',
    ])->assertRedirect(route('verification.notice'))->assertSessionHasNoErrors();
    $this->assertAuthenticated();
    Queue::assertPushed(SendQueuedNotifications::class,
        fn ($job) => $job->connection === 'database' && $job->queue === 'mail');
});

test('registration remains usable when verification scheduling fails', function () {
    Notification::shouldReceive('send')->once()->andThrow(new RuntimeException('Queue unavailable'));
    $this->post(route('register.submit'), [
        'name' => 'Recovery Test', 'email' => 'recovery@example.com',
        'password' => 'password123', 'password_confirmation' => 'password123',
    ])->assertRedirect(route('verification.notice'))->assertSessionHasErrors('email');
    $this->assertAuthenticated();
    expect(User::where('email', 'recovery@example.com')->count())->toBe(1);
});

test('password resets are queued for the mail worker', function () {
    Queue::fake();
    $user = User::factory()->create(['email' => 'reset@example.com']);
    $this->post(route('password.email'), ['email' => $user->email])
        ->assertRedirect()->assertSessionHasNoErrors();
    Queue::assertPushed(SendQueuedNotifications::class,
        fn ($job) => $job->connection === 'database' && $job->queue === 'mail'
            && $job->notification instanceof QueuedResetPassword);
});

test('a reservation request reserves its times and queues booking emails', function () {
    Mail::fake();
    config()->set('mail.notifications.address', 'staff@example.com');
    $room = testRoom();
    $checkIn = now()->addDays(3)->startOfDay();
    $payload = ['checkout_type' => 'guest', 'booking_type' => 'dates', 'check_in' => $checkIn->toDateString(), 'check_out' => $checkIn->copy()->addDays(2)->toDateString(), 'guests' => 2, 'children_count' => 1, 'pets_count' => 1, 'guest_name' => 'Test Guest', 'guest_email' => 'guest@example.com', 'guest_phone' => '09171234567', 'terms_accepted' => '1', 'submission_token' => (string) Str::uuid()];
    $this->post(route('bookings.store', $room), $payload)->assertRedirect()->assertSessionHasNoErrors();
    $booking = Booking::firstOrFail();
    expect($booking->status)->toBe('pending')->and($booking->hold_expires_at)->toBeNull()->and($booking->check_in_at->toDateString())->toBe($checkIn->toDateString())->and($booking->children_count)->toBe(1)->and($booking->pets_count)->toBe(1);
    $this->post(route('bookings.store', $room), $payload)->assertRedirect();
    expect(Booking::count())->toBe(1);
    Mail::assertQueued(BookingConfirmation::class, fn ($mail) => $mail->hasTo('guest@example.com'));
    Mail::assertQueued(NewBookingRequest::class, fn ($mail) => $mail->hasTo('staff@example.com'));
    $this->get(route('rooms.index', ['check_in' => $checkIn->toDateString(), 'check_out' => $checkIn->copy()->addDay()->toDateString()]))->assertOk()->assertSee('No rooms found');
    $payload['submission_token'] = (string) Str::uuid();
    $this->post(route('bookings.store', $room), $payload)->assertSessionHasErrors('availability');
    expect(Booking::count())->toBe(1);
    $this->get(route('rooms.availability', ['room' => $room, 'start' => $checkIn->toIso8601String(), 'end' => $checkIn->copy()->addDay()->toIso8601String()]))->assertJson(['available' => false]);
});

test('hourly reservations only accept advertised arrival hours', function () {
    $room = testRoom();
    $this->post(route('bookings.store', $room), ['checkout_type' => 'guest', 'booking_type' => 'hourly', 'hourly_date' => now()->addDay()->toDateString(), 'check_in_time' => '03:00', 'hours' => 3, 'guests' => 1, 'guest_name' => 'Test Guest', 'guest_email' => 'guest@example.com', 'guest_phone' => '09171234567', 'terms_accepted' => '1'])
        ->assertSessionHasErrors('check_in_time');
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
