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
use App\Policies\PermissionPolicy;

/**
 * Domain-Portfolio (Feature 083): getrennte Rechte je Aktion. Registrierung,
 * Kontakt-, DNS-, Renewal- und Transferpflege sind eigene Fähigkeiten;
 * `approveDangerous` (Löschen/Push/Trade/Assign/Transfer-Out) ist die
 * Vier-Augen-Freigabe.
 */
class DomainProjectionPolicy extends PermissionPolicy {
    use HasAdminBypass;

    protected const ABILITIES = [
        'viewAny' => P::DomainViewAny,
        'view' => P::DomainView,
        'assignCustomer' => P::DomainCustomerAssign,
        'register' => P::DomainRegister,
        'manageContacts' => P::DomainContactManage,
        'manageDns' => P::DomainDnsManage,
        'manageRenewal' => P::DomainRenewalManage,
        'manageTransfer' => P::DomainTransferManage,
        'approveDangerous' => P::DomainDangerousApprove,
    ];

    /** Kunden-/Reseller-Zuordnung pflegen. */
    public function assignCustomer(User $user, DomainProjection $domain): bool {
        return $this->allows($user, 'assignCustomer');
    }

    /** Verfügbarkeit prüfen und registrieren. */
    public function register(User $user): bool {
        return $this->allows($user, 'register');
    }

    public function manageContacts(User $user, DomainProjection $domain): bool {
        return $this->allows($user, 'manageContacts');
    }

    public function manageDns(User $user, DomainProjection $domain): bool {
        return $this->allows($user, 'manageDns');
    }

    public function manageRenewal(User $user, DomainProjection $domain): bool {
        return $this->allows($user, 'manageRenewal');
    }

    public function manageTransfer(User $user, DomainProjection $domain): bool {
        return $this->allows($user, 'manageTransfer');
    }

    /** Vier-Augen-Freigabe für Hochrisikoaktionen. */
    public function approveDangerous(User $user, DomainProjection $domain): bool {
        return $this->allows($user, 'approveDangerous');
    }
}
