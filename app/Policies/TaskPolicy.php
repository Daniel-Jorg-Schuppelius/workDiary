<?php

namespace App\Policies;

use App\Models\Task;
use App\Models\User;
use App\Policies\Concerns\ChecksOwnership;
use App\Policies\Concerns\HasAdminBypass;

class TaskPolicy {
    use ChecksOwnership;
    use HasAdminBypass;

    public function viewAny(User $user): bool {
        return true;
    }

    public function view(User $user, Task $task): bool {
        return true;
    }

    public function create(User $user): bool {
        return true;
    }

    public function update(User $user, Task $task): bool {
        return $this->owns($user, $task, 'created_by');
    }

    public function delete(User $user, Task $task): bool {
        return $this->owns($user, $task, 'created_by');
    }
}
