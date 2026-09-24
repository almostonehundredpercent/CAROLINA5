<?php

use App\Mail\BookingUpdate;
use App\Models\ActivityLog;
use App\Models\Booking;
use App\Models\Payment;
use App\Models\Room;
use App\Models\User;
use Illuminate\Support\Facades\Mail;

function refundWorkflowBooking(): Booking
{
    $room = Room::create([
        'name' => 'Refund workflow room '.uniqid(),
        'slug' => 'refund-workflow-'.uniqid(),
        'room_type' => 'Test',
        'description' => 'Refund workflow test room.',
        'beds' => 1,
        'guests' => 2,
        'price_per_night' => 1000,
        'is_active' => true,
    ]);

    return Booking::create([
        'room_id' => $room->id,
        'guest_name' => 'Refund guest',
        'guest_email' => uniqid().'@example.test',
        'guest_phone' => '09171234567',
        'check_in' => now()->addDay()->toDateString(),
        'check_out' => now()->addDays(2)->toDateString(),
        'check_in_at' => now()->addDay()->startOfHour(),
        'check_out_at' => now()->addDays(2)->startOfHour(),
        'guests' => 1,
        'nights' => 1,
        'total_amount' => 1000,
        'payment_method' => 'cash',
        'payment_status' => 'paid',
        'status' => 'confirmed',
    ]);
}

test('staff can record a partial refund with a reason and guest notification', function () {
    Mail::fake();
    $staff = User::factory()->create(['is_admin' => true]);
    $booking = refundWorkflowBooking();
    Payment::create(['booking_id' => $booking->id, 'amount' => 1000, 'method' => 'cash', 'status' => 'paid', 'paid_at' => now()]);

    $this->actingAs($staff)->post(route('admin.bookings.refunds.store', $booking), [
        'amount' => '250.00',
        'reason' => 'Guest cancelled before arrival.',
    ])->assertRedirect()->assertSessionHas('success');

    expect(Payment::where('booking_id', $booking->id)->where('status', 'refunded')->value('amount'))->toEqual('250.00');
    expect($booking->fresh()->payment_status)->toBe('paid');
    expect(ActivityLog::where('booking_id', $booking->id)->where('event', 'payment_refunded')->exists())->toBeTrue();
    Mail::assertSent(BookingUpdate::class);
});

test('a refund cannot exceed the amount actually recorded as paid', function () {
    $staff = User::factory()->create(['is_admin' => true]);
    $booking = refundWorkflowBooking();
    Payment::create(['booking_id' => $booking->id, 'amount' => 500, 'method' => 'cash', 'status' => 'paid', 'paid_at' => now()]);

    $this->actingAs($staff)->from(route('admin.bookings'))
        ->post(route('admin.bookings.refunds.store', $booking), ['amount' => 500.01, 'reason' => 'Too much'])
        ->assertRedirect(route('admin.bookings'))
        ->assertSessionHasErrors('amount');

    expect(Payment::where('booking_id', $booking->id)->where('status', 'refunded')->exists())->toBeFalse();
});
