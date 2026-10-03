<?php

use App\Models\Booking;
use App\Models\Payment;
use App\Models\Room;
use App\Models\RoomBlock;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;

function holdRoom(): Room
{
    return Room::create(['name' => 'Hold room', 'slug' => 'hold-room', 'room_type' => 'Standard', 'description' => 'Test room', 'beds' => 1, 'guests' => 2, 'price_per_night' => 450, 'rental_hours' => 6, 'is_active' => true, 'operational_status' => 'available']);
}

function heldBooking(Room $room, array $overrides = []): Booking
{
    return Booking::create($overrides + ['room_id' => $room->id, 'guest_name' => 'Hold guest', 'guest_email' => 'hold@example.test', 'guest_phone' => '09XX XXX XXXX', 'check_in' => '2026-10-05', 'check_out' => '2026-10-05', 'check_in_at' => '2026-10-05 12:00:00', 'check_out_at' => '2026-10-05 18:00:00', 'booking_type' => 'hourly', 'hours' => 6, 'guests' => 1, 'nights' => 1, 'total_amount' => 450, 'status' => 'pending', 'payment_status' => 'pending', 'payment_method' => 'cash']);
}

beforeEach(function () {
    $this->travelTo(now()->setDate(2026, 10, 3)->setTime(1, 0));
    config()->set('booking.hold_minutes', 30);
});

test('a public request has a finite hold which duplicate submission cannot extend', function () {
    Mail::fake();
    $room = holdRoom();
    $payload = ['checkout_type' => 'guest', 'booking_type' => 'hourly', 'hourly_date' => '2026-10-05', 'check_in_time' => '12:00', 'hours' => 6, 'guests' => 1, 'guest_name' => 'Test guest', 'guest_email' => 'guest@example.test', 'guest_phone' => '09171234567', 'terms_accepted' => 1, 'submission_token' => (string) Str::uuid()];
    $this->post(route('bookings.store', $room), $payload)->assertSessionHasNoErrors();
    $booking = Booking::firstOrFail();
    expect($booking->hold_expires_at?->format('H:i'))->toBe('01:30');
    $this->travel(10)->minutes();
    $this->post(route('bookings.store', $room), $payload)->assertRedirect(route('bookings.receipt', $booking));
    expect($booking->fresh()->hold_expires_at?->format('H:i'))->toBe('01:30');
});

test('expiry releases unpaid inventory at the boundary without a scheduler', function () {
    $room = holdRoom();
    $booking = heldBooking($room, ['hold_expires_at' => now()->addMinutes(30)]);
    $url = route('rooms.availability', ['room' => $room, 'start' => '2026-10-05T12:00:00Z', 'end' => '2026-10-05T18:00:00Z']);
    $this->get($url)->assertJson(['available' => false]);
    $this->travel(30)->minutes();
    $this->get($url)->assertJson(['available' => true]);
    expect(Booking::whereKey($booking->id)->exists())->toBeTrue();
    heldBooking($room, ['status' => 'confirmed']);
    expect($room->bookings()->blocking()->count())->toBe(1);
});

test('payment backed confirmed and checked in inventory does not expire', function (array $attributes) {
    $room = holdRoom();
    $booking = heldBooking($room, $attributes + ['hold_expires_at' => now()->subMinute()]);
    expect($room->bookings()->blocking()->whereKey($booking->id)->exists())->toBeTrue();
    Booking::releaseExpiredHolds();
    expect($booking->fresh()->status)->not->toBe('cancelled');
})->with([
    'confirmed' => [['status' => 'confirmed']],
    'paid flag' => [['payment_status' => 'paid']],
    'paid timestamp' => [['paid_at' => '2026-10-03 00:30:00']],
    'checked in' => [['checked_in_at' => '2026-10-03 00:30:00']],
]);

test('paid ledger and verified legacy deposit protect pending inventory', function () {
    $room = holdRoom();
    $booking = heldBooking($room, ['hold_expires_at' => now()->addMinute()]);
    Payment::create(['booking_id' => $booking->id, 'amount' => 100, 'method' => 'cash', 'status' => 'paid', 'paid_at' => now()]);
    $this->travel(2)->minutes();
    expect($room->bookings()->blocking()->whereKey($booking->id)->exists())->toBeTrue();
    Booking::releaseExpiredHolds();
    expect($booking->fresh()->status)->toBe('pending');
    Payment::where('booking_id', $booking->id)->delete();
    DB::table('bookings')->where('id', $booking->id)->update(['deposit_verified_at' => now()]);
    expect($room->bookings()->blocking()->whereKey($booking->id)->exists())->toBeTrue();
});

test('expired requests cannot start checkout', function () {
    $room = holdRoom();
    $booking = heldBooking($room, ['hold_expires_at' => now()->subMinute()]);
    Http::fake();
    $this->withSession(['guest_booking_reference' => $booking->reference])->post(route('bookings.paymongo.start', $booking))->assertStatus(422);
    Http::assertNothingSent();
});

test('late verified payment is recorded without reviving replaced inventory', function () {
    Mail::fake();
    config()->set('services.paymongo.mode', 'test');
    config()->set('services.paymongo.secret_key', 'sk_test_example');
    $room = holdRoom();
    $booking = heldBooking($room, ['hold_expires_at' => now()->addMinute()]);
    Payment::create(['booking_id' => $booking->id, 'amount' => 450, 'method' => 'gcash', 'status' => 'pending', 'reference' => 'paymongo:cs_late']);
    $this->travel(2)->minutes();
    heldBooking($room, ['status' => 'confirmed']);
    Http::fake(['https://api.paymongo.com/v1/checkout_sessions/cs_late' => Http::response([
        'data' => ['attributes' => ['payment_intent' => ['attributes' => ['status' => 'succeeded']]]],
    ])]);
    $this->withSession(['guest_booking_reference' => $booking->reference])->get(route('bookings.paymongo.return', $booking))->assertRedirect(route('bookings.receipt', $booking));
    expect($booking->fresh()->status)->toBe('cancelled')->and($booking->fresh()->payment_status)->toBe('paid')
        ->and($room->bookings()->blocking()->count())->toBe(1)
        ->and(Payment::where('booking_id', $booking->id)->first()->status)->toBe('paid');
});

