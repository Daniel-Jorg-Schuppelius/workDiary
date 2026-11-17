<?php
/*
 * Created on   : Thu Nov 12 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : CarryForwardNotePolicy.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Policies\CarryForward;

use App\Models\CarryForward\CarryForwardNote;
use App\Models\Platform\User;
use App\Policies\Concerns\HasAdminBypass;

/**
 * Merker (Feature 201): Lesen, Anlegen und Abhaken folgen den Rechten am
 * Vorkommen (Controller). Zurückziehen darf nur, wer den offenen Merker
 * angelegt hat — ein abgehakter bleibt als Nachweis stehen.
 */
class CarryForwardNotePolicy {
    use HasAdminBypass;

    public function delete(User $user, CarryForwardNote $note): bool {
        return $note->isOpen()
            && (int) $note->organization_id === (int) $user->organization_id
            && (int) $note->created_by === (int) $user->id;
    }
}
