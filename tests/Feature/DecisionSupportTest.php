<?php

use App\Models\Booking;
use App\Models\Payment;
use App\Models\Room;
use App\Models\User;
use App\Services\OperationalDecisionSupport;
use Illuminate\Support\Collection;

function decisionSupportTestRoom(string $name): Room
{
    return Room::create([
        'name' => $name,
        'slug' => 'dss-'.uniqid(),
        'room_type' => 'Test room',
        'description' => 'Decision support test room.',
        'beds' => 1,
        'guests' => 2,
        'price_per_night' => 1000,
        'is_active' => true,
        'operational_status' => 'available',
    ]);
}

function decisionSupportTestBooking(User $guest, Room $room, Carbon\Carbon $checkIn, Carbon\Carbon $checkOut, string $status = 'confirmed'): Booking
{
    return Booking::create([
        'user_id' => $guest->id,
        'room_id' => $room->id,
        'guest_name' => $guest->name,
        'guest_email' => $guest->email,
        'guest_phone' => '09170000000',
        'check_in' => $checkIn->toDateString(),
        'check_out' => $checkOut->toDateString(),
        'check_in_at' => $checkIn,
        'check_out_at' => $checkOut,
        'booking_type' => 'hourly',
        'hours' => max(1, (int) ($checkIn->diffInMinutes($checkOut) / 60)),
        'guests' => 1,
        'nights' => max(0, $checkIn->copy()->startOfDay()->diffInDays($checkOut->copy()->startOfDay())),
        'total_amount' => 1000,
        'payment_method' => 'cash',
        'payment_status' => 'pending',
        'status' => $status,
    ]);
}

test('room suggestions are branded as CAROL across the page and staff navigation', function () {
    $admin = User::factory()->create(['is_admin' => true]);

    $this->actingAsStaff($admin)
        ->get(route('admin.decision-support'))
        ->assertOk()
        ->assertSee('<title>CAROL · Decision Support · Carolina Admin</title>', false)
        ->assertSee('CAROL · DECISION SUPPORT')
        ->assertSee('Carolina Assistant for Reservations, Operations &amp; Lodging', false)
        ->assertSee('CAROL · Decision support');
});

test('dashboard explains operational recommendations with evidence and a next action', function () {
    $admin = User::factory()->create(['is_admin' => true]);
    $guest = User::factory()->create();

    foreach (range(1, 3) as $index) {
        decisionSupportTestBooking($guest, decisionSupportTestRoom('Cancelled '.$index), now()->addDays($index), now()->addDays($index)->addHours(6), 'cancelled');
    }
    foreach (range(1, 2) as $index) {
        decisionSupportTestBooking($guest, decisionSupportTestRoom('Confirmed '.$index), now()->addDays($index + 5), now()->addDays($index + 5)->addHours(6));
    }

    $this->actingAsStaff($admin)
        ->get(route('admin.dashboard'))
        ->assertOk()
        ->assertSee('CAROL')
        ->assertSee('Carolina Assistant for Reservations, Operations &amp; Lodging', false)
        ->assertSee('3 of 5 decided bookings were cancelled (60%)')
        ->assertSee('Review booking requests');
});

test('dashboard does not trigger the cancellation alert from a rounded-up rate below its threshold', function () {
    $admin = User::factory()->create(['is_admin' => true]);
    $guest = User::factory()->create();
    $room = decisionSupportTestRoom('Cancellation threshold');

    foreach (range(1, 13) as $index) {
        decisionSupportTestBooking($guest, $room, now()->addDays($index), now()->addDays($index)->addHours(6), 'cancelled');
    }
    foreach (range(1, 40) as $index) {
        decisionSupportTestBooking($guest, $room, now()->addDays($index + 20), now()->addDays($index + 20)->addHours(6));
    }

    $this->actingAsStaff($admin)
        ->get(route('admin.dashboard'))
        ->assertOk()
        ->assertDontSee('Cancellation activity is elevated');
});

test('dashboard shows the available same-day room turnaround window', function () {
    $admin = User::factory()->create(['is_admin' => true]);
    $guest = User::factory()->create();
    $room = decisionSupportTestRoom('Turnover Room');
    $departure = now()->addDay()->setTime(10, 0);
    $arrival = $departure->copy()->setTime(14, 0);

    decisionSupportTestBooking($guest, $room, $departure->copy()->subHours(6), $departure);
    decisionSupportTestBooking($guest, $room, $arrival, $arrival->copy()->addHours(6));

    $this->actingAsStaff($admin)
        ->get(route('admin.dashboard'))
        ->assertOk()
        ->assertSee('Room turnover priorities')
        ->assertSee('Turnover Room')
        ->assertSee('4 hours between stays');
});

