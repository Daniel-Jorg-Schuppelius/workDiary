<?php

namespace App\Policies;

use App\Models\Attachment;
use App\Models\User;

class AttachmentPolicy {
    public function before(User $user, string $ability): ?bool {
        return $user->isAdmin() ? true : null;
    }

    public function view(User $user, Attachment $attachment): bool {
        return true;
    }

    public function create(User $user): bool {
        return true;
    }

    public function delete(User $user, Attachment $attachment): bool {
        return $user->id === $attachment->user_id;
    }
}
