<?php

namespace App\Console\Commands;

use App\Models\ActivityLog;
use App\Models\User;
use Illuminate\Console\Command;

class PromoteSuperAdmin extends Command
{
    protected $signature = 'staff:promote-super-admin {email : Email of an existing administrator}';

    protected $description = 'Grant super administrator access to an existing administrator';

    public function handle(): int
    {
        $email = strtolower(trim((string) $this->argument('email')));
        $user = User::where('email', $email)->first();

        if (! $user?->isAdmin()) {
            $this->error('The account must already be an administrator.');

            return self::FAILURE;
        }

        if ($user->staff_role !== 'super_admin') {
            $user->forceFill(['staff_role' => 'super_admin', 'is_admin' => true])->save();
            ActivityLog::create([
                'user_id' => $user->id,
                'event' => 'super_admin_granted',
                'description' => 'Super administrator access granted through a server-side maintenance command.',
            ]);
        }

        $this->info('Super administrator access is configured. MFA is required at sign-in.');

        return self::SUCCESS;
    }
}
