<?php
/*
 * Created on   : Thu Nov 12 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ProcedureRunWorkItemSource.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Procedure\WorkQueue;

use App\Enums\Procedure\ProcedureRunStatus;
use App\Enums\User\Permission;
use App\Enums\WorkQueue\WorkItemCategory;
use App\Models\Platform\{Organization, User};
use App\Models\Procedure\ProcedureRun;
use App\Services\Delegation\Contracts\DelegableWork;
use App\Services\WorkQueue\Contracts\WorkItemSource;
use App\Services\WorkQueue\Dto\WorkItem;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Gate;

/**
 * Laufende Prozedurläufe, die dem Nutzer zugewiesen sind (offen, in Arbeit,
 * blockiert) — „Meine Prozedurläufe“ im Arbeitsvorrat. Zugleich delegierbare
 * Arbeit (Feature 202, MVP-1648): die Vertretung sieht sie, gekennzeichnet „in
 * Vertretung für …“. Ausführen darf sie nur mit eigenem Ausführungsrecht —
 * Rolle und Qualifikation eines Schritts prüft der Lauf an der handelnden Person.
 */
final class ProcedureRunWorkItemSource implements DelegableWork, WorkItemSource {
    public function key(): string {
        return 'procedure_run';
    }

    public function label(): string {
        return (string) __('work-queue.source.procedure_run');
    }

    public function category(): WorkItemCategory {
        return WorkItemCategory::Task;
    }

    public function available(User $viewer): bool {
        return Gate::forUser($viewer)->allows(Permission::ProcedureRunView->value);
    }

    public function items(User $viewer, Organization $organization): iterable {
        $gate = Gate::forUser($viewer);
        $runs = $this->assigned($viewer, (int) $organization->id)
            ->with('templateVersion.template:id,name')
            ->orderBy('id')
            ->get();

        foreach ($runs as $run) {
            if (! $gate->allows('view', $run)) {
                continue;
            }
            yield new WorkItem(
                source: $this->key(),
                key: $run->getMorphClass() . ':' . $run->id,
                title: (string) ($run->templateVersion->template->name ?? __('work-queue.item.procedure_run')),
                url: route('procedure-runs.show', $run),
                detail: $run->status->label(),
                dueAt: null,
                personal: true,
                // Liegezeit (MVP-1670): letzte Änderung am Gegenstand.
                movedAt: WorkItem::lastMoved($run->updated_at),
            );
        }
    }

    public function count(User $user): int {
        return $this->assigned($user, (int) $user->organization_id)->count();
    }

    /** @return Builder<ProcedureRun> */
    private function assigned(User $user, int $organizationId): Builder {
        return ProcedureRun::query()
            ->where('organization_id', $organizationId)
            ->where('assigned_user_id', $user->id)
            ->whereIn('status', array_map(
                static fn (ProcedureRunStatus $status): string => $status->value,
                array_values(array_filter(ProcedureRunStatus::cases(), static fn (ProcedureRunStatus $status): bool => $status->isActive())),
            ));
    }
}
