<?php
/*
 * Created on   : Tue Sep 22 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ClubAttendanceSheetPolicy.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Policies\Club;

use App\Models\Club\ClubAttendanceSheet;
use App\Models\Platform\User;
use App\Policies\Concerns\HasAdminBypass;

/**
 * Anwesenheitslisten (MVP-844): Register liest alle, Gruppenleitung sieht
 * und erfasst nur Listen ihrer Zielgruppen; Erfassen, Bestätigen und
 * Korrigieren teilen dasselbe Recht. Mitglieder sehen eigene Nachweise über
 * „Mein Verein" (MVP-845).
 */
class ClubAttendanceSheetPolicy {
    use ClubAccess;
    use HasAdminBypass;

    public function viewAny(User $user): bool {
        return $this->canReadRegister($user) || $this->isGroupLead($user);
    }

    public function view(User $user, ClubAttendanceSheet $sheet): bool {
        return $this->canReadRegister($user) || $this->leadsTargetGroupOf($user, $sheet);
    }

    /** Erfassen, bestätigen, wieder öffnen, korrigieren, spontan ergänzen, Überschneidung klären. */
    public function record(User $user, ClubAttendanceSheet $sheet): bool {
        return $this->canManage($user) || $this->leadsTargetGroupOf($user, $sheet);
    }

    public function export(User $user): bool {
        return $this->viewAny($user);
    }

    private function leadsTargetGroupOf(User $user, ClubAttendanceSheet $sheet): bool {
        if (! $this->isGroupLead($user)) {
            return false;
        }
        $event = $sheet->event;

        return $event !== null && $event->clubGroups()->where('leader_user_id', $user->id)->exists();
    }
}