test('turnover priorities only pair confirmed stays in the same room and day, ordered by shortest prep gap', function () {
    $admin = User::factory()->create(['is_admin' => true]);
    $guest = User::factory()->create();
    $day = now()->addDay();

    $longGapRoom = decisionSupportTestRoom('Long gap room');
    $longDeparture = $day->copy()->setTime(10, 0);
    decisionSupportTestBooking($guest, $longGapRoom, $longDeparture->copy()->subHours(6), $longDeparture);
    decisionSupportTestBooking($guest, $longGapRoom, $longDeparture->copy()->setTime(13, 0), $longDeparture->copy()->setTime(19, 0), 'cancelled');
    decisionSupportTestBooking($guest, $longGapRoom, $longDeparture->copy()->setTime(14, 0), $longDeparture->copy()->setTime(20, 0));

    $shortGapRoom = decisionSupportTestRoom('Short gap room');
    $shortDeparture = $day->copy()->setTime(11, 0);
    decisionSupportTestBooking($guest, $shortGapRoom, $shortDeparture->copy()->subHours(6), $shortDeparture);
    decisionSupportTestBooking($guest, $shortGapRoom, $shortDeparture->copy()->setTime(12, 0), $shortDeparture->copy()->setTime(18, 0));

    $differentDayRoom = decisionSupportTestRoom('Different day room');
    $lateDeparture = $day->copy()->setTime(22, 0);
    decisionSupportTestBooking($guest, $differentDayRoom, $lateDeparture->copy()->subHours(6), $lateDeparture);
    decisionSupportTestBooking($guest, $differentDayRoom, $lateDeparture->copy()->addHours(3), $lateDeparture->copy()->addHours(9));

    $this->actingAsStaff($admin)
        ->get(route('admin.dashboard'))
        ->assertOk()
        ->assertViewHas('turnovers', function (Collection $turnovers) {
            return $turnovers->count() === 2
                && $turnovers->first()['room']->name === 'Short gap room'
                && $turnovers->last()['room']->name === 'Long gap room'
                && $turnovers->last()['next_check_in_at']->format('H:i') === '14:00';
        });
});

test('booking report summary counts only reservations created in the selected period', function () {
    $this->travelTo(now()->setDate(2026, 10, 22)->setTime(12, 0));
    $admin = User::factory()->create(['is_admin' => true]);
    $guest = User::factory()->create();

    $inPeriodRoom = decisionSupportTestRoom('In period');
    decisionSupportTestBooking($guest, $inPeriodRoom, now()->addDays(1), now()->addDays(1)->addHours(6));
    decisionSupportTestBooking($guest, decisionSupportTestRoom('Cancelled in period'), now()->addDays(2), now()->addDays(2)->addHours(6), 'cancelled');
    $outsidePeriod = decisionSupportTestBooking($guest, decisionSupportTestRoom('Outside period'), now()->addDays(3), now()->addDays(3)->addHours(6));
    $outsidePeriod->forceFill(['created_at' => now()->subDays(20), 'updated_at' => now()->subDays(20)])->save();

    $this->actingAsStaff($admin)
        ->get(route('admin.reports', ['period' => 'daily', 'metric' => 'bookings']))
        ->assertOk()
        ->assertViewHas('bookingReportMetrics', fn (array $metrics) => $metrics['total'] === 2 && $metrics['cancelled'] === 1 && $metrics['roomsBooked'] === 1);
});

test('guest report identifies first-time and returning guests within the selected period', function () {
    $this->travelTo(now()->setDate(2026, 10, 22)->setTime(12, 0));
    $admin = User::factory()->create(['is_admin' => true]);
    $returningGuest = User::factory()->create();
    $newGuest = User::factory()->create();

    $prior = decisionSupportTestBooking($returningGuest, decisionSupportTestRoom('Prior visit'), now()->subDays(19), now()->subDays(19)->addHours(6));
    $prior->forceFill(['created_at' => now()->subDays(20), 'updated_at' => now()->subDays(20)])->save();
    decisionSupportTestBooking($returningGuest, decisionSupportTestRoom('Returning visit'), now()->addDays(1)->setTime(8, 0), now()->addDays(1)->setTime(14, 0));
    decisionSupportTestBooking($newGuest, decisionSupportTestRoom('First visit'), now()->addDays(2)->setTime(8, 0), now()->addDays(2)->setTime(20, 0));

    $this->actingAsStaff($admin)
        ->get(route('admin.reports', ['period' => 'daily', 'metric' => 'guests']))
        ->assertOk()
        ->assertViewHas('guestReportMetrics', fn (array $metrics) => $metrics['total'] === 2 && $metrics['new'] === 1 && $metrics['returning'] === 1 && $metrics['averageDurationHours'] === 9.0);
});

