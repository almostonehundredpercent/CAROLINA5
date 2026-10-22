<?php

use App\Mail\BookingUpdate;
use App\Models\Booking;
use App\Models\Payment;
use App\Models\Room;
use App\Models\User;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;

function payMongoWebhookPayload(Booking $booking, array $overrides = []): array
{
    return array_replace_recursive([
        'data' => [
            'id' => 'evt_test_123',
            'type' => 'event',
            'attributes' => [
                'type' => 'checkout_session.payment.paid',
                'livemode' => false,
                'created_at' => now()->timestamp,
                'data' => [
                    'id' => 'cs_test_123',
                    'type' => 'checkout_session',
                    'attributes' => [
                        'livemode' => false,
                        'reference_number' => $booking->reference,
                        'payments' => [[
                            'id' => 'pay_test_123',
                            'type' => 'payment',
                            'attributes' => [
                                'amount' => 100000,
                                'currency' => 'PHP',
                                'status' => 'paid',
                            ],
                        ]],
                    ],
                ],
            ],
        ],
    ], $overrides);
}

function signedPayMongoWebhookRequest($test, array $payload, string $secret = 'whsec_test_example')
{
    $rawBody = json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
    $timestamp = (string) now()->timestamp;
    $signature = hash_hmac('sha256', $timestamp.'.'.$rawBody, $secret);

    return $test->call('POST', route('bookings.paymongo.webhook'), [], [], [], [
        'CONTENT_TYPE' => 'application/json',
        'HTTP_PAYMONGO_SIGNATURE' => "t={$timestamp},te={$signature},li=",
    ], $rawBody);
}

function payMongoBooking(): Booking
{
    $room = Room::create([
        'name' => 'PayMongo Room', 'slug' => 'paymongo-room', 'room_type' => 'Standard',
        'description' => 'A test room.', 'beds' => 1, 'guests' => 2, 'price_per_night' => 1000,
        'is_active' => true, 'operational_status' => 'available',
    ]);

    return Booking::create([
        'room_id' => $room->id, 'guest_name' => 'Payment Guest', 'guest_email' => 'payment@example.test',
        'guest_phone' => '09171234567', 'check_in' => now()->addDays(3)->toDateString(),
        'check_out' => now()->addDays(4)->toDateString(), 'check_in_at' => now()->addDays(3)->startOfDay(),
        'check_out_at' => now()->addDays(4)->startOfDay(), 'guests' => 1, 'nights' => 1,
        'total_amount' => 1000, 'payment_method' => 'cash', 'payment_status' => 'pending', 'status' => 'pending',
    ]);
}

beforeEach(function () {
    config()->set('services.paymongo.mode', 'test');
    config()->set('services.paymongo.secret_key', 'sk_test_example');
    config()->set('services.paymongo.webhook_secret', 'whsec_test_example');
});

test('a guest can begin an online GCash test checkout', function () {
    $booking = payMongoBooking();
    Http::fake(['https://api.paymongo.com/v1/checkout_sessions' => Http::response([
        'data' => ['id' => 'cs_test_123', 'attributes' => ['checkout_url' => 'https://checkout.paymongo.test/cs_test_123']],
    ])]);

    $this->withSession(['guest_booking_reference' => $booking->reference])
        ->post(route('bookings.paymongo.start', $booking))
        ->assertRedirect('https://checkout.paymongo.test/cs_test_123');

    $payment = Payment::firstOrFail();
    expect($payment->reference)->toBe('paymongo:cs_test_123')->and($payment->status)->toBe('pending');
    expect($booking->fresh()->payment_method)->toBe('gcash');
});

test('hosted checkout identifies the merchant as Carolina instead of the account owner', function () {
    $booking = payMongoBooking();
    Http::fake(['https://api.paymongo.com/v1/checkout_sessions' => Http::response([
        'data' => ['id' => 'cs_test_brand', 'attributes' => ['checkout_url' => 'https://checkout.paymongo.test/cs_test_brand']],
    ])]);

    $this->withSession(['guest_booking_reference' => $booking->reference])
        ->post(route('bookings.paymongo.start', $booking));

    Http::assertSent(function (Request $request) {
        $payload = json_decode($request->body(), true);

        return data_get($payload, 'data.attributes.merchant') === 'Carolina';
    });
});

