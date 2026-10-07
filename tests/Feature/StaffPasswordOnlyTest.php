<?php

use App\Models\User;
use Illuminate\Support\Facades\Hash;

test('staff with an enrolled authenticator can sign in with only a password while mfa is paused', function () {
    config()->set('auth.staff_mfa_enabled', false);
    $staff = User::factory()->create([
        'staff_role' => 'admin',
        'is_admin' => true,
        'password' => Hash::make('correct-password'),
        'mfa_secret' => 'JBSWY3DPEHPK3PXPJBSWY3DPEHPK3PXP',
        'mfa_confirmed_at' => now(),
    ]);

    $this->post(route('login.submit'), [
        'email' => $staff->email,
        'password' => 'correct-password',
    ])->assertRedirect(route('admin.frontdesk'));

    $this->get(route('admin.frontdesk'))->assertOk();
    expect($staff->fresh()->mfa_secret)->toBe('JBSWY3DPEHPK3PXPJBSWY3DPEHPK3PXP');
});

test('staff without an authenticator can sign in while paused but must set one up when mfa resumes', function () {
    config()->set('auth.staff_mfa_enabled', false);
    $staff = User::factory()->create(['staff_role' => 'front_desk']);

    $this->post(route('login.submit'), [
        'email' => $staff->email,
        'password' => 'password',
    ])->assertRedirect(route('admin.frontdesk'));
    $this->get(route('admin.frontdesk'))->assertOk();

    config()->set('auth.staff_mfa_enabled', true);
    $this->get(route('admin.frontdesk'))->assertRedirect(route('admin.mfa.setup'));
});

test('password-only mode still rejects wrong passwords and preserves staff roles', function () {
    config()->set('auth.staff_mfa_enabled', false);
    $viewer = User::factory()->create([
        'staff_role' => 'viewer',
        'password' => Hash::make('correct-password'),
    ]);

    $this->post(route('login.submit'), [
        'email' => $viewer->email,
        'password' => 'wrong-password',
    ])->assertSessionHasErrors('email');
    $this->assertGuest();

    $this->post(route('login.submit'), [
        'email' => $viewer->email,
        'password' => 'correct-password',
    ])->assertRedirect(route('admin.dashboard'));
    $this->get(route('admin.dashboard'))->assertOk();
    $this->get(route('admin.bookings'))->assertForbidden();
});

test('bookmarked authenticator pages redirect staff back to work while mfa is paused', function () {
    config()->set('auth.staff_mfa_enabled', false);
    $staff = User::factory()->create([
        'staff_role' => 'admin',
        'is_admin' => true,
        'mfa_secret' => 'JBSWY3DPEHPK3PXPJBSWY3DPEHPK3PXP',
        'mfa_confirmed_at' => now(),
    ]);

    $this->actingAs($staff)->get(route('admin.mfa.challenge'))
        ->assertRedirect(route('admin.frontdesk'));
    $this->post(route('admin.mfa.verify'), ['code' => '000000'])
        ->assertRedirect(route('admin.frontdesk'));
    expect($staff->fresh()->mfa_secret)->toBe('JBSWY3DPEHPK3PXPJBSWY3DPEHPK3PXP');
});

test('staff access screen says password only while the authenticator is paused', function () {
    config()->set('auth.staff_mfa_enabled', false);
    $admin = User::factory()->create(['staff_role' => 'admin', 'is_admin' => true]);

    $this->actingAs($admin)->get(route('admin.staff'))
        ->assertOk()
        ->assertSee('password only')
        ->assertDontSee('Every staff role requires an authenticator');
});