test('guest report uses the saved hours when an hourly booking has no check-in timestamps', function () {
    $this->travelTo(now()->setDate(2026, 10, 22)->setTime(12, 0));
    $admin = User::factory()->create(['is_admin' => true]);
    $guest = User::factory()->create();
    $booking = decisionSupportTestBooking($guest, decisionSupportTestRoom('Legacy hourly stay'), now()->addDay(), now()->addDay()->addHours(6));
    $booking->forceFill(['booking_type' => 'hourly', 'hours' => 6, 'check_in_at' => null, 'check_out_at' => null])->save();
    $unknownDuration = decisionSupportTestBooking($guest, decisionSupportTestRoom('Unknown duration'), now()->addDays(2), now()->addDays(2)->addHours(6));
    $unknownDuration->forceFill(['booking_type' => 'dates', 'hours' => null, 'nights' => 0, 'check_in_at' => null, 'check_out_at' => null])->save();

    $this->actingAsStaff($admin)
        ->get(route('admin.reports', ['period' => 'daily', 'metric' => 'guests']))
        ->assertOk()
        ->assertViewHas('guestReportMetrics', fn (array $metrics) => $metrics['averageDurationHours'] === 6.0);
});

test('guest report shows no average when every stay duration is unknown', function () {
    $this->travelTo(now()->setDate(2026, 10, 22)->setTime(12, 0));
    $admin = User::factory()->create(['is_admin' => true]);
    $guest = User::factory()->create();
    $booking = decisionSupportTestBooking($guest, decisionSupportTestRoom('Unknown duration'), now()->addDay(), now()->addDay()->addHours(6));
    $booking->forceFill(['booking_type' => 'dates', 'hours' => null, 'nights' => 0, 'check_in_at' => null, 'check_out_at' => null])->save();

    $this->actingAsStaff($admin)
        ->get(route('admin.reports', ['period' => 'daily', 'metric' => 'guests']))
        ->assertOk()
        ->assertViewHas('guestReportMetrics', fn (array $metrics) => $metrics['averageDurationHours'] === null);
});

test('payment report shows verified payments, refunds, and net collected for the selected period', function () {
    $this->travelTo(now()->setDate(2026, 10, 22)->setTime(12, 0));
    $admin = User::factory()->create(['is_admin' => true]);
    $guest = User::factory()->create();
    $room = decisionSupportTestRoom('Payment report');
    $booking = decisionSupportTestBooking($guest, $room, now()->addDay(), now()->addDay()->addHours(6));

    Payment::create(['booking_id' => $booking->id, 'amount' => 900, 'method' => 'paymongo_gcash', 'status' => 'paid', 'paid_at' => now()->subDays(2)]);
    Payment::create(['booking_id' => $booking->id, 'amount' => 100, 'method' => 'paymongo_gcash', 'status' => 'refunded', 'paid_at' => now()->subDay()]);
    Payment::create(['booking_id' => $booking->id, 'amount' => 500, 'method' => 'cash', 'status' => 'paid', 'paid_at' => now()->subDays(20)]);

    $this->actingAsStaff($admin)
        ->get(route('admin.reports', ['period' => 'daily', 'metric' => 'payments']))
        ->assertOk()
        ->assertSee('Paid in period')
        ->assertSee('Refunded in period')
        ->assertSee('Net collected')
        ->assertViewHas('financialMetrics.period_ledger', fn (array $ledger) => $ledger['paid'] === 900.0 && $ledger['refunds'] === 100.0 && $ledger['net_collected'] === 800.0);
});

test('dashboard fallback keeps operational signals renderable when a summary query fails', function () {
    $admin = User::factory()->create(['is_admin' => true]);
    $failingSupport = new class extends OperationalDecisionSupport
    {
        public function upcomingTurnovers(): Collection
        {
            throw new RuntimeException('Simulated summary failure.');
        }
    };
    app()->instance(OperationalDecisionSupport::class, $failingSupport);

    $this->actingAsStaff($admin)
        ->get(route('admin.dashboard'))
        ->assertOk()
        ->assertSee('The dashboard is showing core booking data while a non-essential operational summary is restored.')
        ->assertSee('Review bookings');
});
