<?php
/*
 * Created on   : Tue Jul 14 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : BackupTargetConnectionPolicy.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Policies\Backup;

use App\Models\Backup\BackupTargetConnection;
use App\Models\User;

/**
 * Backupziel-Verbindungen (Feature 017 Phase 32): SYSTEMWEITE Verwaltung —
 * ausschließlich Plattform-Admin (`is_platform_admin`), NIE org-delegierbar.
 * Bewusst KEIN {@see \App\Policies\Concerns\HasAdminBypass}: ein org-lokaler
 * Admin darf Installations-Backups weder sehen noch verwalten (Muster
 * Mandantenverwaltung, {@see \App\Policies\OrganizationPolicy}).
 */
class BackupTargetConnectionPolicy {
    public function before(User $user, string $ability): ?bool {
        unset($ability);

        return $user->isGlobalAdmin() ? true : null;
    }

    public function viewAny(User $user): bool {
        unset($user);

        return false;
    }

    public function view(User $user, BackupTargetConnection $connection): bool {
        unset($user, $connection);

        return false;
    }

    public function create(User $user): bool {
        unset($user);

        return false;
    }

    public function update(User $user, BackupTargetConnection $connection): bool {
        unset($user, $connection);

        return false;
    }

    public function delete(User $user, BackupTargetConnection $connection): bool {
        unset($user, $connection);

        return false;
    }
}
