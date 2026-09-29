<?php

use App\Models\PromoCode;
use App\Models\Room;
use App\Support\PromoPricing;
use Illuminate\Validation\ValidationException;

function promoRoom(string $suffix = ''): Room
{
    return Room::create([
        'name' => 'Promo room '.$suffix.uniqid(),
        'slug' => 'promo-room-'.$suffix.uniqid(),
        'room_type' => 'Fan room',
        'description' => 'Promo test room.',
        'beds' => 1,
        'guests' => 2,
        'price_per_night' => 450,
        'rate_label' => 'per 22-hour stay',
        'rental_hours' => 22,
        'is_active' => true,
    ]);
}

test('monthly promo sets a selected room thirty-day stay to six thousand pesos', function () {
    $room = promoRoom('eligible');
    $promo = PromoCode::create([
        'code' => 'TESTMONTH6000',
        'name' => 'One month for ₱6,000',
        'discount_type' => 'fixed_total',
        'discount_value' => 6000,
        'minimum_hours' => 720,
        'maximum_hours' => 720,
        'is_active' => true,
    ]);
    $promo->rooms()->attach($room);

    $quote = PromoPricing::quote('testmonth6000', $room, 720, 13500);

    expect($quote['total'])->toBe(6000.0)
        ->and($quote['discount'])->toBe(7500.0)
        ->and($quote['promo']->is($promo))->toBeTrue();
});

test('monthly promo rejects shorter stays and rooms outside the offer', function () {
    $eligibleRoom = promoRoom('eligible');
    $otherRoom = promoRoom('other');
    $promo = PromoCode::create([
        'code' => 'LIMITEDMONTH',
        'name' => 'Limited monthly offer',
        'discount_type' => 'fixed_total',
        'discount_value' => 6000,
        'minimum_hours' => 720,
        'maximum_hours' => 720,
        'is_active' => true,
    ]);
    $promo->rooms()->attach($eligibleRoom);

    expect(fn () => PromoPricing::quote('LIMITEDMONTH', $eligibleRoom, 168, 3150))->toThrow(ValidationException::class)
        ->and(fn () => PromoPricing::quote('LIMITEDMONTH', $otherRoom, 720, 13500))->toThrow(ValidationException::class);
});