test('an AJAX checkout request receives a checkout URL without a redirect response', function () {
    $booking = payMongoBooking();
    Http::fake(['https://api.paymongo.com/v1/checkout_sessions' => Http::response([
        'data' => ['id' => 'cs_test_ajax', 'attributes' => ['checkout_url' => 'https://checkout.paymongo.test/cs_test_ajax']],
    ])]);

    $this->withSession(['guest_booking_reference' => $booking->reference])
        ->withHeaders(['Accept' => 'application/json', 'X-Requested-With' => 'XMLHttpRequest'])
        ->post(route('bookings.paymongo.start', $booking))
        ->assertOk()
        ->assertJsonPath('checkout_url', 'https://checkout.paymongo.test/cs_test_ajax');

    expect(Payment::where('booking_id', $booking->id)->firstOrFail()->reference)->toBe('paymongo:cs_test_ajax');
});

test('an AJAX checkout failure returns a visible error response', function () {
    $booking = payMongoBooking();
    Http::fake(['https://api.paymongo.com/v1/checkout_sessions' => Http::response(['errors' => [['detail' => 'Unavailable']]], 503)]);

    $this->withSession(['guest_booking_reference' => $booking->reference])
        ->withHeaders(['Accept' => 'application/json', 'X-Requested-With' => 'XMLHttpRequest'])
        ->post(route('bookings.paymongo.start', $booking))
        ->assertStatus(503)
        ->assertJsonPath('message', 'Online GCash checkout is unavailable right now. Please try again shortly.');

    expect(Payment::where('booking_id', $booking->id)->exists())->toBeFalse();
});

test('a payment is recorded only after the server verifies PayMongo success', function () {
    $booking = payMongoBooking();
    Payment::create(['booking_id' => $booking->id, 'amount' => 1000, 'method' => 'gcash', 'status' => 'pending', 'reference' => 'paymongo:cs_test_123']);
    Http::fake(['https://api.paymongo.com/v1/checkout_sessions/cs_test_123' => Http::response([
        'data' => ['id' => 'cs_test_123', 'attributes' => [
            'livemode' => false,
            'reference_number' => $booking->reference,
            'payments' => [['attributes' => ['amount' => 100000, 'currency' => 'PHP', 'status' => 'paid']]],
        ]],
    ])]);

    $this->withSession(['guest_booking_reference' => $booking->reference])
        ->get(route('bookings.paymongo.return', ['booking' => $booking, 'outcome' => 'success']))
        ->assertRedirect(route('bookings.receipt', $booking));

    expect(Payment::firstOrFail()->fresh()->status)->toBe('paid');
    expect($booking->fresh()->payment_status)->toBe('paid');
});

test('a browser success return cannot mark an unverified payment as paid', function () {
    $booking = payMongoBooking();
    Payment::create(['booking_id' => $booking->id, 'amount' => 1000, 'method' => 'gcash', 'status' => 'pending', 'reference' => 'paymongo:cs_test_123']);
    Http::fake(['https://api.paymongo.com/v1/checkout_sessions/cs_test_123' => Http::response([
        'data' => ['id' => 'cs_test_123', 'attributes' => [
            'livemode' => false,
            'reference_number' => $booking->reference,
            'payments' => [['attributes' => ['amount' => 100000, 'currency' => 'PHP', 'status' => 'awaiting_payment_method']]],
        ]],
    ])]);

    $this->withSession(['guest_booking_reference' => $booking->reference])
        ->get(route('bookings.paymongo.return', ['booking' => $booking, 'outcome' => 'success']))
        ->assertRedirect(route('bookings.receipt', $booking))
        ->assertSessionHasErrors('payment');

    expect(Payment::firstOrFail()->fresh()->status)->toBe('pending');
    expect($booking->fresh()->payment_status)->toBe('pending');
});

