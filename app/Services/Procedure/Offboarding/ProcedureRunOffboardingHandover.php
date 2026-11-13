<?php
/*
 * Created on   : Thu Nov 12 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ProcedureRunOffboardingHandover.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Procedure\Offboarding;

use App\Enums\Procedure\ProcedureRunStatus;
use App\Models\Platform\User;
use App\Models\Procedure\ProcedureRun;
use App\Models\Scopes\OrganizationScope;
use App\Services\Org\Contracts\OffboardingHandover;
use Illuminate\Database\Eloquent\Builder;

/**
 * Laufende, der Person zugewiesene Prozedurläufe an eine Nachfolge (Feature
 * 202, MVP-1648; auch beim Austritt). Erledigte Schritte und ihre
 * Unterschriften bleiben bei der Person, die sie ausgeführt hat.
 */
final class ProcedureRunOffboardingHandover implements OffboardingHandover {
    public function key(): string {
        return 'assigned_procedure_runs';
    }

    public function label(int $count): string {
        return trans_choice('delegation.handover.area.assigned_procedure_runs', $count, ['count' => $count]);
    }

    public function count(User $member): int {
        return $this->assigned($member)->count();
    }

    public function handOver(User $member, User $successor, User $actor): int {
        $moved = 0;
        foreach ($this->assigned($member)->orderBy('id')->get() as $run) {
            $run->update(['assigned_user_id' => $successor->id]);
            $moved++;
        }

        return $moved;
    }

    /** @return Builder<ProcedureRun> */
    private function assigned(User $member): Builder {
        return ProcedureRun::query()->withoutGlobalScope(OrganizationScope::class)
            ->where('organization_id', $member->organization_id)
            ->where('assigned_user_id', $member->id)
            ->whereIn('status', array_map(
                static fn (ProcedureRunStatus $status): string => $status->value,
                array_values(array_filter(ProcedureRunStatus::cases(), static fn (ProcedureRunStatus $status): bool => $status->isActive())),
            ));
    }
}
