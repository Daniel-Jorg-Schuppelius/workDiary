<?php
/*
 * Created on   : Wed Sep 23 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ClubPerformancePolicy.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Policies\Club;

use App\Models\Club\ClubPerformance;
use App\Models\User;
use App\Policies\Concerns\HasAdminBypass;

/** Leistungen und Startrechte (MVP-855): Leistungen erfasst/bestätigt Verwaltung oder Gruppenleitung, Startrechte nur Verwaltung. */
class ClubPerformancePolicy {
    use ClubAccess;
    use HasAdminBypass;

    public function viewAny(User $user): bool {
        return $this->canReadRegister($user) || $this->isGroupLead($user);
    }

    public function create(User $user): bool {
        return $this->canManage($user) || $this->isGroupLead($user);
    }

    public function confirm(User $user, ClubPerformance $performance): bool {
        unset($performance);

        return $this->create($user);
    }

    public function update(User $user, ClubPerformance $performance): bool {
        return $this->confirm($user, $performance);
    }

    public function delete(User $user, ClubPerformance $performance): bool {
        unset($performance);

        return $this->canManage($user);
    }

    /** Startrecht als dokumentierte Prüfung — nur Vereinsverwaltung. */
    public function grantStartRight(User $user): bool {
        return $this->canManage($user);
    }
}
