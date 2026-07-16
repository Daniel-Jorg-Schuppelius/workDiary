<?php
/*
 * Created on   : Thu Jul 16 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : DomainProjectionPolicy.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Policies\Domain;

use App\Enums\User\Permission as P;
use App\Models\Domain\DomainProjection;
use App\Models\User;
use App\Policies\Concerns\HasAdminBypass;

/**
 * Domain-Portfolio (Feature 083): getrennte Rechte je Aktion. Registrierung,
 * Kontakt-, DNS-, Renewal- und Transferpflege sind eigene Fähigkeiten;
 * `approveDangerous` (Löschen/Push/Trade/Assign/Transfer-Out) ist die
 * Vier-Augen-Freigabe.
 */
class DomainProjectionPolicy {
    use HasAdminBypass;

    public function viewAny(User $user): bool {
        return $user->can(P::DomainViewAny->value);
    }

    public function view(User $user, DomainProjection $domain): bool {
        return $user->can(P::DomainView->value);
    }

    /** Kunden-/Reseller-Zuordnung pflegen. */
    public function assignCustomer(User $user, DomainProjection $domain): bool {
        return $user->can(P::DomainCustomerAssign->value);
    }

    /** Verfügbarkeit prüfen und registrieren. */
    public function register(User $user): bool {
        return $user->can(P::DomainRegister->value);
    }

    public function manageContacts(User $user, DomainProjection $domain): bool {
        return $user->can(P::DomainContactManage->value);
    }

    public function manageDns(User $user, DomainProjection $domain): bool {
        return $user->can(P::DomainDnsManage->value);
    }

    public function manageRenewal(User $user, DomainProjection $domain): bool {
        return $user->can(P::DomainRenewalManage->value);
    }

    public function manageTransfer(User $user, DomainProjection $domain): bool {
        return $user->can(P::DomainTransferManage->value);
    }

    /** Vier-Augen-Freigabe für Hochrisikoaktionen. */
    public function approveDangerous(User $user, DomainProjection $domain): bool {
        return $user->can(P::DomainDangerousApprove->value);
    }
}
