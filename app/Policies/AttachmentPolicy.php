<?php
/*
 * Created on   : Sun May 03 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : AttachmentPolicy.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

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
