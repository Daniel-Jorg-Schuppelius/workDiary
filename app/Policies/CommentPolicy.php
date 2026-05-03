<?php

namespace App\Policies;

use App\Models\Comment;
use App\Models\User;
use App\Policies\Concerns\ChecksOwnership;
use App\Policies\Concerns\HasAdminBypass;

class CommentPolicy {
    use ChecksOwnership;
    use HasAdminBypass;

    public function create(User $user): bool {
        return true;
    }

    public function update(User $user, Comment $comment): bool {
        return $this->owns($user, $comment);
    }

    public function delete(User $user, Comment $comment): bool {
        return $this->owns($user, $comment);
    }
}
