<?php

namespace App\Policies\Concerns;

use App\Models\User;

trait ChecksOwnership {
    protected function owns(User $user, mixed $resource, string $column = 'user_id'): bool {
        return (int) $user->id === (int) data_get($resource, $column);
    }
}