test('a signed PayMongo paid event records the full matching payment automatically', function () {
    Mail::fake();
    $booking = payMongoBooking();
    Payment::create(['booking_id' => $booking->id, 'amount' => 1000, 'method' => 'gcash', 'status' => 'pending', 'reference' => 'paymongo:cs_test_123']);

    $response = signedPayMongoWebhookRequest($this, payMongoWebhookPayload($booking));

    $response->assertOk()->assertJsonPath('received', true);
    expect(Payment::firstOrFail()->fresh()->status)->toBe('paid');
    expect($booking->fresh()->payment_status)->toBe('paid');
    Mail::assertSent(BookingUpdate::class, 1);
});

test('a duplicate PayMongo paid event does not duplicate payment activity or email', function () {
    Mail::fake();
    $booking = payMongoBooking();
    Payment::create(['booking_id' => $booking->id, 'amount' => 1000, 'method' => 'gcash', 'status' => 'pending', 'reference' => 'paymongo:cs_test_123']);
    $payload = payMongoWebhookPayload($booking);

    signedPayMongoWebhookRequest($this, $payload)->assertOk();
    signedPayMongoWebhookRequest($this, $payload)->assertOk();

    expect(Payment::where('booking_id', $booking->id)->count())->toBe(1);
    expect($booking->activityLogs()->where('event', 'payment_paid')->count())->toBe(1);
    Mail::assertSent(BookingUpdate::class, 1);
});

test('an invalid PayMongo webhook signature cannot mark a booking paid', function () {
    $booking = payMongoBooking();
    Payment::create(['booking_id' => $booking->id, 'amount' => 1000, 'method' => 'gcash', 'status' => 'pending', 'reference' => 'paymongo:cs_test_123']);
    $payload = payMongoWebhookPayload($booking);
    $rawBody = json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);

    $this->call('POST', route('bookings.paymongo.webhook'), [], [], [], [
        'CONTENT_TYPE' => 'application/json',
        'HTTP_PAYMONGO_SIGNATURE' => 't=1,te=invalid,li=',
    ], $rawBody)->assertUnauthorized();

    expect(Payment::firstOrFail()->fresh()->status)->toBe('pending');
    expect($booking->fresh()->payment_status)->toBe('pending');
});

test('a signed PayMongo event cannot mark a booking paid for a different amount', function () {
    $booking = payMongoBooking();
    Payment::create(['booking_id' => $booking->id, 'amount' => 1000, 'method' => 'gcash', 'status' => 'pending', 'reference' => 'paymongo:cs_test_123']);
    $payload = payMongoWebhookPayload($booking, [
        'data' => ['attributes' => ['data' => ['attributes' => ['payments' => [[
            'attributes' => ['amount' => 50000],
        ]]]]]],
    ]);

    signedPayMongoWebhookRequest($this, $payload)->assertOk();

    expect(Payment::firstOrFail()->fresh()->status)->toBe('pending');
    expect($booking->fresh()->payment_status)->toBe('pending');
});

test('a signed PayMongo event for a different booking cannot update this reservation', function () {
    $booking = payMongoBooking();
    Payment::create(['booking_id' => $booking->id, 'amount' => 1000, 'method' => 'gcash', 'status' => 'pending', 'reference' => 'paymongo:cs_test_123']);
    $payload = payMongoWebhookPayload($booking, [
        'data' => ['attributes' => ['data' => ['attributes' => ['reference_number' => 'CAR-OTHER']]]],
    ]);

    signedPayMongoWebhookRequest($this, $payload)->assertOk()->assertJsonPath('matched', false);

    expect(Payment::firstOrFail()->fresh()->status)->toBe('pending')
        ->and($booking->fresh()->payment_status)->toBe('pending');
});

