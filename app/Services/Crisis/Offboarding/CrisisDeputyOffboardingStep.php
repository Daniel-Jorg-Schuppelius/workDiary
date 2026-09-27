<?php
/*
 * Created on   : Sun Sep 27 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : CrisisDeputyOffboardingStep.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Crisis\Offboarding;

use App\Models\Crisis\CrisisTeamAssignment;
use App\Models\Platform\User;
use App\Services\Org\Contracts\OffboardingStep;

/** Austritt (MVP-941): Vertretungen im Krisenstab enden mit dem Austritt; die Rolle bleibt besetzt. */
final class CrisisDeputyOffboardingStep implements OffboardingStep {
    public function blockers(User $member): array {
        return [];
    }

    public function onExit(User $member): void {
        foreach (CrisisTeamAssignment::query()->withoutGlobalScopes()->where('deputy_user_id', $member->id)->get() as $assignment) {
            $assignment->forceFill(['deputy_user_id' => null])->save();
        }
    }
}
