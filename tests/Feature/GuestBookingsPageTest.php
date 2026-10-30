<?php

use App\Models\Booking;
use App\Models\Room;
use App\Models\User;
use Illuminate\Support\Facades\Http;

test('a checked-out guest booking renders separate action and review sections', function () {
    $user = User::factory()->create(['staff_role' => 'guest', 'is_admin' => false]);
    $room = Room::create([
        'name' => 'My bookings layout room',
        'slug' => 'my-bookings-layout-room',
        'room_type' => 'Standard',
        'description' => 'A room for the guest bookings layout test.',
        'beds' => 1,
        'guests' => 2,
        'price_per_night' => 450,
        'is_active' => true,
        'operational_status' => 'available',
    ]);
    Booking::create([
        'user_id' => $user->id,
        'room_id' => $room->id,
        'guest_name' => 'Guest Layout Test',
        'guest_email' => $user->email,
        'guest_phone' => '09XX XXX XXXX',
        'check_in' => '2026-10-21',
        'check_out' => '2026-10-23',
        'check_in_at' => '2026-10-21 06:00:00',
        'check_out_at' => '2026-10-23 06:00:00',
        'booking_type' => 'overnight',
        'hours' => 48,
        'guests' => 1,
        'nights' => 2,
        'total_amount' => 900,
        'status' => 'confirmed',
        'payment_status' => 'paid',
        'payment_method' => 'gcash',
        'checked_out_at' => '2026-10-23 06:00:00',
    ]);

    $response = $this->actingAs($user)->get(route('bookings.index'))
        ->assertOk()
        ->assertSee('class="booking-actions"', false)
        ->assertSee('class="booking-review-area"', false)
        ->assertSee('View details')
        ->assertSee('Leave a review');

    $document = new DOMDocument;
    @$document->loadHTML($response->getContent());
    $xpath = new DOMXPath($document);
    expect($xpath->query('//fieldset[contains(concat(" ", normalize-space(@class), " "), " review-rating ")]')->length)->toBe(4);

    foreach (['rating', 'cleanliness_rating', 'comfort_rating', 'value_rating'] as $name) {
        expect($xpath->query('//input[@type="radio" and @name="'.$name.'"]')->length)->toBe(5);
    }

    expect($xpath->query('//input[@type="radio" and @name="rating" and @required]')->length)->toBe(5)
        ->and($xpath->query('//input[@type="radio" and @name!="rating" and @required]')->length)->toBe(0)
        ->and($xpath->query('//select[@name="rating" or @name="cleanliness_rating" or @name="comfort_rating" or @name="value_rating"]')->length)->toBe(0);
});

test('signed-in guest can resume payment for an active unpaid booking', function () {
    $user = User::factory()->create(['staff_role' => 'guest', 'is_admin' => false]);
    $room = Room::create([
        'name' => 'Unpaid account room', 'slug' => 'unpaid-account-room', 'room_type' => 'Standard',
        'description' => 'A room for payment recovery.', 'beds' => 1, 'guests' => 2,
        'price_per_night' => 450, 'is_active' => true, 'operational_status' => 'available',
    ]);
    $booking = Booking::create([
        'user_id' => $user->id, 'room_id' => $room->id, 'guest_name' => 'Account Guest',
        'guest_email' => $user->email, 'guest_phone' => '09XX XXX XXXX',
        'check_in' => '2026-11-10', 'check_out' => '2026-11-11', 'check_in_at' => '2026-11-10 12:00:00',
        'check_out_at' => '2026-11-11 12:00:00', 'booking_type' => 'overnight', 'hours' => 24,
        'guests' => 1, 'nights' => 1, 'total_amount' => 450, 'status' => 'pending',
        'payment_status' => 'pending', 'payment_method' => 'gcash', 'hold_expires_at' => now()->addMinutes(20),
    ]);

    $this->actingAs($user)->get(route('bookings.index'))
        ->assertOk()
        ->assertSee('Continue payment')
        ->assertSee(route('bookings.receipt', $booking), false);
});

