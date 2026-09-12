<?php

use App\Models\Room;
use App\Models\User;
use Illuminate\Support\Facades\Hash;

function staffAccount(string $role): User
{
    return User::factory()->create([
        'staff_role' => $role,
        'is_admin' => $role === 'admin',
        'password' => Hash::make('secure-password'),
    ]);
}

test('each role is sent to its authorized landing page after login', function (string $role, string $route) {
    $user = staffAccount($role);
    $this->post(route('login.submit'), ['email' => $user->email, 'password' => 'secure-password'])
        ->assertRedirect(route($route));
})->with([
    'administrator' => ['admin', 'admin.dashboard'],
    'front desk' => ['front_desk', 'admin.frontdesk'],
    'housekeeping' => ['housekeeping', 'admin.rooms'],
    'viewer' => ['viewer', 'admin.dashboard'],
    'guest' => ['guest', 'home'],
]);

test('role boundaries are enforced on direct administrative URLs', function () {
    $admin = staffAccount('admin');
    $frontDesk = staffAccount('front_desk');
    $housekeeping = staffAccount('housekeeping');
    $viewer = staffAccount('viewer');
    $guest = staffAccount('guest');

    $this->actingAs($admin)->get(route('admin.reports'))->assertOk();
    $this->actingAs($frontDesk)->get(route('admin.bookings'))->assertOk();
    $this->actingAs($frontDesk)->get(route('admin.reports'))->assertForbidden();
    $this->actingAs($frontDesk)->patch('/admin/guests/not-a-guest/restriction', ['action' => 'remove'])->assertForbidden();
    $this->actingAs($housekeeping)->get(route('admin.rooms'))->assertOk();
    $this->actingAs($housekeeping)->get(route('admin.bookings'))->assertForbidden();
    $this->actingAs($viewer)->get(route('admin.dashboard'))->assertOk();
    $this->actingAs($viewer)->get(route('admin.rooms'))->assertOk();
    $this->actingAs($viewer)->get(route('admin.bookings'))->assertForbidden();
    $this->actingAs($guest)->get(route('admin.dashboard'))->assertForbidden();
    $this->post(route('logout'));
    $this->get(route('admin.dashboard'))->assertRedirect(route('login'));
});

test('only operational staff can change room operations', function () {
    $room = Room::create(['name' => 'Authorization room', 'slug' => 'authorization-room', 'room_type' => 'Test', 'description' => 'Test room.', 'beds' => 1, 'guests' => 2, 'price_per_night' => 500, 'is_active' => true]);
    $frontDesk = staffAccount('front_desk');
    $viewer = staffAccount('viewer');
    $housekeeping = staffAccount('housekeeping');

    $payload = ['operational_status' => 'cleaning', 'operational_starts_at' => now()->addHour()->toDateTimeString(), 'operational_until' => now()->addHours(2)->toDateTimeString()];
    $this->actingAs($frontDesk)->patch(route('admin.rooms.status', $room), $payload)->assertRedirect();
    $this->actingAs($viewer)->patch(route('admin.rooms.status', $room), $payload)->assertForbidden();
    $this->actingAs($housekeeping)->patch(route('admin.rooms.status', $room), $payload)->assertRedirect();
});
