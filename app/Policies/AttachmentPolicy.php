<?php

namespace App\Policies;

use App\Models\Attachment;
use App\Models\User;
use App\Policies\Concerns\ChecksOwnership;
use App\Policies\Concerns\HasAdminBypass;

class AttachmentPolicy {
    use ChecksOwnership;
    use HasAdminBypass;

    public function view(User $user, Attachment $attachment): bool {
        return true;
    }

    public function create(User $user): bool {
        return true;
    }

    public function delete(User $user, Attachment $attachment): bool {
        return $this->owns($user, $attachment);
    }
}
