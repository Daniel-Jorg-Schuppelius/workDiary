<?php
/*
 * Created on   : Fri Jul 10 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ClaimCasePolicy.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Policies\Claims;

use App\Enums\User\Permission as P;
use App\Models\Claims\ClaimCase;
use App\Models\User;
use App\Policies\Concerns\HasAdminBypass;

/**
 * Reklamationsakten (Feature 072, MVP-246): Rollen trennen Annahme/Führung
 * (manage), Bewertung + Entscheidung (decide), kaufmännische Freigabe
 * (finance), Lagerprüfung/Rückläufer (warehouse) und Lieferantenregress
 * (recourse). Kunden sehen ihre Fälle nur über das Portal (guard customer,
 * strikte customer_id-Prüfung im Controller).
 */
class ClaimCasePolicy {
    use HasAdminBypass;

    public function viewAny(User $user): bool {
        return $user->can(P::ClaimViewAny->value);
    }

    public function view(User $user, ClaimCase $case): bool {
        return $user->can(P::ClaimView->value);
    }

    public function create(User $user): bool {
        return $user->can(P::ClaimManage->value);
    }

    public function update(User $user, ClaimCase $case): bool {
        return $user->can(P::ClaimManage->value);
    }

    /** Bewertung + Entscheidung (Anspruchsart, Kulanz, Ablehnung). */
    public function decide(User $user, ClaimCase $case): bool {
        return $user->can(P::ClaimDecide->value);
    }

    /** Kaufmännische Folgen freigeben/ausführen (Gutschrift/Storno/…). */
    public function finance(User $user, ClaimCase $case): bool {
        return $user->can(P::ClaimFinance->value);
    }

    /** RMA-Wareneingang, Prüfung, Quarantäne, Verwendungsentscheidung. */
    public function warehouse(User $user, ClaimCase $case): bool {
        return $user->can(P::ClaimWarehouse->value);
    }

    /** Lieferanten-/Herstellerregress führen. */
    public function recourse(User $user, ClaimCase $case): bool {
        return $user->can(P::ClaimRecourse->value);
    }
}
