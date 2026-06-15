<?php
/*
 * Created on   : Sat Jun 14 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ShiftExchangePolicy.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Policies;

use App\Enums\User\Permission;
use App\Models\{ShiftExchange, User};
use App\Policies\Concerns\{ChecksOwnership, HasAdminBypass};

/**
 * Schichttausch (Feature 007):
 *  - request/cancel: jeder Mitarbeiter mit shift.exchange (für eigene Schicht)
 *  - accept: der gewünschte Ziel-Kollege (offene Abgabe: jeder mit shift.exchange)
 *  - approve/reject: nur mit shift.exchange.approve (Teamleitung)
 */
class ShiftExchangePolicy {
    use ChecksOwnership;
    use HasAdminBypass;

    public function viewAny(User $user): bool {
        return $user->hasPermissionTo(Permission::ShiftExchangeRequest->value)
            || $user->hasPermissionTo(Permission::ShiftExchangeApprove->value);
    }

    public function view(User $user, ShiftExchange $exchange): bool {
        if (! $this->sharesOrganization($user, $exchange)) {
            return false;
        }

        return $this->owns($user, $exchange, 'requested_by_user_id')
            || $this->owns($user, $exchange, 'target_user_id')
            || $user->hasPermissionTo(Permission::ShiftExchangeApprove->value);
    }

    public function create(User $user): bool {
        return $user->hasPermissionTo(Permission::ShiftExchangeRequest->value);
    }

    public function accept(User $user, ShiftExchange $exchange): bool {
        if (! $this->sharesOrganization($user, $exchange)) {
            return false;
        }
        if (! $user->hasPermissionTo(Permission::ShiftExchangeRequest->value)) {
            return false;
        }
        // Festgelegter Ziel-Kollege oder offene Abgabe (target NULL).
        return $exchange->target_user_id === null
            || $this->owns($user, $exchange, 'target_user_id');
    }

    public function cancel(User $user, ShiftExchange $exchange): bool {
        return $this->owns($user, $exchange, 'requested_by_user_id')
            && $user->hasPermissionTo(Permission::ShiftExchangeRequest->value);
    }

    public function decide(User $user, ShiftExchange $exchange): bool {
        return $this->sharesOrganization($user, $exchange)
            && $user->hasPermissionTo(Permission::ShiftExchangeApprove->value);
    }
}
