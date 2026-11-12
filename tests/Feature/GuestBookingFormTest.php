<?php

use App\Models\Room;
use Carbon\Carbon;

test('guest booking form shows useful examples in contact and special request fields', function () {
    $room = Room::create([
        'name' => 'Guest Form Test Room',
        'slug' => 'guest-form-test-room',
        'room_type' => 'Standard',
        'description' => 'A room used to test the guest booking form.',
        'beds' => 1,
        'guests' => 2,
        'price_per_night' => 1000,
        'is_active' => true,
        'operational_status' => 'available',
    ]);

    $this->get(route('bookings.create', ['room' => $room, 'guest' => 1]))
        ->assertOk()
        ->assertSee('placeholder="e.g., Maria Santos"', false)
        ->assertSee('placeholder="e.g., maria@example.com"', false)
        ->assertSee('placeholder="09XX XXX XXXX"', false)
        ->assertSee('title="Enter a Philippine mobile number, such as 09XX XXX XXXX or +63 9XX XXX XXXX."', false)
        ->assertSee('placeholder="e.g., Extra pillow, if available."', false);
});

test('guest details autosaved during booking are restored after navigating away', function () {
    $room = Room::create([
        'name' => 'Guest Draft Test Room',
        'slug' => 'guest-draft-test-room',
        'room_type' => 'Standard',
        'description' => 'A room used to test guest draft persistence.',
        'beds' => 1,
        'guests' => 2,
        'price_per_night' => 1000,
        'is_active' => true,
        'operational_status' => 'available',
    ]);

    $draftUrl = route('bookings.guest-draft.save');
    $this->postJson($draftUrl, [
        'guest_name' => 'Maria Example',
        'guest_email' => 'maria@example.test',
        'guest_phone' => '09171234567',
    ])->assertNoContent();

    $this->get(route('bookings.create', ['room' => $room]))
        ->assertOk()
        ->assertDontSee('name="guest_name" value="Maria Example"', false);

    $this->get(route('bookings.create', ['room' => $room, 'guest' => 1]))
        ->assertOk()
        ->assertSee('name="guest_name" value="Maria Example"', false)
        ->assertSee('name="guest_email" value="maria@example.test"', false)
        ->assertSee('name="guest_phone" value="09171234567"', false)
        ->assertSee('data-guest-draft-url="'.$draftUrl.'"', false);
});

test('expired guest booking details are not restored', function () {
    $room = Room::create([
        'name' => 'Expired Draft Test Room',
        'slug' => 'expired-draft-test-room',
        'room_type' => 'Standard',
        'description' => 'A room used to test guest draft expiration.',
        'beds' => 1,
        'guests' => 2,
        'price_per_night' => 1000,
        'is_active' => true,
        'operational_status' => 'available',
    ]);
    $this->travelTo(Carbon::parse('2026-11-12 10:00:00'));
    $this->withSession(['guest_booking_draft' => [
        'guest_name' => 'Expired Example',
        'guest_email' => 'expired@example.test',
        'guest_phone' => '09171234567',
        'expires_at' => now()->subSecond()->timestamp,
    ]]);

    $this->get(route('bookings.create', ['room' => $room, 'guest' => 1]))
        ->assertOk()
        ->assertDontSee('name="guest_name" value="Expired Example"', false)
        ->assertSessionMissing('guest_booking_draft');
});

test('promo code disclosure uses a distinct solid surface in light and dark mode', function () {
    $room = Room::create([
        'name' => 'Promo Surface Test Room',
        'slug' => 'promo-surface-test-room',
        'room_type' => 'Standard',
        'description' => 'A room used to check promo control contrast.',
        'beds' => 1,
        'guests' => 2,
        'price_per_night' => 1000,
        'is_active' => true,
        'operational_status' => 'available',
    ]);

    $response = $this->get(route('bookings.create', ['room' => $room, 'guest' => 1]))->assertOk();
    $html = $response->getContent();
    preg_match_all('/\.promo-entry>summary\s*\{([^}]*)\}/', $html, $summaryRules);
    preg_match_all('/\.dark-mode \.promo-entry>summary\s*\{([^}]*)\}/', $html, $darkSummaryRules);
    preg_match_all('/\.promo-entry\[open\]\s*\{([^}]*)\}/', $html, $expandedRules);
    preg_match_all('/\.dark-mode \.promo-entry\[open\]\s*\{([^}]*)\}/', $html, $darkExpandedRules);

    expect(implode(';', $summaryRules[1] ?? []))->toContain('background:var(--auth-canvas)')
        ->and(implode(';', $darkSummaryRules[1] ?? []))->toContain('background:var(--deep)')
        ->and(implode(';', $expandedRules[1] ?? []))->toContain('background:var(--auth-canvas)')
        ->and(implode(';', $darkExpandedRules[1] ?? []))->toContain('background:var(--deep)');
});

test('invalid guest phone is explained inline while submitted guest details are retained', function () {
    $room = Room::create([
        'name' => 'Validation Test Room',
        'slug' => 'validation-test-room',
        'room_type' => 'Standard',
        'description' => 'A room used to test booking validation.',
        'beds' => 1,
        'guests' => 2,
        'price_per_night' => 1000,
        'is_active' => true,
        'operational_status' => 'available',
    ]);
    $formUrl = route('bookings.create', ['room' => $room, 'guest' => 1]);

    $this->from($formUrl)->post(route('bookings.store', $room), [
        'checkout_type' => 'guest',
        'booking_type' => 'dates',
        'submission_token' => (string) \Illuminate\Support\Str::uuid(),
        'terms_accepted' => '1',
        'check_in' => now('Asia/Manila')->addDays(3)->toDateString(),
        'check_out' => now('Asia/Manila')->addDays(4)->toDateString(),
        'guests' => 1,
        'guest_name' => 'Maria Example',
        'guest_email' => 'maria@example.test',
        'guest_phone' => '123',
    ])->assertRedirect($formUrl)->assertSessionHasErrors('guest_phone');

    $this->get($formUrl)
        ->assertOk()
        ->assertSee('id="booking-validation-summary"', false)
        ->assertSee('The guest phone field format is invalid.')
        ->assertSee('id="guest-phone-error"', false)
        ->assertSee('name="guest_name" value="Maria Example"', false);
});
