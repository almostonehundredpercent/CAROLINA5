<?php

use App\Models\ActivityLog;
use App\Models\Booking;
use App\Models\Review;
use App\Models\Room;
use App\Models\RoomBlock;
use App\Models\User;
use Illuminate\Support\Carbon;
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

test('check-in confirmation is rendered in the admin interface on front desk and bookings pages', function () {
    Carbon::setTestNow('2026-10-23 12:00:00');

    try {
        $admin = User::factory()->create(['is_admin' => true]);
        $guest = User::factory()->create(['name' => 'Jordan Lee']);
        $room = adminDayRoom('check-in-confirmation-room');
        adminDayBooking($room, $guest, ['status' => 'confirmed']);

        $this->actingAsStaff($admin);

        foreach ([route('admin.frontdesk'), route('admin.bookings')] as $url) {
            $this->get($url)
                ->assertOk()
                ->assertSee('id="admin-confirm-dialog"', false)
                ->assertSee('data-admin-confirm', false)
                ->assertSee('Check in Jordan Lee now?', false)
                ->assertDontSee("onsubmit=\"return confirm('Check this guest in now?')\"", false);
        }
    } finally {
        Carbon::setTestNow();
    }
});

test('booking cancellation uses the in-page confirmation and explains refunds are separate', function () {
    $admin = User::factory()->create(['is_admin' => true]);
    $guest = User::factory()->create(['name' => 'Jordan Lee']);
    $room = adminDayRoom('cancel-confirmation-room');
    $booking = adminDayBooking($room, $guest, ['status' => 'confirmed']);

    $this->actingAsStaff($admin)
        ->get(route('admin.bookings'))
        ->assertOk()
        ->assertSee('data-confirm-kicker="BOOKING CANCELLATION"', false)
        ->assertSee('data-confirm-icon="!"', false)
        ->assertSee('data-confirm-title="Cancel this booking?"', false)
        ->assertSee('data-confirm-action="Cancel booking"', false)
        ->assertSee('data-confirm-dismiss="Keep booking"', false)
        ->assertSee('Refunds must be handled separately.', false)
        ->assertSee($booking->reference)
        ->assertDontSee('onsubmit="return confirm(\'Cancel this booking?\')"', false);
});

test('bookings page navigation reaches every record with the default page size', function () {
    Carbon::setTestNow('2026-10-23 12:00:00');

    try {
        $admin = User::factory()->create(['is_admin' => true]);
        $guest = User::factory()->create(['name' => 'Reservation guest']);

        for ($index = 1; $index <= 9; $index++) {
            $room = adminDayRoom('booking-list-room-'.$index);
            $timestamp = now()->addSeconds($index);
            adminDayBooking($room, $guest, [
                'guest_name' => 'Reservation '.$index,
                'guest_email' => 'reservation'.$index.'@example.test',
                'created_at' => $timestamp,
                'updated_at' => $timestamp,
            ]);
        }

        $firstPage = $this->actingAsStaff($admin)
            ->get(route('admin.bookings'))
            ->assertOk()
            ->assertSee('Showing 1-5 of 9 bookings')
            ->assertSee('Page 1 of 2')
            ->assertSee('booking_page=2', false)
            ->assertSee('Next')
            ->assertSee('class="booking-heading-actions"', false)
            ->assertSee('class="booking-page-previous is-disabled"', false)
            ->assertSee('class="booking-page-next"', false)
            ->assertSee('data-label="Guest"', false)
            ->assertSee('data-label="Actions"', false)
            ->assertSee('name="per_page"', false);
        preg_match_all('/reservation\d+@example\.test/', $firstPage->getContent(), $firstPageEmails);
        expect($firstPageEmails[0])->toHaveCount(5);

        $secondPage = $this->actingAsStaff($admin)
            ->get(route('admin.bookings', ['booking_page' => 2]))
            ->assertOk()
            ->assertSee('Showing 6-9 of 9 bookings')
            ->assertSee('Page 2 of 2')
            ->assertSee('Previous');
        preg_match_all('/reservation\d+@example\.test/', $secondPage->getContent(), $secondPageEmails);
        expect($secondPageEmails[0])->toHaveCount(4);
        expect(array_unique(array_merge($firstPageEmails[0], $secondPageEmails[0])))->toHaveCount(9);
    } finally {
        Carbon::setTestNow();
    }
});

