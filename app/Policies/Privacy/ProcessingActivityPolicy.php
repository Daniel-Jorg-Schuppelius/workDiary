<?php
/*
 * Created on   : Tue Jun 09 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ProcessingActivityPolicy.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Policies\Privacy;

use App\Models\Privacy\ProcessingActivity;
use App\Models\User;

/**
 * Zugriff auf das Verzeichnis von Verarbeitungstaetigkeiten. Ohne Admin-Bypass;
 * organisationsgebunden. Bearbeiten und Freigeben sind getrennte Rechte
 * (Vier-Augen moeglich).
 */
class ProcessingActivityPolicy {
    private function sameOrg(User $user, ProcessingActivity $activity): bool {
        return (int) $user->organization_id === (int) $activity->organization_id;
    }

    public function viewAny(User $user): bool {
        return $user->can('dataprotection.view');
    }

    public function view(User $user, ProcessingActivity $activity): bool {
        return $this->sameOrg($user, $activity) && $user->can('dataprotection.view');
    }

    public function create(User $user): bool {
        return $user->can('dataprotection.ropa.manage');
    }

    public function update(User $user, ProcessingActivity $activity): bool {
        return $this->sameOrg($user, $activity) && $user->can('dataprotection.ropa.manage');
    }

    public function approve(User $user, ProcessingActivity $activity): bool {
        return $this->sameOrg($user, $activity) && $user->can('dataprotection.ropa.approve');
    }

    public function export(User $user): bool {
        return $user->can('dataprotection.export');
    }
}
