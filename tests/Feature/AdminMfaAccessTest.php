<?php

use App\Models\ActivityLog;
use App\Models\User;
use App\Support\AdminPermissions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Facades\Route;
use PragmaRX\Google2FA\Google2FA;

test('staff password sign in leads to mandatory authenticator setup', function () {
    $staff = User::factory()->create([
        'staff_role' => 'admin',
        'is_admin' => true,
        'password' => Hash::make('correct-password'),
    ]);

    $this->post(route('login.submit'), [
        'email' => $staff->email,
        'password' => 'correct-password',
    ])->assertRedirect('/staff/mfa/setup');
});

test('an authenticated staff member cannot open admin pages before mfa setup', function () {
    $staff = User::factory()->create(['staff_role' => 'front_desk']);

    $this->actingAs($staff)->get(route('admin.bookings'))
        ->assertRedirect('/staff/mfa/setup');
});

test('a regular administrator cannot elevate a staff member to administrator', function () {
    $admin = User::factory()->create(['staff_role' => 'admin', 'is_admin' => true]);
    $staff = User::factory()->create(['staff_role' => 'front_desk']);
    $admin->forceFill(['mfa_secret' => 'JBSWY3DPEHPK3PXPJBSWY3DPEHPK3PXP', 'mfa_confirmed_at' => now()])->save();

    $this->actingAs($admin)->withSession([
        'staff_mfa_user_id' => $admin->id,
        'staff_mfa_stamp' => $admin->mfa_confirmed_at->getTimestamp(),
    ])->patch(route('admin.staff.role', $staff), [
        'staff_role' => 'super_admin',
    ])->assertForbidden();
});

test('staff enrollment requires the current password and encrypts the authenticator secret', function () {
    $staff = User::factory()->create(['staff_role' => 'admin', 'is_admin' => true]);

    $this->actingAs($staff)->post(route('admin.mfa.begin'), ['password' => 'wrong'])
        ->assertSessionHasErrors('password');
    expect($staff->fresh()->mfa_secret)->toBeNull();

    $this->post(route('admin.mfa.begin'), ['password' => 'password'])
        ->assertRedirect(route('admin.mfa.setup'));
    expect($staff->fresh()->mfa_secret)->toHaveLength(32);
    expect(DB::table('users')->where('id', $staff->id)->value('mfa_secret'))
        ->not->toBe($staff->fresh()->mfa_secret);
});

test('a pending authenticator secret is not exposed or confirmed in a new session without password recheck', function () {
    $staff = User::factory()->create(['staff_role' => 'admin', 'is_admin' => true]);
    $staff->forceFill(['mfa_secret' => 'JBSWY3DPEHPK3PXPJBSWY3DPEHPK3PXP'])->save();

    $this->actingAs($staff)->get(route('admin.mfa.setup'))
        ->assertOk()->assertDontSee('JBSWY3DPEHPK3PXPJBSWY3DPEHPK3PXP');
    $this->post(route('admin.mfa.confirm'), [
        'code' => (new Google2FA)->getCurrentOtp($staff->mfa_secret),
    ])->assertForbidden();
});

test('staff must confirm totp and receives one-time recovery codes before admin access', function () {
    $staff = User::factory()->create(['staff_role' => 'admin', 'is_admin' => true]);
    $this->actingAs($staff)->post(route('admin.mfa.begin'), ['password' => 'password']);
    $code = (new Google2FA)->getCurrentOtp($staff->fresh()->mfa_secret);

    $this->post(route('admin.mfa.confirm'), ['code' => '000000'])
        ->assertSessionHasErrors('code');
    $this->post(route('admin.mfa.confirm'), ['code' => $code])
        ->assertRedirect(route('admin.mfa.recovery'));
    expect($staff->fresh()->mfa_confirmed_at)->not->toBeNull();
    $this->get(route('admin.mfa.recovery'))->assertOk()->assertSee('backup access');
    $this->get(route('admin.mfa.recovery'))->assertRedirect(route('admin.frontdesk'));
    $this->get(route('admin.frontdesk'))->assertOk();
});

