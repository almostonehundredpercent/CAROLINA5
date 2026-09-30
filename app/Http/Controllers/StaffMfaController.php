<?php

namespace App\Http\Controllers;

use App\Models\ActivityLog;
use App\Models\User;
use App\Support\AdminPermissions;
use BaconQrCode\Renderer\Image\SvgImageBackEnd;
use BaconQrCode\Renderer\ImageRenderer;
use BaconQrCode\Renderer\RendererStyle\RendererStyle;
use BaconQrCode\Writer;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use PragmaRX\Google2FA\Google2FA;

class StaffMfaController extends Controller
{
    public function setup(Request $request)
    {
        $user = $this->staff($request);

        if ($user->mfa_confirmed_at) {
            return redirect()->route('admin.mfa.challenge');
        }

        $canConfirm = $this->setupAuthorized($request, $user);
        $qrSvg = null;
        if ($canConfirm && $user->mfa_secret) {
            $uri = 'otpauth://totp/'.rawurlencode('Carolina:'.$user->email)
                .'?secret='.$user->mfa_secret.'&issuer=Carolina&digits=6&period=30';
            $qrSvg = (new Writer(new ImageRenderer(new RendererStyle(192), new SvgImageBackEnd)))
                ->writeString($uri);
        }

        return response()->view('auth.staff-mfa-setup', compact('user', 'qrSvg', 'canConfirm'))
            ->header('Cache-Control', 'no-store, private');
    }

    public function beginSetup(Request $request, Google2FA $totp): RedirectResponse
    {
        $user = $this->staff($request);
        abort_if($user->mfa_confirmed_at, 403);
        $request->validate(['password' => 'required|string']);

        if (! Hash::check((string) $request->input('password'), $user->password)) {
            return back()->withErrors(['password' => 'The password is incorrect.']);
        }

        $user->forceFill(['mfa_secret' => $totp->generateSecretKey(32)])->save();
        $request->session()->put('staff_mfa_setup_user_id', $user->id);
        $request->session()->put('staff_mfa_setup_at', now()->getTimestamp());
        $this->audit($user, 'mfa_setup_started', 'Authenticator setup started.');

        return redirect()->route('admin.mfa.setup');
    }

    public function confirmSetup(Request $request, Google2FA $totp): RedirectResponse
    {
        $user = $this->staff($request);
        abort_unless($user->mfa_secret && ! $user->mfa_confirmed_at, 403);
        abort_unless($this->setupAuthorized($request, $user), 403);
        $code = $request->validate(['code' => 'required|digits:6'])['code'];
        $step = $totp->verifyKeyNewer($user->mfa_secret, $code, 0, 1);

        if ($step === false) {
            return back()->withErrors(['code' => 'That authenticator code is not valid.']);
        }

        $codes = array_map(fn () => strtoupper(bin2hex(random_bytes(12))), range(1, 8));
        $user->forceFill([
            'mfa_recovery_codes' => array_map(fn ($recovery) => Hash::make($recovery), $codes),
            'mfa_confirmed_at' => now(),
            'mfa_last_totp_step' => $step,
        ])->save();

        $this->markVerified($request, $user);
        $request->session()->forget(['staff_mfa_setup_user_id', 'staff_mfa_setup_at']);
        $request->session()->flash('staff_mfa_new_codes', Crypt::encryptString(json_encode($codes)));
        $this->audit($user, 'mfa_enrolled', 'Staff authenticator confirmed.');

        return redirect()->route('admin.mfa.recovery');
    }

    public function challenge(Request $request)
    {
        $user = $this->staff($request);
        if (! $user->mfa_confirmed_at || ! $user->mfa_secret) {
            return redirect()->route('admin.mfa.setup');
        }

        if ($this->verified($request, $user)) {
            return redirect()->route(AdminPermissions::landingRoute($user));
        }

        return response()->view('auth.staff-mfa-challenge')
            ->header('Cache-Control', 'no-store, private');
    }

    public function verifyChallenge(Request $request, Google2FA $totp): RedirectResponse
    {
        $user = $this->staff($request);
        abort_unless($user->mfa_confirmed_at && $user->mfa_secret, 403);
        $data = $request->validate([
            'code' => 'nullable|digits:6|required_without:recovery_code',
            'recovery_code' => 'nullable|string|max:64|required_without:code',
        ]);

        $valid = DB::transaction(function () use ($user, $data, $totp): bool {
            $locked = User::query()->lockForUpdate()->findOrFail($user->id);
            if (filled($data['code'] ?? null)) {
                $step = $totp->verifyKeyNewer(
                    $locked->mfa_secret,
                    $data['code'],
                    $locked->mfa_last_totp_step ?? 0,
                    1,
                );
                if ($step === false) {
                    return false;
                }
                $locked->forceFill(['mfa_last_totp_step' => $step])->save();

                return true;
            }

            $candidate = strtoupper(trim((string) ($data['recovery_code'] ?? '')));
            $codes = $locked->mfa_recovery_codes ?? [];
            foreach ($codes as $index => $hash) {
                if (Hash::check($candidate, $hash)) {
                    unset($codes[$index]);
                    $locked->forceFill(['mfa_recovery_codes' => array_values($codes)])->save();

                    return true;
                }
            }

            return false;
        });

        if (! $valid) {
            $this->audit($user, 'mfa_challenge_failed', 'A staff MFA challenge failed.');

            return back()->withErrors(['code' => 'That code is invalid or has already been used.']);
        }

        $this->markVerified($request, $user);
        $this->audit($user, 'staff_login', 'Staff sign-in completed with MFA.');

        return redirect()->intended(route(AdminPermissions::landingRoute($user)));
    }

    public function recoveryCodes(Request $request)
    {
        $user = $this->staff($request);
        abort_unless($this->verified($request, $user), 403);
        $encrypted = $request->session()->pull('staff_mfa_new_codes');
        if (! $encrypted) {
            return redirect()->route(AdminPermissions::landingRoute($user));
        }

        $codes = json_decode(Crypt::decryptString($encrypted), true);

        return response()->view('auth.staff-mfa-recovery', compact('codes'))
            ->header('Cache-Control', 'no-store, private');
    }

    private function staff(Request $request): User
    {
        $user = $request->user();
        abort_unless($user?->hasStaffAccess(), 403);

        return $user;
    }

    private function verified(Request $request, User $user): bool
    {
        return $request->session()->get('staff_mfa_user_id') === $user->id
            && $request->session()->get('staff_mfa_stamp') === $user->mfa_confirmed_at?->getTimestamp();
    }

    private function markVerified(Request $request, User $user): void
    {
        $request->session()->regenerate();
        $request->session()->put('staff_mfa_user_id', $user->id);
        $request->session()->put('staff_mfa_stamp', $user->mfa_confirmed_at->getTimestamp());
    }

    private function setupAuthorized(Request $request, User $user): bool
    {
        $time = $request->session()->get('staff_mfa_setup_at');

        return $request->session()->get('staff_mfa_setup_user_id') === $user->id
            && is_int($time)
            && $time <= now()->getTimestamp()
            && $time >= now()->subMinutes(10)->getTimestamp();
    }

    private function audit(User $user, string $event, string $description): void
    {
        ActivityLog::create(['user_id' => $user->id, 'event' => $event, 'description' => $description]);
    }
}
