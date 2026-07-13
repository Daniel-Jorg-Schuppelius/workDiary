<?php
/*
 * Created on   : Sun Jul 12 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ChangePolicy.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Policies;

use App\Enums\User\Permission;
use App\Models\{Change, User};

/**
 * Change-Management (Feature 065, MVP-157): Sicht folgt dem Ticket-
 * Sichtrecht, Pflege (Anlage, Umsetzung, Abschluss, Asset-Verknüpfung,
 * Vorlagen) nur mit service_desk.change.manage. GENEHMIGT wird NICHT
 * hier: Freigaben laufen über die gemeinsame Genehmigungs-Inbox mit
 * service_request.approve (eine Mechanik, MVP-154). Org-Scope kommt aus
 * den Global Scopes — die Policy prüft ihn trotzdem hart (Defense in
 * Depth, Muster ProblemPolicy).
 */
class ChangePolicy {
    public function viewAny(User $user): bool {
        return $user->can(Permission::ServiceTicketView->value);
    }

    public function view(User $user, Change $change): bool {
        return $this->sameOrg($user, $change) && $user->can(Permission::ServiceTicketView->value);
    }

    public function create(User $user): bool {
        return $user->can(Permission::ServiceDeskChangeManage->value);
    }

    public function update(User $user, Change $change): bool {
        return $this->sameOrg($user, $change) && $user->can(Permission::ServiceDeskChangeManage->value);
    }

    private function sameOrg(User $user, Change $change): bool {
        return (int) $user->organization_id === (int) $change->organization_id;
    }
}