test('bookings page keeps a chosen page size while navigating and can show all records', function () {
    Carbon::setTestNow('2026-10-23 12:00:00');

    try {
        $admin = User::factory()->create(['is_admin' => true]);
        $guest = User::factory()->create(['name' => 'Reservation guest']);

        for ($index = 1; $index <= 16; $index++) {
            $room = adminDayRoom('booking-page-size-room-'.$index);
            $timestamp = now()->addSeconds($index);
            adminDayBooking($room, $guest, [
                'guest_name' => 'Reservation '.$index,
                'guest_email' => 'reservation'.$index.'@example.test',
                'created_at' => $timestamp,
                'updated_at' => $timestamp,
            ]);
        }

        $firstPage = $this->actingAsStaff($admin)
            ->get(route('admin.bookings', ['per_page' => 15]))
            ->assertOk()
            ->assertSee('Showing 1-15 of 16 bookings')
            ->assertSee('Page 1 of 2')
            ->assertSee('booking_page=2', false);
        preg_match('/href="([^"]*booking_page=2[^"]*)"/', $firstPage->getContent(), $nextPageLink);
        parse_str(parse_url(html_entity_decode($nextPageLink[1]), PHP_URL_QUERY), $nextPageQuery);
        expect($nextPageQuery)->toMatchArray(['per_page' => '15', 'booking_page' => '2']);
        preg_match_all('/reservation\d+@example\.test/', $firstPage->getContent(), $firstPageEmails);
        expect($firstPageEmails[0])->toHaveCount(15);

        $secondPage = $this->actingAsStaff($admin)
            ->get(route('admin.bookings', ['per_page' => 15, 'booking_page' => 2]))
            ->assertOk()
            ->assertSee('Showing 16-16 of 16 bookings')
            ->assertSee('Page 2 of 2');
        preg_match_all('/reservation\d+@example\.test/', $secondPage->getContent(), $secondPageEmails);
        expect($secondPageEmails[0])->toHaveCount(1);
        expect(array_diff($secondPageEmails[0], $firstPageEmails[0]))->toBe($secondPageEmails[0]);

        $allOnOnePage = $this->actingAsStaff($admin)
            ->get(route('admin.bookings', ['per_page' => 50]))
            ->assertOk()
            ->assertSee('Showing 1-16 of 16 bookings')
            ->assertSee('Page 1 of 1');
        preg_match_all('/reservation\d+@example\.test/', $allOnOnePage->getContent(), $allPageEmails);
        expect($allPageEmails[0])->toHaveCount(16);
    } finally {
        Carbon::setTestNow();
    }
});

test('payment status changes show a clear in-page confirmation for paid and unpaid records', function () {
    Carbon::setTestNow('2026-10-23 12:00:00');

    try {
        $admin = User::factory()->create(['is_admin' => true]);
        $guest = User::factory()->create(['name' => 'Jordan Lee']);
        $unpaidRoom = adminDayRoom('manual-payment-room');
        $paidRoom = adminDayRoom('recorded-payment-room');
        adminDayBooking($unpaidRoom, $guest, ['status' => 'confirmed', 'payment_status' => 'pending']);
        adminDayBooking($paidRoom, $guest, ['status' => 'confirmed', 'payment_status' => 'paid']);

        $this->actingAsStaff($admin)
            ->get(route('admin.bookings'))
            ->assertOk()
            ->assertSee('data-confirm-title="Update payment record?"', false)
            ->assertSee('data-confirm-message="Mark this payment as paid? This records the payment manually and does not process a charge."', false)
            ->assertSee('data-confirm-action="Mark paid"', false)
            ->assertSee('data-confirm-message="Mark this payment as unpaid? This changes only the payment record; it does not issue a refund."', false)
            ->assertSee('data-confirm-action="Mark unpaid"', false)
            ->assertDontSee('Update this payment record?', false);
    } finally {
        Carbon::setTestNow();
    }
});

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

    $this->actingAsStaff($admin);
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

    $this->patch(route('admin.bookings.update', $arrival), ['status' => 'cancelled'])
        ->assertStatus(422);
    expect($arrival->fresh()->status)->toBe('confirmed');

    $this->post(route('admin.bookings.check-out', $arrival), ['action' => 'check_out'])
        ->assertRedirect();
    expect($arrival->fresh()->checked_out_at)->not->toBeNull();
    expect(RoomBlock::where('room_id', $activeRoom->id)->where('status', 'cleaning')->exists())->toBeTrue();

    $this->patch(route('admin.rooms.status', $freeRoom), [
        'operational_status' => 'maintenance',
        'operational_starts_at' => now()->addDays(2)->startOfHour()->toDateTimeString(),
        'operational_until' => now()->addDays(2)->addHour()->startOfHour()->toDateTimeString(),
    ])->assertSessionHasErrors('operational_until');

    $this->patch(route('admin.rooms.status', $freeRoom), [
        'operational_status' => 'maintenance',
        'operational_starts_at' => now()->addDays(5)->startOfHour()->toDateTimeString(),
        'operational_until' => now()->addDays(5)->addHours(2)->startOfHour()->toDateTimeString(),
        'notes' => 'Aircon service',
    ])->assertRedirect();
    expect(RoomBlock::where('room_id', $freeRoom->id)->where('status', 'maintenance')->exists())->toBeTrue();

    $this->patch(route('admin.rooms.status', $freeRoom), [
        'operational_status' => 'cleaning',
        'operational_date' => now()->addDays(7)->toDateString(),
        'operational_start_time' => '08:00',
        'operational_end_time' => '17:00',
    ])->assertRedirect();
    expect(RoomBlock::where('room_id', $freeRoom->id)->where('status', 'cleaning')->exists())->toBeTrue();

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
