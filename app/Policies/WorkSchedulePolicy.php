<?php
/*
 * Created on   : Thu May 14 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : WorkSchedulePolicy.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Policies;

use App\Enums\User\Permission;
use App\Models\{User, WorkSchedule};
use App\Policies\Concerns\HasAdminBypass;

/**
 * Das Arbeitszeit-Modell darf nur von Personen mit `work-schedule.manage`
 * bearbeitet werden (per Default: Personalverwaltung + Geschäftsführung;
 * Admin via {@see HasAdminBypass}). Für alle anderen ist es read-only —
 * sie sehen lediglich ihr eigenes Modell.
 */
class WorkSchedulePolicy {
    use HasAdminBypass;

    public function viewAny(User $user): bool {
        return true;
    }

    public function view(User $user, WorkSchedule $schedule): bool {
        return $this->canManage($user) || (int) $user->id === (int) $schedule->user_id;
    }

    public function create(User $user): bool {
        return $this->canManage($user);
    }

    public function update(User $user, WorkSchedule $schedule): bool {
        return $this->canManage($user);
    }

    public function delete(User $user, WorkSchedule $schedule): bool {
        return $this->canManage($user);
    }

    private function canManage(User $user): bool {
        return $user->can(Permission::WorkScheduleManage->value);
    }
}
