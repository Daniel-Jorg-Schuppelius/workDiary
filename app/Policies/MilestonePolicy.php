<?php

namespace App\Policies;

use App\Models\Milestone;
use App\Models\User;
use App\Policies\Concerns\ChecksOwnership;
use App\Policies\Concerns\HasAdminBypass;

class MilestonePolicy {
    use ChecksOwnership;
    use HasAdminBypass;

    public function viewAny(User $user): bool {
        return true;
    }

    public function view(User $user, Milestone $milestone): bool {
        return true;
    }

    public function create(User $user): bool {
        return true;
    }

    public function update(User $user, Milestone $milestone): bool {
        return $this->owns($user, $milestone, 'created_by');
    }

    public function delete(User $user, Milestone $milestone): bool {
        return $this->owns($user, $milestone, 'created_by');
    }
}
