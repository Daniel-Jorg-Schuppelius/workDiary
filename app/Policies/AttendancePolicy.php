<?php
/*
 * Created on   : Sun May 17 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : AttendancePolicy.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Policies;

use App\Models\{Attendance, User};
use App\Policies\Concerns\{ChecksOwnership, HasAdminBypass};
use App\Services\TimeApproval\DayCloseService;

class AttendancePolicy {
    use ChecksOwnership;
    use HasAdminBypass;

    public function viewAny(User $user): bool {
        return true;
    }

    public function view(User $user, Attendance $attendance): bool {
        return $this->owns($user, $attendance, 'user_id');
    }

    public function create(User $user): bool {
        return true;
    }

    public function update(User $user, Attendance $attendance): bool {
        return $this->owns($user, $attendance, 'user_id')
            && ! $this->dayCloseLocked($attendance);
    }

    public function delete(User $user, Attendance $attendance): bool {
        return $this->owns($user, $attendance, 'user_id')
            && ! $this->dayCloseLocked($attendance);
    }

    /**
     * Tagesabschluss (MVP-015, docs/tagesabschluss.md §2.1/§5): Stempel
     * sind für den Eigentümer gesperrt, sobald der Tag abgeschlossen/in
     * Korrektur ist, nach einer Korrektur-Freigabe (attendance_locked)
     * oder wenn der Monat freigegeben ist. Admins umgehen das über
     * {@see HasAdminBypass}; reguläre Änderungen laufen über den
     * Korrektur-Antrag (MVP-015 §5 bzw. MVP-017).
     */
    private function dayCloseLocked(Attendance $attendance): bool {
        return app(DayCloseService::class)->attendanceEditLocked($attendance);
    }
}