test('email and reference lookup can resume an unpaid booking', function () {
    $room = Room::create([
        'name' => 'Unpaid lookup room', 'slug' => 'unpaid-lookup-room', 'room_type' => 'Standard',
        'description' => 'A room for payment lookup.', 'beds' => 1, 'guests' => 2,
        'price_per_night' => 450, 'is_active' => true, 'operational_status' => 'available',
    ]);
    $booking = Booking::create([
        'room_id' => $room->id, 'guest_name' => 'Lookup Guest', 'guest_email' => 'lookup@example.test',
        'guest_phone' => '09XX XXX XXXX', 'check_in' => '2026-11-10', 'check_out' => '2026-11-11',
        'check_in_at' => '2026-11-10 12:00:00', 'check_out_at' => '2026-11-11 12:00:00',
        'booking_type' => 'overnight', 'hours' => 24, 'guests' => 1, 'nights' => 1,
        'total_amount' => 450, 'status' => 'pending', 'payment_status' => 'pending',
        'payment_method' => 'gcash', 'hold_expires_at' => now()->addMinutes(20),
    ]);

    $this->get(route('bookings.lookup', ['reference' => $booking->reference]))
        ->assertOk()
        ->assertSee('name="reference" placeholder="CAR-XXXXXXXX" value="'.$booking->reference.'"', false);

    $this->post(route('bookings.lookup.submit'), ['email' => 'lookup@example.test', 'reference' => $booking->reference])
        ->assertOk()
        ->assertSee('Continue payment')
        ->assertSee(route('bookings.receipt', $booking), false);
});

test('email and reference lookup lets an account guest open the payment receipt', function () {
    config()->set('services.paymongo.mode', 'test');
    config()->set('services.paymongo.secret_key', 'sk_test_example');
    $user = User::factory()->create(['staff_role' => 'guest', 'is_admin' => false]);
    $room = Room::create([
        'name' => 'Account lookup room', 'slug' => 'account-lookup-room', 'room_type' => 'Standard',
        'description' => 'A room for account booking recovery.', 'beds' => 1, 'guests' => 2,
        'price_per_night' => 450, 'is_active' => true, 'operational_status' => 'available',
    ]);
    $booking = Booking::create([
        'user_id' => $user->id, 'room_id' => $room->id, 'guest_name' => 'Account Lookup Guest',
        'guest_email' => $user->email, 'guest_phone' => '09XX XXX XXXX',
        'check_in' => '2026-11-10', 'check_out' => '2026-11-11',
        'check_in_at' => '2026-11-10 12:00:00', 'check_out_at' => '2026-11-11 12:00:00',
        'booking_type' => 'overnight', 'hours' => 24, 'guests' => 1, 'nights' => 1,
        'total_amount' => 450, 'status' => 'pending', 'payment_status' => 'pending',
        'payment_method' => 'gcash', 'hold_expires_at' => now()->addMinutes(20),
    ]);

    $this->post(route('bookings.lookup.submit'), ['email' => $user->email, 'reference' => $booking->reference])
        ->assertOk()
        ->assertSee('Continue payment');

    $this->get(route('bookings.receipt', $booking))
        ->assertOk()
        ->assertSee('id="paymongo-checkout-form"', false)
        ->assertSee('Pay ₱450.00 with GCash');

    Http::fake(['https://api.paymongo.com/v1/checkout_sessions' => Http::response([
        'data' => ['id' => 'cs_account_lookup', 'attributes' => ['checkout_url' => 'https://checkout.paymongo.test/cs_account_lookup']],
    ])]);

    $this->withHeaders(['Accept' => 'application/json', 'X-Requested-With' => 'XMLHttpRequest'])
        ->post(route('bookings.paymongo.start', $booking))
        ->assertOk()
        ->assertJsonPath('checkout_url', 'https://checkout.paymongo.test/cs_account_lookup');
});

test('expired hold recovery shows the released room state without payment controls', function () {
    $user = User::factory()->create(['staff_role' => 'guest', 'is_admin' => false]);
    $room = Room::create([
        'name' => 'Expired guest room', 'slug' => 'expired-guest-room', 'room_type' => 'Standard',
        'description' => 'An expired guest room.', 'beds' => 1, 'guests' => 2,
        'price_per_night' => 450, 'is_active' => true, 'operational_status' => 'available',
    ]);
    $booking = Booking::create([
        'user_id' => $user->id, 'room_id' => $room->id, 'guest_name' => 'Expired Guest',
        'guest_email' => $user->email, 'guest_phone' => '09XX XXX XXXX', 'check_in' => '2026-11-10',
        'check_out' => '2026-11-11', 'check_in_at' => '2026-11-10 12:00:00', 'check_out_at' => '2026-11-11 12:00:00',
        'booking_type' => 'overnight', 'hours' => 24, 'guests' => 1, 'nights' => 1, 'total_amount' => 450,
        'status' => 'cancelled', 'payment_status' => 'failed', 'payment_method' => 'gcash',
        'hold_expires_at' => now()->subMinute(), 'cancelled_at' => now()->subMinute(),
        'cancellation_reason' => 'Unpaid reservation hold expired.',
    ]);

    $this->actingAs($user)->get(route('bookings.index'))
        ->assertOk()
        ->assertSee('Payment hold expired')
        ->assertSee(route('bookings.receipt', $booking), false)
        ->assertDontSee('Continue payment');
});