test('totp challenge accepts a current code only once and then opens admin', function () {
    $staff = User::factory()->create(['staff_role' => 'admin', 'is_admin' => true]);
    $staff->forceFill([
        'mfa_secret' => (new Google2FA)->generateSecretKey(32),
        'mfa_confirmed_at' => now(),
        'mfa_recovery_codes' => [],
    ])->save();
    $code = (new Google2FA)->getCurrentOtp($staff->mfa_secret);

    $this->actingAs($staff)->get(route('admin.frontdesk'))
        ->assertRedirect(route('admin.mfa.challenge'));
    $this->post(route('admin.mfa.verify'), ['code' => $code])
        ->assertRedirect(route('admin.frontdesk'));
    $this->get(route('admin.frontdesk'))->assertOk();
    $this->post(route('logout'))->assertRedirect(route('home'));
    $this->post(route('login.submit'), ['email' => $staff->email, 'password' => 'password'])
        ->assertRedirect(route('admin.mfa.challenge'));
    $this->post(route('admin.mfa.verify'), ['code' => $code])
        ->assertSessionHasErrors('code');
});

test('recovery code is consumed after one successful challenge', function () {
    $staff = User::factory()->create(['staff_role' => 'admin', 'is_admin' => true]);
    $staff->forceFill([
        'mfa_secret' => (new Google2FA)->generateSecretKey(32),
        'mfa_confirmed_at' => now(),
        'mfa_recovery_codes' => [Hash::make('RECOVERY-CODE-ONE')],
    ])->save();

    $this->actingAs($staff)->post(route('admin.mfa.verify'), ['recovery_code' => 'recovery-code-one'])
        ->assertRedirect(route('admin.frontdesk'));
    expect($staff->fresh()->mfa_recovery_codes)->toBe([]);
    $this->post(route('logout'));
    $this->post(route('login.submit'), ['email' => $staff->email, 'password' => 'password']);
    $this->post(route('admin.mfa.verify'), ['recovery_code' => 'RECOVERY-CODE-ONE'])
        ->assertSessionHasErrors('code');
});

test('login attempts are limited by account and ip', function () {
    $staff = User::factory()->create(['staff_role' => 'admin', 'is_admin' => true]);
    foreach (range(1, 5) as $_) {
        $this->post(route('login.submit'), ['email' => $staff->email, 'password' => 'wrong']);
    }
    $this->post(route('login.submit'), ['email' => $staff->email, 'password' => 'wrong'])
        ->assertTooManyRequests();
});

test('distributed attempts against one account are also limited', function () {
    $staff = User::factory()->create(['staff_role' => 'admin', 'is_admin' => true]);
    foreach (range(1, 10) as $number) {
        $this->withServerVariables(['REMOTE_ADDR' => '192.0.2.'.$number])
            ->post(route('login.submit'), ['email' => $staff->email, 'password' => 'wrong-password']);
    }

    $this->withServerVariables(['REMOTE_ADDR' => '192.0.2.11'])
        ->post(route('login.submit'), ['email' => $staff->email, 'password' => 'wrong-password'])
        ->assertTooManyRequests();
});

test('login throttling treats copied and mixed-case email variants as the same account', function () {
    $staff = User::factory()->create([
        'email' => 'staff@example.test', 'staff_role' => 'admin', 'is_admin' => true,
    ]);
    foreach (['staff@example.test', 'STAFF@EXAMPLE.TEST', 'staff\\@example.test', ' Staff@Example.Test ', 'staff@example.test'] as $email) {
        $this->post(route('login.submit'), ['email' => $email, 'password' => 'wrong-password'])
            ->assertRedirect();
    }

    $this->post(route('login.submit'), ['email' => $staff->email, 'password' => 'wrong-password'])
        ->assertTooManyRequests();
});

