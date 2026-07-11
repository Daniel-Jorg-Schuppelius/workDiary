<?php
/*
 * Created on   : Fri Jul 10 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : CrisisCasePolicy.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Policies\Crisis;

use App\Enums\User\Permission as P;
use App\Models\Crisis\CrisisCase;
use App\Models\User;
use App\Policies\Concerns\HasAdminBypass;

/**
 * Krisenakten (Feature 070): eigene Rechte — normale Projekt-/Ticket-
 * Rollen sehen sie nicht automatisch. NOTFALLZUGRIFF (MVP-213): benannte
 * Stabsmitglieder (inkl. Stellvertretung) sehen IHRE Akte auch ohne
 * crisis.view — zeitlich implizit begrenzt auf die Stabsbenennung,
 * auditiert über die Assignment-Anlage.
 */
class CrisisCasePolicy {
    use HasAdminBypass;

    public function viewAny(User $user): bool {
        return $user->can(P::CrisisViewAny->value);
    }

    public function view(User $user, CrisisCase $case): bool {
        return $user->can(P::CrisisView->value) || $case->isTeamMember($user);
    }

    public function create(User $user): bool {
        return $user->can(P::CrisisManage->value);
    }

    public function update(User $user, CrisisCase $case): bool {
        return $user->can(P::CrisisManage->value);
    }

    /** Quittierung: eigenes Stabsmitglied — bewusst ohne crisis.*-Recht. */
    public function acknowledge(User $user, CrisisCase $case): bool {
        return $case->isTeamMember($user) || $user->can(P::CrisisManage->value);
    }

    /** Aktivierung, Entwarnung, Kommunikationsfreigabe. */
    public function approve(User $user, CrisisCase $case): bool {
        return $user->can(P::CrisisApprove->value);
    }
}
