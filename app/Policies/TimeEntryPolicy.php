<?php

namespace App\Policies;

use App\Models\TimeEntry;
use App\Models\User;
use App\Policies\Concerns\ChecksOwnership;
use App\Policies\Concerns\HasAdminBypass;

class TimeEntryPolicy
{
    use ChecksOwnership;
    use HasAdminBypass;

    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, TimeEntry $entry): bool
    {
        return true;
    }

    public function create(User $user): bool
    {
        return true;
    }

    public function update(User $user, TimeEntry $entry): bool
    {
        return $this->owns($user, $entry, 'user_id');
    }

    public function delete(User $user, TimeEntry $entry): bool
    {
        return $this->owns($user, $entry, 'user_id');
    }
}
