<?php

namespace App\Console\Commands;

use App\Models\ActivityLog;
use App\Models\User;
use Illuminate\Console\Command;

class PromoteOrganizationAdmin extends Command
{
    protected $signature = 'staff:promote-organization-admin {email : Email of an existing account}';

    protected $description = 'Grant Organization Admin access to an existing account';

    public function handle(): int
    {
        $email = strtolower(trim((string) $this->argument('email')));
        $user = User::where('email', $email)->first();

        if (! $user) {
            $this->error('The account was not found.');

            return self::FAILURE;
        }

        if (! $user->isAdmin() || $user->staff_role !== 'organization_admin') {
            $user->forceFill(['staff_role' => 'organization_admin', 'is_admin' => true])->save();
            ActivityLog::create([
                'user_id' => $user->id,
                'event' => 'organization_admin_granted',
                'description' => 'Organization Admin access granted through a server-side maintenance command.',
            ]);
        }

        $this->info('Organization Admin access is configured.');

        return self::SUCCESS;
    }
}
