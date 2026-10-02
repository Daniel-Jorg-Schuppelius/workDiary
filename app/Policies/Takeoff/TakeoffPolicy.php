<?php
/*
 * Created on   : Fri Oct 02 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : TakeoffPolicy.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Policies\Takeoff;

use App\Enums\User\Permission;
use App\Models\Diary\DiaryEntry;
use App\Models\Gaeb\BillOfQuantity;
use App\Models\Platform\User;
use App\Models\Project\Project;
use App\Models\Takeoff\Takeoff;
use App\Policies\Concerns\ChecksOwnership;
use Illuminate\Database\Eloquent\Model;

/**
 * Aufmaß (MVP-1058): sehen, wer den Träger sieht; messen, wer das Blatt
 * angelegt hat oder Aufträge bearbeiten darf — solange es offen ist.
 */
class TakeoffPolicy {
    use ChecksOwnership;

    /** Admin-Bypass, aber ein abgeschlossenes Blatt bleibt auch für Admins gesperrt. */
    public function before(User $user, string $ability): ?bool {
        return $user->isAdmin() && ! in_array($ability, ['update', 'delete'], true) ? true : null;
    }

    public function view(User $user, Takeoff $takeoff): bool {
        $carrier = $takeoff->carrier();

        return $this->sharesOrganization($user, $takeoff) && $carrier !== null && $this->canViewCarrier($user, $carrier);
    }

    public function createFor(User $user, Model $carrier): bool {
        return $this->canViewCarrier($user, $carrier);
    }

    public function update(User $user, Takeoff $takeoff): bool {
        return $takeoff->isEditable() && $this->manages($user, $takeoff);
    }

    /** Abschließen und wieder öffnen — wiederöffnen auch nach dem Abschluss. */
    public function transition(User $user, Takeoff $takeoff): bool {
        return $this->manages($user, $takeoff);
    }

    public function delete(User $user, Takeoff $takeoff): bool {
        return $this->update($user, $takeoff);
    }

    private function manages(User $user, Takeoff $takeoff): bool {
        return $this->view($user, $takeoff)
            && ($this->owns($user, $takeoff, 'created_by') || $user->can(Permission::DiaryUpdate->value));
    }

    private function canViewCarrier(User $user, Model $carrier): bool {
        return match (true) {
            $carrier instanceof DiaryEntry, $carrier instanceof Project => $user->can('view', $carrier),
            $carrier instanceof BillOfQuantity => $this->sharesOrganization($user, $carrier) && $user->can(Permission::ProjectViewAny->value),
            default => false,
        };
    }
}
