<?php
/*
 * Created on   : Tue Jul 14 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : BackupGenerationPolicy.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Policies\Backup;

use App\Models\Backup\BackupGeneration;
use App\Models\User;

/**
 * Backup-Generationen (Feature 017 Phase 32): Sicht + Legal-Hold +
 * Bereinigung nur Plattform-Admin — analog
 * {@see BackupTargetConnectionPolicy}, kein Org-Admin-Bypass.
 */
class BackupGenerationPolicy {
    public function before(User $user, string $ability): ?bool {
        unset($ability);

        return $user->isGlobalAdmin() ? true : null;
    }

    public function viewAny(User $user): bool {
        unset($user);

        return false;
    }

    public function view(User $user, BackupGeneration $generation): bool {
        unset($user, $generation);

        return false;
    }

    /** Legal-Hold setzen/lösen. */
    public function hold(User $user, BackupGeneration $generation): bool {
        unset($user, $generation);

        return false;
    }

    /** Explizite Bereinigung (Remote-Löschung) nach Vorschau + Bestätigung. */
    public function delete(User $user, BackupGeneration $generation): bool {
        unset($user, $generation);

        return false;
    }
}
