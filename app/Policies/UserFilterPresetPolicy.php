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

use App\Models\User;
use App\Models\UserFilterPreset;

class UserFilterPresetPolicy {
    public function view(User $user, UserFilterPreset $preset): bool {
        return $preset->user_id === $user->id;
    }

    public function update(User $user, UserFilterPreset $preset): bool {
        return $preset->user_id === $user->id;
    }

    public function delete(User $user, UserFilterPreset $preset): bool {
        return $preset->user_id === $user->id;
    }
}