test('distributed authenticator guesses against one staff account are limited', function () {
    $staff = User::factory()->create(['staff_role' => 'admin', 'is_admin' => true]);
    $staff->forceFill([
        'mfa_secret' => (new Google2FA)->generateSecretKey(32),
        'mfa_confirmed_at' => now(),
    ])->save();
    $this->actingAs($staff);
    foreach (range(1, 5) as $number) {
        $this->withServerVariables(['REMOTE_ADDR' => '198.51.100.'.$number])
            ->post(route('admin.mfa.verify'), ['code' => '000000'])
            ->assertRedirect();
    }

    $this->withServerVariables(['REMOTE_ADDR' => '198.51.100.6'])
        ->post(route('admin.mfa.verify'), ['code' => '000000'])
        ->assertTooManyRequests();
});

test('a super administrator can grant administrator access after mfa', function () {
    $owner = User::factory()->create(['staff_role' => 'super_admin', 'is_admin' => true]);
    $staff = User::factory()->create(['staff_role' => 'front_desk']);

    $this->actingAsStaff($owner)->patch(route('admin.staff.role', $staff), [
        'staff_role' => 'admin',
    ])->assertRedirect();

    expect($staff->fresh()->staff_role)->toBe('admin');
    expect($staff->fresh()->is_admin)->toBeTrue();
});

test('a super administrator label alone cannot grant access without the admin flag', function () {
    $misconfigured = User::factory()->create(['staff_role' => 'super_admin', 'is_admin' => false]);

    expect(AdminPermissions::allows($misconfigured, 'elevate_staff'))->toBeFalse();
    $this->actingAs($misconfigured)->get(route('admin.staff'))->assertForbidden();
});

test('staff mfa routes reject regular guests and unknown visitors', function () {
    $guest = User::factory()->create();
    $this->get(route('admin.mfa.setup'))->assertRedirect(route('login'));
    $this->actingAs($guest)->get(route('admin.mfa.setup'))->assertForbidden();
    $this->post(route('admin.mfa.begin'), ['password' => 'password'])->assertForbidden();
});

test('every admin route has authentication staff authorization and mfa middleware', function () {
    foreach (Route::getRoutes() as $route) {
        if (! str_starts_with($route->uri(), 'admin/')) {
            continue;
        }
        $middleware = $route->gatherMiddleware();
        expect($middleware)->toContain('auth', 'admin', 'staff.mfa');
    }
});

test('registration hashes the password and never stores the submitted value', function () {
    $this->post(route('register.submit'), [
        'name' => 'Password Test',
        'email' => 'password-test@example.test',
        'password' => 'safe-example-password',
        'password_confirmation' => 'safe-example-password',
    ])->assertRedirect(route('verification.notice'));

    $hash = User::where('email', 'password-test@example.test')->value('password');
    expect($hash)->not->toBe('safe-example-password');
    expect(Hash::check('safe-example-password', $hash))->toBeTrue();
});

test('the staff session is regenerated after password and mfa verification', function () {
    $staff = User::factory()->create(['staff_role' => 'admin', 'is_admin' => true]);
    $staff->forceFill([
        'mfa_secret' => (new Google2FA)->generateSecretKey(32),
        'mfa_confirmed_at' => now(),
    ])->save();
    $beforeLogin = session()->getId();
    $this->post(route('login.submit'), ['email' => $staff->email, 'password' => 'password'])
        ->assertRedirect(route('admin.mfa.challenge'));
    $afterLogin = session()->getId();
    expect($afterLogin)->not->toBe($beforeLogin);

    $this->post(route('admin.mfa.verify'), ['code' => (new Google2FA)->getCurrentOtp($staff->mfa_secret)])
        ->assertRedirect(route('admin.frontdesk'));
    expect(session()->getId())->not->toBe($afterLogin);
});

test('the maintenance command can promote only an existing administrator', function () {
    $admin = User::factory()->create(['staff_role' => 'admin', 'is_admin' => true]);
    $guest = User::factory()->create();

    $this->artisan('staff:promote-super-admin', ['email' => $guest->email])->assertExitCode(1);
    expect($guest->fresh()->is_admin)->toBeFalse();

    $this->artisan('staff:promote-super-admin', ['email' => $admin->email])->assertExitCode(0);
    expect($admin->fresh()->staff_role)->toBe('super_admin');
});

