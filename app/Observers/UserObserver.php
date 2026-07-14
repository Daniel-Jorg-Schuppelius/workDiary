<?php
/*
 * Created on   : Sun May 03 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : UserObserver.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Observers;

use App\Models\User;
use App\Support\LookupCache;

class UserObserver {
    public function saved(User $user): void {
        LookupCache::forgetUserDropdown($user->organization_id !== null ? (int) $user->organization_id : null);
    }

    public function deleted(User $user): void {
        LookupCache::forgetUserDropdown($user->organization_id !== null ? (int) $user->organization_id : null);
    }
}
