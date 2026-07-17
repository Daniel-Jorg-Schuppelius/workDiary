<?php
/*
 * Created on   : Sun May 17 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : UserBookmarkPolicy.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Policies;

use App\Models\{User, UserBookmark};
use App\Policies\Concerns\ChecksOwnership;

class UserBookmarkPolicy {
    use ChecksOwnership;

    public function viewAny(User $user): bool {
        return true;
    }

    public function view(User $user, UserBookmark $bookmark): bool {
        return $this->owns($user, $bookmark);
    }

    public function create(User $user): bool {
        return true;
    }

    public function update(User $user, UserBookmark $bookmark): bool {
        return $this->owns($user, $bookmark);
    }

    public function delete(User $user, UserBookmark $bookmark): bool {
        return $this->owns($user, $bookmark);
    }
}
