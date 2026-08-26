<?php
/*
 * Created on   : Tue Aug 25 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : SafetyInstructionPolicy.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Policies\Safety;

use App\Enums\User\Permission as P;
use App\Models\Safety\SafetyInstruction;
use App\Models\User;
use App\Policies\Concerns\HasAdminBypass;

/**
 * Unterweisungen (Feature 132): Register mit safety.viewAny ODER
 * safety.manage; die Nachweis-Ansicht sieht zusätzlich jede eingetragene
 * Teilnehmerin (sie muss ihre Teilnahme bestätigen können). Pflege nur
 * mit safety.manage.
 */
class SafetyInstructionPolicy {
    use HasAdminBypass;

    public function viewAny(User $user): bool {
        return $user->can(P::SafetyViewAny->value) || $user->can(P::SafetyManage->value);
    }

    public function view(User $user, SafetyInstruction $instruction): bool {
        return $this->viewAny($user)
            || $instruction->participants()->where('user_id', $user->id)->exists();
    }

    public function create(User $user): bool {
        return $user->can(P::SafetyManage->value);
    }

    public function update(User $user, SafetyInstruction $instruction): bool {
        unset($instruction);

        return $user->can(P::SafetyManage->value);
    }

    public function delete(User $user, SafetyInstruction $instruction): bool {
        return $this->update($user, $instruction);
    }
}
