<?php

namespace App\Policies;

use App\Models\DiaryEntry;
use App\Models\User;

class DiaryEntryPolicy {
    public function update(User $user, DiaryEntry $entry): bool {
        return $user->id === $entry->user_id;
    }

    public function delete(User $user, DiaryEntry $entry): bool {
        return $user->id === $entry->user_id;
    }
}
