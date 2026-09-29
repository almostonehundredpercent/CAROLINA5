<?php

use App\Models\Booking;
use App\Models\Payment;
use App\Models\Room;
use Illuminate\Support\Facades\Http;

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
        'data' => ['attributes' => ['payment_intent' => ['attributes' => ['status' => 'succeeded']]]],
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
        'data' => ['attributes' => ['payment_intent' => ['attributes' => ['status' => 'awaiting_payment_method']]]],
    ])]);

    $this->withSession(['guest_booking_reference' => $booking->reference])
        ->get(route('bookings.paymongo.return', ['booking' => $booking, 'outcome' => 'success']))
        ->assertRedirect(route('bookings.receipt', $booking))
        ->assertSessionHasErrors('payment');

    expect(Payment::firstOrFail()->fresh()->status)->toBe('pending');
    expect($booking->fresh()->payment_status)->toBe('pending');
});
