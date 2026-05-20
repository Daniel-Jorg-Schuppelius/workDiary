<?php
/*
 * Created on   : Wed Apr 29 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : DiaryEntryPolicy.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

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
