<?php

namespace Tests;

use App\Models\User;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    public function actingAsStaff(User $user): static
    {
        if ($user->hasStaffAccess()) {
            $user->forceFill([
                'mfa_secret' => 'JBSWY3DPEHPK3PXPJBSWY3DPEHPK3PXP',
                'mfa_confirmed_at' => now(),
            ])->save();
            $this->withSession([
                'staff_mfa_user_id' => $user->id,
                'staff_mfa_stamp' => $user->mfa_confirmed_at->getTimestamp(),
            ]);
        }

        return $this->actingAs($user);
    }
}
