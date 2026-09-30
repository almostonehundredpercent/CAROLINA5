<?php

namespace App\Console\Commands;

use App\Models\ActivityLog;
use App\Models\User;
use Illuminate\Console\Command;

class ResetStaffMfa extends Command
{
    protected $signature = 'staff:reset-mfa {email : Email of a staff account whose identity has been verified}';

    protected $description = 'Reset a lost staff authenticator from the server console';

    public function handle(): int
    {
        $email = strtolower(trim((string) $this->argument('email')));
        $user = User::where('email', $email)->first();

        if (! $user?->hasStaffAccess()) {
            $this->error('No staff account matches that address.');

            return self::FAILURE;
        }

        $user->forceFill([
            'mfa_secret' => null,
            'mfa_recovery_codes' => null,
            'mfa_confirmed_at' => null,
            'mfa_last_totp_step' => null,
        ])->save();

        ActivityLog::create([
            'user_id' => $user->id,
            'event' => 'mfa_reset',
            'description' => 'Staff authenticator reset through a server-side maintenance command.',
        ]);

        $this->info('Authenticator reset. The staff member must enroll again at next sign-in.');

        return self::SUCCESS;
    }
}
