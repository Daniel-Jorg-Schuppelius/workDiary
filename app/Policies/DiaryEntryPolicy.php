<?php

namespace App\Policies;

use App\Models\DiaryEntry;
use App\Models\User;
use App\Policies\Concerns\ChecksOwnership;
use App\Policies\Concerns\HasAdminBypass;

class DiaryEntryPolicy {
    use ChecksOwnership;
    use HasAdminBypass;

    public function view(User $user, DiaryEntry $entry): bool {
        return $this->owns($user, $entry);
    }

    public function update(User $user, DiaryEntry $entry): bool {
        return $this->owns($user, $entry);
    }

    public function delete(User $user, DiaryEntry $entry): bool {
        return $this->owns($user, $entry);
    }

    public function archive(User $user, DiaryEntry $entry): bool {
        return $this->owns($user, $entry);
    }
}
