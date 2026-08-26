<?php
/*
 * Created on   : Tue Aug 25 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : MedicalCheckupPolicy.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Policies\Safety;

use App\Enums\User\Permission as P;
use App\Models\Safety\MedicalCheckup;
use App\Models\User;
use App\Policies\Concerns\HasAdminBypass;

/**
 * Vorsorge-Register (Feature 132): lesen mit safety.viewAny ODER
 * safety.manage, pflegen nur mit safety.manage. Enthält keine
 * Gesundheitsdaten (nur Art/Termin/Bescheinigung) — daher dieselbe
 * Rechte-Ebene wie die übrigen Arbeitsschutz-Register.
 */
class MedicalCheckupPolicy {
    use HasAdminBypass;

    public function viewAny(User $user): bool {
        return $user->can(P::SafetyViewAny->value) || $user->can(P::SafetyManage->value);
    }

    public function view(User $user, MedicalCheckup $checkup): bool {
        unset($checkup);

        return $this->viewAny($user);
    }

    public function create(User $user): bool {
        return $user->can(P::SafetyManage->value);
    }

    public function update(User $user, MedicalCheckup $checkup): bool {
        unset($checkup);

        return $user->can(P::SafetyManage->value);
    }

    public function delete(User $user, MedicalCheckup $checkup): bool {
        return $this->update($user, $checkup);
    }
}