test('verified payment on protected inventory survives a manager operation override', function () {
    Mail::fake();
    config()->set('services.paymongo.mode', 'test');
    config()->set('services.paymongo.secret_key', 'sk_test_example');
    $room = holdRoom();
    $booking = heldBooking($room, ['status' => 'confirmed']);
    RoomBlock::create(['room_id' => $room->id, 'status' => 'maintenance', 'starts_at' => '2026-10-05 13:00:00', 'ends_at' => '2026-10-05 14:00:00', 'notes' => 'Manager override']);
    Payment::create(['booking_id' => $booking->id, 'amount' => 450, 'method' => 'gcash', 'status' => 'pending', 'reference' => 'paymongo:cs_override']);
    Http::fake(['https://api.paymongo.com/v1/checkout_sessions/cs_override' => Http::response([
        'data' => ['attributes' => ['payment_intent' => ['attributes' => ['status' => 'succeeded']]]],
    ])]);
    $this->withSession(['guest_booking_reference' => $booking->reference])->get(route('bookings.paymongo.return', $booking))->assertRedirect(route('bookings.receipt', $booking));
    expect($booking->fresh()->payment_status)->toBe('paid')->and($booking->fresh()->status)->toBe('confirmed')->and($booking->payments()->first()->status)->toBe('paid');
});

test('a failed payment can be recorded on an expired request without reclaiming inventory', function () {
    Mail::fake();
    $room = holdRoom();
    $booking = heldBooking($room, ['hold_expires_at' => now()->subMinute()]);
    heldBooking($room, ['status' => 'confirmed']);
    $admin = User::factory()->create(['is_admin' => true]);
    $this->actingAsStaff($admin)->patch(route('admin.bookings.payment', $booking), ['payment_status' => 'failed', 'payment_method' => 'cash'])->assertSessionHasNoErrors();
    expect($booking->payments()->first()?->status)->toBe('failed')->and($room->bookings()->blocking()->count())->toBe(1);
});

test('expired receipt explains availability and removes the payment action', function () {
    $booking = heldBooking(holdRoom(), ['hold_expires_at' => now()->subMinute()]);
    $this->withSession(['guest_booking_reference' => $booking->reference])->get(route('bookings.receipt', $booking))
        ->assertOk()->assertSee('Reservation hold expired')->assertDontSee('id="paymongo-checkout-form"', false);
});

test('receipt and terms describe the same finite unpaid hold', function () {
    $booking = heldBooking(holdRoom());
    $this->withSession(['guest_booking_reference' => $booking->reference])->get(route('bookings.receipt', $booking))
        ->assertOk()->assertSee('30 minutes')->assertSee('Oct 3, 2026, 9:30 AM');
    $this->get('/terms')->assertOk()->assertSee('30 minutes')->assertDontSee('Pending requests do not prevent');
});

test('confirmed paid and refunded receipts retain their record and do not ask for repayment', function (array $attributes, string $label) {
    $booking = heldBooking(holdRoom(), $attributes);
    $this->withSession(['guest_booking_reference' => $booking->reference])->get(route('bookings.receipt', $booking))
        ->assertOk()->assertSee($label)->assertDontSee('id="paymongo-checkout-form"', false);
})->with([
    'confirmed paid' => [['status' => 'confirmed', 'payment_status' => 'paid'], 'Your reservation is confirmed.'],
    'refunded' => [['status' => 'cancelled', 'payment_status' => 'refunded'], 'Refund recorded.'],
]);

test('staff cannot record a new paid ledger on an expired slot', function () {
    Mail::fake();
    $booking = heldBooking(holdRoom(), ['hold_expires_at' => now()->subMinute()]);
    $admin = User::factory()->create(['is_admin' => true]);
    $this->actingAsStaff($admin)->patch(route('admin.bookings.payment', $booking), ['payment_status' => 'paid', 'payment_method' => 'cash'])->assertStatus(422);
    expect($booking->payments()->count())->toBe(0);
});

test('legacy migration grants grace only to unpaid requests and is idempotent', function () {
    $room = holdRoom();
    $unpaid = heldBooking($room);
    DB::table('bookings')->where('id', $unpaid->id)->update(['hold_expires_at' => null, 'created_at' => now()->subDays(30)]);
    $paid = heldBooking($room, ['check_in_at' => '2026-10-06 12:00:00', 'check_out_at' => '2026-10-06 18:00:00', 'payment_status' => 'paid']);
    $deposit = heldBooking($room, ['check_in_at' => '2026-10-07 12:00:00', 'check_out_at' => '2026-10-07 18:00:00']);
    DB::table('bookings')->where('id', $deposit->id)->update(['hold_expires_at' => null, 'deposit_verified_at' => now()]);
    $migration = require database_path('migrations/2026_10_03_000001_bound_legacy_pending_holds.php');
    $migration->up();
    $deadline = $unpaid->fresh()->hold_expires_at;
    expect($deadline->equalTo(now()->addDay()))->toBeTrue()->and($paid->fresh()->hold_expires_at)->toBeNull()->and($deposit->fresh()->hold_expires_at)->toBeNull();
    $this->travel(1)->hours();
    $migration->up();
    expect($unpaid->fresh()->hold_expires_at->equalTo($deadline))->toBeTrue();
});
