<?php
/*
 * Created on   : Tue Oct 13 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : DeliveryNotePolicy.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Policies\Billing;

use App\Enums\Billing\DeliveryNoteStatus;
use App\Models\Billing\DeliveryNote;
use App\Models\Platform\User;

/**
 * Lieferschein (Phase 145, MVP-1163): Rechte der Belegverwaltung; aus der
 * Fertigung zusätzlich die des Fertigungsauftrags.
 */
class DeliveryNotePolicy {
    public function viewAny(User $user): bool {
        return $user->canManageBilling();
    }

    public function view(User $user, DeliveryNote $note): bool {
        return $user->canManageBilling() || ($note->manufacturingOrder !== null && $user->can('view', $note->manufacturingOrder));
    }

    /** Mengen im Entwurf ändern und versenden. */
    public function update(User $user, DeliveryNote $note): bool {
        return $user->canManageBilling() || ($note->manufacturingOrder !== null && $user->can('update', $note->manufacturingOrder));
    }

    public function deliver(User $user, DeliveryNote $note): bool {
        return $this->update($user, $note) && $note->status->canTransitionTo(DeliveryNoteStatus::Delivered);
    }

    public function cancel(User $user, DeliveryNote $note): bool {
        return $user->canManageBilling() && $note->status->canTransitionTo(DeliveryNoteStatus::Cancelled) && ! $note->hasStockDeliveries();
    }
}
