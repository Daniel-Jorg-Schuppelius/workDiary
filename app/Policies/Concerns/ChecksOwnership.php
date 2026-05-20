<?php
/*
 * Created on   : Sun May 03 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ChecksOwnership.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Policies\Concerns;

use App\Models\User;

trait ChecksOwnership {
    protected function owns(User $user, mixed $resource, string $column = 'user_id'): bool {
        return (int) $user->id === (int) data_get($resource, $column);
    }
}
