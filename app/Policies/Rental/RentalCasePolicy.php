<?php
/*
 * Created on   : Fri Jul 10 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : RentalCasePolicy.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Policies\Rental;

use App\Enums\User\Permission as P;
use App\Models\Rental\RentalCase;
use App\Models\User;
use App\Policies\Concerns\HasAdminBypass;

/**
 * Policy des Verleih-Aggregats (Feature 073). Kind-Objekte (Reservierungen,
 * Protokolle, Positionen, Kautionen) werden gegen die Akte autorisiert.
 * Org-Scoping läuft global über BelongsToOrganization + Sqid-Binding.
 */
class RentalCasePolicy {
    use HasAdminBypass;

    public function viewAny(User $user): bool {
        return $user->can(P::RentalViewAny->value);
    }

    public function view(User $user, RentalCase $case): bool {
        return $user->can(P::RentalView->value);
    }

    public function create(User $user): bool {
        return $user->can(P::RentalManage->value);
    }

    public function update(User $user, RentalCase $case): bool {
        return $user->can(P::RentalManage->value);
    }

    /**
     * Übergabe- und Rücknahmeprotokolle (operative Ausgabe).
     */
    public function handover(User $user, RentalCase $case): bool {
        return $user->can(P::RentalHandover->value);
    }

    /**
     * Kaufmännische Folge: Positionen freigeben/abrechnen, Kaution führen.
     */
    public function finance(User $user, RentalCase $case): bool {
        return $user->can(P::RentalFinance->value);
    }
}
