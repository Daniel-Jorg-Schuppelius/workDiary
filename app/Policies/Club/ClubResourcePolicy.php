<?php
/*
 * Created on   : Wed Sep 23 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ClubResourcePolicy.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Policies\Club;

use App\Models\Club\ClubResource;
use App\Models\User;
use App\Policies\Concerns\HasAdminBypass;

/** Sportstätten (MVP-853): lesen wie das Register (auch Gruppenleitung), Stammdaten und Sperrzeiten nur Vereinsverwaltung, Freigaben auch Leitung. */
class ClubResourcePolicy {
    use ClubAccess;
    use HasAdminBypass;

    public function viewAny(User $user): bool {
        return $this->canReadRegister($user) || $this->isGroupLead($user);
    }

    public function view(User $user, ClubResource $resource): bool {
        unset($resource);

        return $this->viewAny($user);
    }

    public function create(User $user): bool {
        return $this->canManage($user);
    }

    public function update(User $user, ClubResource $resource): bool {
        unset($resource);

        return $this->canManage($user);
    }

    public function delete(User $user, ClubResource $resource): bool {
        return $this->update($user, $resource);
    }

    /** Sperrzeiten setzen/aufheben. */
    public function close(User $user, ClubResource $resource): bool {
        return $this->update($user, $resource);
    }

    /** Einweisungs-/Eignungsfreigaben erteilen und widerrufen. */
    public function clear(User $user, ClubResource $resource): bool {
        unset($resource);

        return $this->canManage($user) || $this->isGroupLead($user);
    }
}
