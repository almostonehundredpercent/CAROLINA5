<?php

use App\Models\ActivityLog;
use App\Models\Booking;
use App\Models\Review;
use App\Models\Room;
use App\Models\RoomBlock;
use App\Models\User;
use Illuminate\Support\Facades\Mail;

function adminDayRoom(string $slug, int $capacity = 2): Room
{
    return Room::create([
        'name' => ucfirst(str_replace('-', ' ', $slug)),
        'slug' => $slug,
        'room_type' => 'Standard',
        'description' => 'Operational test room.',
        'beds' => 1,
        'guests' => $capacity,
        'price_per_night' => 1000,
        'is_active' => true,
        'operational_status' => 'available',
    ]);
}

function adminDayBooking(Room $room, User $guest, array $attributes = []): Booking
{
    $start = now()->subHour();

    return Booking::create(array_merge([
        'user_id' => $guest->id,
        'room_id' => $room->id,
        'guest_name' => $guest->name,
        'guest_email' => $guest->email,
        'guest_phone' => '09171234567',
        'check_in' => $start->toDateString(),
        'check_out' => $start->copy()->addDay()->toDateString(),
        'check_in_at' => $start,
        'check_out_at' => $start->copy()->addDay(),
        'guests' => 1,
        'nights' => 1,
        'total_amount' => 1000,
        'payment_method' => 'cash',
        'status' => 'pending',
    ], $attributes));
}

test('staff can complete a busy-day booking workflow without creating conflicts', function () {
    Mail::fake();
    $admin = User::factory()->create(['is_admin' => true]);
    $guest = User::factory()->create();
    $activeRoom = adminDayRoom('busy-active-room');
    $freeRoom = adminDayRoom('busy-free-room', 3);
    $arrival = adminDayBooking($activeRoom, $guest, ['status' => 'confirmed']);
    $request = adminDayBooking($freeRoom, $guest, [
        'check_in_at' => now()->addDays(2)->startOfHour(),
        'check_out_at' => now()->addDays(3)->startOfHour(),
        'check_in' => now()->addDays(2)->toDateString(),
        'check_out' => now()->addDays(3)->toDateString(),
    ]);
    $review = Review::create([
        'booking_id' => $request->id,
        'room_id' => $freeRoom->id,
        'user_id' => $guest->id,
        'guest_name' => $guest->name,
        'rating' => 5,
        'comment' => 'Great stay.',
        'status' => 'pending',
    ]);

    $this->actingAs($admin);
    $this->get(route('admin.dashboard'))->assertOk();
    $this->get(route('admin.bookings'))->assertOk()->assertDontSee('@if');
    $this->get(route('admin.rooms'))->assertOk();
    $this->get(route('admin.walk-ins.create'))->assertOk();
    $this->get(route('admin.reports', ['period' => 'daily', 'metric' => 'earnings']))->assertOk();

    $this->patch(route('admin.bookings.update', $request), ['status' => 'confirmed'])
        ->assertRedirect();
    expect($request->fresh()->status)->toBe('confirmed');

    $this->post(route('admin.bookings.check-in', $arrival), ['action' => 'check_in'])
        ->assertRedirect();
    expect($arrival->fresh()->checked_in_at)->not->toBeNull();

    $this->post(route('admin.bookings.check-out', $arrival), ['action' => 'check_out'])
        ->assertRedirect();
    expect($arrival->fresh()->checked_out_at)->not->toBeNull();
    expect(RoomBlock::where('room_id', $activeRoom->id)->where('status', 'cleaning')->exists())->toBeTrue();

    $this->patch(route('admin.rooms.status', $freeRoom), [
        'operational_status' => 'maintenance',
        'operational_starts_at' => now()->addDays(5)->startOfHour()->toDateTimeString(),
        'operational_until' => now()->addDays(5)->addHours(2)->startOfHour()->toDateTimeString(),
        'notes' => 'Aircon service',
    ])->assertRedirect();
    expect(RoomBlock::where('room_id', $freeRoom->id)->where('status', 'maintenance')->exists())->toBeTrue();

    $this->patch(route('admin.reviews.update', $review), ['status' => 'approved'])
        ->assertRedirect();
    expect($review->fresh()->status)->toBe('approved');

    $this->post(route('admin.walk-ins.store'), [
        'room_id' => $activeRoom->id,
        'guest_name' => 'Walk-in Guest',
        'guest_phone' => '09170000000',
        'guests' => 1,
        'check_in' => now()->toDateString(),
        'check_out' => now()->addDay()->toDateString(),
        'check_in_time' => now()->format('H:i'),
        'check_out_time' => now()->addHour()->format('H:i'),
    ])->assertSessionHasErrors('room_id');

    expect(ActivityLog::whereIn('event', ['booking_confirmed', 'checked_in', 'checked_out', 'review_approved'])->count())->toBe(4);
});
