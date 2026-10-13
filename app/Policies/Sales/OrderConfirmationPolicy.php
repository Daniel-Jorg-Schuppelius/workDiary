<?php
/*
 * Created on   : Tue Oct 13 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : OrderConfirmationPolicy.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Policies\Sales;

use App\Enums\Sales\OrderConfirmationStatus;
use App\Models\Platform\User;
use App\Models\Sales\OrderConfirmation;

/** Auftragsbestätigung (Phase 145, MVP-1162): dieselben Rechte wie das Angebot. */
class OrderConfirmationPolicy {
    public function viewAny(User $user): bool {
        return $user->canManageBilling();
    }

    public function view(User $user, OrderConfirmation $confirmation): bool {
        return $user->canManageBilling();
    }

    public function confirm(User $user, OrderConfirmation $confirmation): bool {
        return $user->canManageBilling() && $confirmation->status->canTransitionTo(OrderConfirmationStatus::Confirmed);
    }

    public function cancel(User $user, OrderConfirmation $confirmation): bool {
        return $user->canManageBilling() && $confirmation->status->canTransitionTo(OrderConfirmationStatus::Cancelled);
    }
}
