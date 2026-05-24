<?php
/*
 * Created on   : Sun May 03 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : CommentPolicy.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Policies;

use App\Models\{Comment, User};
use App\Policies\Concerns\{ChecksOwnership, HasAdminBypass};

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
