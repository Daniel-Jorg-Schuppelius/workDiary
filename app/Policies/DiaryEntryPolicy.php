<?php

namespace App\Policies;

use App\Models\DiaryEntry;
use App\Models\User;

class DiaryEntryPolicy {
    public function before(User $user, string $ability): ?bool {
        return $user->isAdmin() ? true : null;
    }

    public function view(User $user, DiaryEntry $entry): bool {
        return $user->id === $entry->user_id;
    }

    public function update(User $user, DiaryEntry $entry): bool {
        return $user->id === $entry->user_id;
    }

    public function delete(User $user, DiaryEntry $entry): bool {
        return $user->id === $entry->user_id;
    }

    public function archive(User $user, DiaryEntry $entry): bool {
        return $user->id === $entry->user_id;
    }
}
