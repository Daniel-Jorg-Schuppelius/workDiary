<?php
/*
 * Created on   : Tue Jul 14 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : CloudDocumentConnectionPolicy.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Policies\CloudIntake;

use App\Enums\User\Permission as P;
use App\Models\CloudIntake\CloudDocumentConnection;
use App\Models\User;
use App\Policies\Concerns\HasAdminBypass;

/**
 * Cloud-Dokumenteingang (Feature 080): OAuth-Verbindungen sind
 * Org-Admin-Sache (`cloudIntake.connection.manage`); Lauf-/Vorschau-Sicht
 * reicht zum Ansehen. Normale DMS-Leserechte erlauben KEINEN Zugriff auf
 * Verbindungen (Konzept §Rechte).
 */
class CloudDocumentConnectionPolicy {
    use HasAdminBypass;

    public function viewAny(User $user): bool {
        return $user->can(P::CloudIntakeConnectionManage->value)
            || $user->can(P::CloudIntakeRunPreview->value);
    }

    public function view(User $user, CloudDocumentConnection $connection): bool {
        unset($connection);

        return $this->viewAny($user);
    }

    public function create(User $user): bool {
        return $user->can(P::CloudIntakeConnectionManage->value);
    }

    public function update(User $user, CloudDocumentConnection $connection): bool {
        unset($connection);

        return $user->can(P::CloudIntakeConnectionManage->value);
    }

    public function delete(User $user, CloudDocumentConnection $connection): bool {
        unset($connection);

        return $user->can(P::CloudIntakeConnectionManage->value);
    }
}
