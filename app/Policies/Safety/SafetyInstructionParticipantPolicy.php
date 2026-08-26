<?php
/*
 * Created on   : Tue Aug 25 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : SafetyInstructionParticipantPolicy.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Policies\Safety;

use App\Models\Safety\SafetyInstructionParticipant;
use App\Models\User;

/**
 * Teilnahme-Nachweis (Feature 132): Signieren darf ausschließlich die
 * eingetragene Person selbst — bewusst OHNE Admin-Bypass, damit niemand
 * einen Nachweis für andere erzeugt.
 */
class SafetyInstructionParticipantPolicy {
    public function sign(User $user, SafetyInstructionParticipant $participant): bool {
        return (int) $participant->user_id === (int) $user->id && ! $participant->isSigned();
    }
}
