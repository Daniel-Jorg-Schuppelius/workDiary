<?php
/*
 * Created on   : Sun May 25 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : UserFilterPresetPolicy.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Policies;

use App\Models\{User, UserFilterPreset};
use App\Policies\Concerns\ChecksOwnership;

class UserFilterPresetPolicy {
    use ChecksOwnership;

    public function view(User $user, UserFilterPreset $preset): bool {
        return $this->owns($user, $preset);
    }

    public function update(User $user, UserFilterPreset $preset): bool {
        return $this->owns($user, $preset);
    }

    public function delete(User $user, UserFilterPreset $preset): bool {
        return $this->owns($user, $preset);
    }
}
