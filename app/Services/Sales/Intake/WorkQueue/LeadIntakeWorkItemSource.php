<?php
/*
 * Created on   : Thu Nov 12 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : LeadIntakeWorkItemSource.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Sales\Intake\WorkQueue;

use App\Enums\Sales\{LeadIntakeDecision, LeadIntakeStage};
use App\Enums\User\Permission;
use App\Enums\WorkQueue\WorkItemCategory;
use App\Models\Platform\{Organization, User};
use App\Models\Sales\LeadIntake;
use App\Models\Scopes\OrganizationScope;
use App\Services\WorkQueue\Contracts\WorkItemSource;
use App\Services\WorkQueue\Dto\WorkItem;
use App\Support\Query\DateRange;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Gate;

/**
 * Aufnahmen im Arbeitsvorrat (Feature 200, MVP-1641/1643):
 *
 * - für Personen mit Entscheidungsrecht: Akten in der Stufe „Entscheidung“
 *   ohne Entscheidung und Annahmen, die auf den zweiten Blick warten — nie
 *   die eigene Annahme;
 * - für die verantwortliche Person des Leads: Aktualisierungen, die in den
 *   nächsten 30 Tagen fällig oder überfällig sind.
 */
final class LeadIntakeWorkItemSource implements WorkItemSource {
    private const int REFRESH_HORIZON_DAYS = 30;

    public function key(): string {
        return 'lead_intake';
    }

    public function label(): string {
        return (string) __('work-queue.source.lead_intake');
    }

    public function category(): WorkItemCategory {
        return WorkItemCategory::Approval;
    }

    public function available(User $viewer): bool {
        return Gate::forUser($viewer)->allows('viewAny', LeadIntake::class);
    }

    public function items(User $viewer, Organization $organization): iterable {
        $gate = Gate::forUser($viewer);
        if ($viewer->can(Permission::LeadIntakeDecide->value)) {
            $pending = LeadIntake::query()->withoutGlobalScope(OrganizationScope::class)
                ->where('organization_id', $organization->id)
                ->whereNull('superseded_at')
                ->where('stage', LeadIntakeStage::Decision->value)
                ->where(static fn ($q) => $q->whereNull('decision')
                    ->orWhere(static fn ($w) => $w->where('decision', LeadIntakeDecision::Accepted->value)->where('decider_user_id', '!=', $viewer->id)))
                ->with('lead:id,organization_id,company,contact_name')
                ->orderBy('updated_at')
                ->limit(50)
                ->get();
            foreach ($pending as $intake) {
                if (! $gate->allows('view', $intake) || $intake->lead === null) {
                    continue;
                }
                yield new WorkItem(
                    source: $this->key(),
                    key: $intake->getMorphClass() . ':' . $intake->id,
                    title: (string) __($intake->awaitsSecondReview() ? 'lead_intake.work.second_review' : 'lead_intake.work.decide', ['name' => $intake->lead->displayName()]),
                    url: route('leads.intakes.show', [$intake->lead, $intake]),
                    detail: $intake->stage->label(),
                    // Liegezeit (MVP-1670): letzte Änderung am Gegenstand.
                    movedAt: WorkItem::lastMoved($intake->updated_at),
                );
            }
        }

        $until = CarbonImmutable::today()->addDays(self::REFRESH_HORIZON_DAYS);
        $refresh = LeadIntake::query()->withoutGlobalScope(OrganizationScope::class)
            ->where('organization_id', $organization->id)
            ->whereNull('superseded_at')
            ->whereNotNull('refresh_due_on')
            ->where('refresh_due_on', '<', DateRange::dayAfter($until))
            ->whereHas('lead', static fn ($q) => $q->where('responsible_user_id', $viewer->id))
            ->with('lead:id,organization_id,company,contact_name')
            ->orderBy('refresh_due_on')
            ->limit(50)
            ->get();
        foreach ($refresh as $intake) {
            if (! $gate->allows('view', $intake) || $intake->lead === null || $intake->refresh_due_on === null) {
                continue;
            }
            yield new WorkItem(
                source: $this->key(),
                key: $intake->getMorphClass() . ':refresh:' . $intake->id,
                title: (string) __('lead_intake.work.refresh', ['name' => $intake->lead->displayName()]),
                url: route('leads.intakes.show', [$intake->lead, $intake]),
                detail: $intake->stage->label(),
                dueAt: CarbonImmutable::instance($intake->refresh_due_on)->startOfDay(),
                personal: true,
            );
        }
    }
}