test('the guest return succeeds when the webhook already recorded the PayMongo payment', function () {
    $booking = payMongoBooking();
    Payment::create(['booking_id' => $booking->id, 'amount' => 1000, 'method' => 'gcash', 'status' => 'pending', 'reference' => 'paymongo:cs_test_123']);
    signedPayMongoWebhookRequest($this, payMongoWebhookPayload($booking))->assertOk();

    $this->withSession(['guest_booking_reference' => $booking->reference])
        ->get(route('bookings.paymongo.return', ['booking' => $booking, 'outcome' => 'success']))
        ->assertRedirect(route('bookings.receipt', $booking))
        ->assertSessionHasNoErrors();

    expect(Payment::where('booking_id', $booking->id)->count())->toBe(1);
    expect($booking->activityLogs()->where('event', 'payment_paid')->count())->toBe(1);
});

test('PayMongo payments can only be marked paid by a verified provider result', function () {
    $booking = payMongoBooking();
    $payment = Payment::create(['booking_id' => $booking->id, 'amount' => 1000, 'method' => 'gcash', 'status' => 'pending', 'reference' => 'paymongo:cs_test_123']);
    $admin = User::factory()->create(['staff_role' => 'admin', 'is_admin' => true]);

    $this->actingAsStaff($admin)->from(route('admin.bookings'))
        ->patch(route('admin.bookings.payment', $booking), ['payment_status' => 'paid', 'payment_method' => 'gcash'])
        ->assertSessionHasErrors('payment_status');

    expect($payment->fresh()->status)->toBe('pending')
        ->and($booking->fresh()->payment_status)->toBe('pending');
});

test('the bookings screen shows provider verification instead of a manual paid toggle', function () {
    $booking = payMongoBooking();
    Payment::create(['booking_id' => $booking->id, 'amount' => 1000, 'method' => 'gcash', 'status' => 'pending', 'reference' => 'paymongo:cs_test_123']);
    $admin = User::factory()->create(['staff_role' => 'admin', 'is_admin' => true]);

    $this->actingAsStaff($admin)->get(route('admin.bookings'))
        ->assertOk()
        ->assertSee('Verify with PayMongo')
        ->assertDontSee('Mark paid');
});

test('admins can verify a pending PayMongo checkout directly with PayMongo', function () {
    Mail::fake();
    $booking = payMongoBooking();
    $payment = Payment::create(['booking_id' => $booking->id, 'amount' => 1000, 'method' => 'gcash', 'status' => 'pending', 'reference' => 'paymongo:cs_test_123']);
    $admin = User::factory()->create(['staff_role' => 'admin', 'is_admin' => true]);
    Http::fake(['https://api.paymongo.com/v1/checkout_sessions/cs_test_123' => Http::response([
        'data' => ['id' => 'cs_test_123', 'attributes' => [
            'livemode' => false,
            'reference_number' => $booking->reference,
            'payments' => [['attributes' => ['amount' => 100000, 'currency' => 'PHP', 'status' => 'paid']]],
        ]],
    ])]);

    $this->actingAsStaff($admin)->from(route('admin.bookings'))
        ->post(route('admin.bookings.payment.recheck-paymongo', $booking))
        ->assertRedirect(route('admin.bookings'))
        ->assertSessionHas('success');

    expect($payment->fresh()->status)->toBe('paid')
        ->and($booking->fresh()->payment_status)->toBe('paid');
    Mail::assertSent(BookingUpdate::class, 1);
});

test('a missing PayMongo webhook secret fails closed', function () {
    config()->set('services.paymongo.webhook_secret', null);
    $booking = payMongoBooking();
    Payment::create(['booking_id' => $booking->id, 'amount' => 1000, 'method' => 'gcash', 'status' => 'pending', 'reference' => 'paymongo:cs_test_123']);

    signedPayMongoWebhookRequest($this, payMongoWebhookPayload($booking))->assertStatus(503);

    expect($booking->fresh()->payment_status)->toBe('pending');
});
