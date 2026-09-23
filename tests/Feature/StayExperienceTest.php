<?php

use App\Models\{Booking, Room, User};
use App\Http\Controllers\StayController;
use Illuminate\Support\Facades\{DB, URL};

function arrivalBooking(): Booking {
    $room = Room::create(['name'=>'Arrival room','slug'=>'arrival-room','description'=>'Test accommodation','room_type'=>'Standard','beds'=>1,'guests'=>2,'price_per_night'=>500,'is_active'=>true,'operational_status'=>'available']);
    return Booking::create(['room_id'=>$room->id,'guest_name'=>'Arrival Guest','guest_email'=>'arrival@example.test','check_in'=>today()->addDay(),'check_out'=>today()->addDays(2),'check_in_at'=>now()->addDay(),'check_out_at'=>now()->addDays(2),'guests'=>1,'nights'=>1,'total_amount'=>500,'payment_method'=>'cash','status'=>'confirmed']);
}

test('private arrival links require a valid unexpired signature', function () {
    $booking = arrivalBooking();
    $this->get(route('stay.show', $booking))->assertForbidden();
    $this->get(StayController::link($booking))->assertOk()->assertSee('A little less to worry about.');
    $this->get(URL::temporarySignedRoute('stay.show', now()->subMinute(), ['booking'=>$booking->id]))->assertForbidden();
});

test('arrival updates never cancel bookings and reject completed stays', function () {
    $booking = arrivalBooking();
    $link = URL::temporarySignedRoute('stay.arrival', now()->addHour(), ['booking'=>$booking->id]);
    $this->post($link, ['arrival_update'=>'cannot_make_it'])->assertRedirect();
    expect($booking->fresh()->arrival_update)->toBe('cannot_make_it')->and($booking->fresh()->status)->toBe('confirmed');
    $booking->update(['checked_out_at'=>now()]);
    $this->post($link, ['arrival_update'=>'on_time'])->assertStatus(409);
});

test('only reception roles publish readiness and notices', function () {
    $booking = arrivalBooking();
    $viewer = User::factory()->create(['staff_role'=>'viewer']);
    $this->actingAs($viewer)->get(route('admin.arrivals'))->assertForbidden();
    $this->actingAs($viewer)->patch(route('admin.arrivals.update', $booking), ['readiness'=>'ready','bag_drop_available'=>0])->assertForbidden();
    $staff = User::factory()->create(['staff_role'=>'front_desk']);
    $this->actingAs($staff)->get(route('admin.arrivals'))->assertOk();
    $this->patch(route('admin.arrivals.update', $booking), ['readiness'=>'ready','bag_drop_available'=>1])->assertRedirect();
    expect($booking->fresh()->readiness)->toBe('ready');
    $booking->room->update(['operational_status'=>'cleaning']);
    $this->patch(route('admin.arrivals.update', $booking), ['readiness'=>'ready','bag_drop_available'=>1])->assertStatus(422);
});

test('guest notices respect room scope expiry and resolution', function () {
    $booking = arrivalBooking();
    $staff = User::factory()->create(['staff_role'=>'front_desk']);
    $data = ['room_id'=>$booking->room_id,'type'=>'water','title'=>'Water advisory','message'=>'Reception can assist.','starts_at'=>now()->subMinute()->toDateTimeString(),'ends_at'=>now()->addHour()->toDateTimeString()];
    $this->actingAs($staff)->post(route('admin.stay-notices.store'), $data)->assertRedirect();
    $this->get(StayController::link($booking))->assertSee('Water advisory');
    $id = DB::table('stay_notices')->value('id');
    $this->patch(route('admin.stay-notices.resolve', $id))->assertRedirect();
    $this->get(StayController::link($booking))->assertDontSee('Water advisory');
    DB::table('stay_notices')->where('id',$id)->update(['active'=>true,'ends_at'=>now()->subMinute()]);
    $this->get(StayController::link($booking))->assertDontSee('Water advisory');
});