test('a server-side recovery command clears a lost authenticator and invalidates mfa sessions', function () {
    $admin = User::factory()->create(['staff_role' => 'admin', 'is_admin' => true]);
    $admin->forceFill([
        'mfa_secret' => (new Google2FA)->generateSecretKey(32),
        'mfa_confirmed_at' => now(),
        'mfa_recovery_codes' => [Hash::make('LOST-CODE')],
    ])->save();
    $this->actingAsStaff($admin)->get(route('admin.frontdesk'))->assertOk();

    $this->artisan('staff:reset-mfa', ['email' => $admin->email])->assertExitCode(0);
    expect($admin->fresh()->mfa_secret)->toBeNull();
    expect($admin->fresh()->mfa_recovery_codes)->toBeNull();
    $admin->refresh();
    $this->get(route('admin.frontdesk'))->assertRedirect(route('admin.mfa.setup'));
});

test('the staff screen only offers administrator roles to super administrators', function () {
    $admin = User::factory()->create(['staff_role' => 'admin', 'is_admin' => true]);
    $this->actingAsStaff($admin)->get(route('admin.staff'))
        ->assertOk()->assertDontSee('value="super_admin"', false);

    $owner = User::factory()->create(['staff_role' => 'super_admin', 'is_admin' => true]);
    $this->actingAsStaff($owner)->get(route('admin.staff'))
        ->assertOk()->assertSee('value="super_admin"', false);
});

test('state-changing admin requests still reject a missing csrf token', function () {
    $environment = app()->environment();
    app()->instance('env', 'local');
    try {
        $this->post(route('admin.mfa.begin'), ['password' => 'password'])
            ->assertStatus(419);
    } finally {
        app()->instance('env', $environment);
    }
});

test('local http does not receive production-only https headers', function () {
    $environment = app()->environment();
    app()->instance('env', 'local');
    try {
        $response = $this->get(route('login'));
        $response->assertOk();
        expect($response->headers->get('Strict-Transport-Security'))->toBeNull();
        expect($response->headers->get('Content-Security-Policy'))->not->toContain('upgrade-insecure-requests');
    } finally {
        app()->instance('env', $environment);
    }
});

test('staff login failure and logout leave credential-free audit events', function () {
    $staff = User::factory()->create([
        'staff_role' => 'admin', 'is_admin' => true,
        'password' => Hash::make('unique-secret-2718'),
    ]);
    $this->post(route('login.submit'), ['email' => $staff->email, 'password' => 'wrong-secret-7182']);
    $this->post(route('login.submit'), ['email' => $staff->email, 'password' => 'unique-secret-2718']);
    $this->post(route('logout'));

    expect(ActivityLog::where('user_id', $staff->id)->where('event', 'login_failed')->exists())->toBeTrue();
    expect(ActivityLog::where('user_id', $staff->id)->where('event', 'staff_logout')->exists())->toBeTrue();
    expect(ActivityLog::where('user_id', $staff->id)->get()->toJson())->not->toContain('unique-secret-2718', 'wrong-secret-7182');
});

test('registration and password reset are recorded without the new password', function () {
    $this->post(route('register.submit'), [
        'name' => 'Audit Guest', 'email' => 'audit-guest@example.test',
        'password' => 'first-safe-password', 'password_confirmation' => 'first-safe-password',
    ]);
    $guest = User::where('email', 'audit-guest@example.test')->firstOrFail();
    expect(ActivityLog::where('user_id', $guest->id)->where('event', 'user_registered')->exists())->toBeTrue();

    $this->post(route('logout'));
    $token = Password::createToken($guest);
    $this->post(route('password.update'), [
        'token' => $token, 'email' => $guest->email,
        'password' => 'second-safe-password', 'password_confirmation' => 'second-safe-password',
    ])->assertRedirect(route('login'));
    expect(ActivityLog::where('user_id', $guest->id)->where('event', 'password_changed')->exists())->toBeTrue();
    expect(ActivityLog::where('user_id', $guest->id)->get()->toJson())->not->toContain('second-safe-password');
});
