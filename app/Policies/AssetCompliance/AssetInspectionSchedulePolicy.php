<?php
/*
 * Created on   : Fri Jul 10 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : AssetInspectionSchedulePolicy.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Policies\AssetCompliance;

use App\Enums\User\Permission as P;
use App\Models\AssetCompliance\AssetInspectionSchedule;
use App\Models\User;
use App\Policies\Concerns\HasAdminBypass;

/**
 * Prüftermin-Policy: notwendig für die Einladung externer Prüfer über
 * ExternalParticipant (manageForSubject prüft update am Subject, MVP-290).
 */
class AssetInspectionSchedulePolicy {
    use HasAdminBypass;

    public function view(User $user, AssetInspectionSchedule $schedule): bool {
        return $user->can(P::AssetComplianceView->value);
    }

    public function update(User $user, AssetInspectionSchedule $schedule): bool {
        return $user->can(P::AssetComplianceManage->value)
            || $user->can(P::AssetComplianceInspect->value);
    }
}
