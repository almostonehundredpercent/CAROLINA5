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

test('staff must set up mfa after password login while guests reach home', function (string $role, string $destination) {
    $user = staffAccount($role);
    $this->post(route('login.submit'), ['email' => $user->email, 'password' => 'secure-password'])
        ->assertRedirect($destination);
})->with([
    'administrator' => ['admin', '/staff/mfa/setup'],
    'front desk' => ['front_desk', '/staff/mfa/setup'],
    'housekeeping' => ['housekeeping', '/staff/mfa/setup'],
    'viewer' => ['viewer', '/staff/mfa/setup'],
    'guest' => ['guest', '/'],
]);

test('role boundaries are enforced on direct administrative URLs', function () {
    $admin = staffAccount('admin');
    $frontDesk = staffAccount('front_desk');
    $housekeeping = staffAccount('housekeeping');
    $viewer = staffAccount('viewer');
    $guest = staffAccount('guest');

    $this->actingAsStaff($admin)->get(route('admin.reports'))->assertOk();
    $this->actingAsStaff($frontDesk)->get(route('admin.bookings'))->assertOk();
    $this->actingAsStaff($frontDesk)->get(route('admin.reports'))->assertForbidden();
    $this->actingAsStaff($frontDesk)->patch('/admin/guests/not-a-guest/restriction', ['action' => 'remove'])->assertForbidden();
    $this->actingAsStaff($housekeeping)->get(route('admin.rooms'))->assertOk();
    $this->actingAsStaff($housekeeping)->get(route('admin.bookings'))->assertForbidden();
    $this->actingAsStaff($viewer)->get(route('admin.dashboard'))->assertOk();
    $this->actingAsStaff($viewer)->get(route('admin.rooms'))->assertOk();
    $this->actingAsStaff($viewer)->get(route('admin.bookings'))->assertForbidden();
    $this->actingAs($guest)->get(route('admin.dashboard'))->assertForbidden();
    $this->post(route('logout'));
    $this->get(route('admin.dashboard'))->assertRedirect(route('login'));
});

test('admin navigation shows every section and a home link without a More menu', function () {
    $admin = staffAccount('admin');

    $response = $this->actingAsStaff($admin)->get(route('admin.frontdesk'));

    $response->assertOk()
        ->assertSee(route('home'), false)
        ->assertSee('class="admin-home-link"', false)
        ->assertSee('Home</a>', false)
        ->assertSee('Today')
        ->assertSee('Bookings')
        ->assertSee('Rooms')
        ->assertSee('Guests')
        ->assertSee('Overview')
        ->assertSee('Reports')
        ->assertSee('CAROL · Decision support')
        ->assertSee('Promo codes')
        ->assertSee('Activity log')
        ->assertSee('Staff access')
        ->assertDontSee('admin-more', false)
        ->assertDontSee('<summary>More', false);
});

test('only operational staff can change room operations', function () {
    $room = Room::create(['name' => 'Authorization room', 'slug' => 'authorization-room', 'room_type' => 'Test', 'description' => 'Test room.', 'beds' => 1, 'guests' => 2, 'price_per_night' => 500, 'is_active' => true]);
    $frontDesk = staffAccount('front_desk');
    $viewer = staffAccount('viewer');
    $housekeeping = staffAccount('housekeeping');

    $payload = ['operational_status' => 'cleaning', 'operational_starts_at' => now()->addHour()->toDateTimeString(), 'operational_until' => now()->addHours(2)->toDateTimeString()];
    $this->actingAsStaff($frontDesk)->patch(route('admin.rooms.status', $room), $payload)->assertRedirect();
    $this->actingAsStaff($viewer)->patch(route('admin.rooms.status', $room), $payload)->assertForbidden();
    $this->actingAsStaff($housekeeping)->patch(route('admin.rooms.status', $room), $payload)->assertRedirect();
});
