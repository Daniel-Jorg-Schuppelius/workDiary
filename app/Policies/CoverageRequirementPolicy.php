<?php

namespace App\Policies;

use App\Models\CoverageRequirement;
use App\Models\User;
use App\Policies\Concerns\HasAdminBypass;

class CoverageRequirementPolicy
{
    use HasAdminBypass;

    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, CoverageRequirement $requirement): bool
    {
        return $user->organization_id === $requirement->organization_id;
    }

    public function create(User $user): bool
    {
        return false;
    }

    public function update(User $user, CoverageRequirement $requirement): bool
    {
        return false;
    }

    public function delete(User $user, CoverageRequirement $requirement): bool
    {
        return false;
    }
}
