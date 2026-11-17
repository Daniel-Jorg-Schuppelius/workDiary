<?php
/*
 * Created on   : Thu Nov 12 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ServicePlanAssignments.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\ServicePlan;

use App\Models\Customer\CustomerDeadlineProfile;
use App\Models\Diary\DiaryEntry;
use App\Models\Platform\Organization;
use App\Models\Scopes\OrganizationScope;
use App\Services\ServicePlan\Dto\{ServicePlanCell, ServicePlanQuery};
use Carbon\CarbonImmutable;

/**
 * Offene Zellen des Leistungsplans einer Person (Feature 199, MVP-1639):
 * Grundlage der Karte „Meine Perioden“ und der Quelle „Überfällige Perioden“
 * im Arbeitsvorrat. Zuständig ist die Person am Auftrag, ohne Auftrag die
 * zuständige Person der Fristmerkmale des Kunden. Gelesen werden die
 * Periodensicht des laufenden Jahres und die Jahressicht von Vor- und
 * laufendem Jahr (Jahresleistungen entstehen nach Ablauf des Jahres); nur
 * Perioden, die schon begonnen haben.
 */
final class ServicePlanAssignments {
    public function __construct(private readonly ServicePlanBuilder $builder) {}

    /**
     * Offene Zellen (fehlt bis zur Prüfung) der Personen, überfällige zuerst, dann nach Fälligkeit.
     *
     * @param  list<int>  $userIds
     * @return list<ServicePlanCell>
     */
    public function pendingCells(Organization $organization, array $userIds, CarbonImmutable $today): array {
        $customerIds = $this->customerIds((int) $organization->id, $userIds);
        if ($customerIds === []) {
            return [];
        }
        $cells = [];
        foreach ([[ServicePlanQuery::VIEW_PERIODS, $today->year], [ServicePlanQuery::VIEW_YEAR, $today->year - 1], [ServicePlanQuery::VIEW_YEAR, $today->year]] as [$view, $year]) {
            $result = $this->builder->build($organization, new ServicePlanQuery(
                view: $view,
                year: $year,
                today: $today,
                responsibleIds: $userIds,
                customerIds: $customerIds,
            ));
            foreach ($result->rows as $row) {
                foreach ($row->cells as $cell) {
                    if ($cell->counts() && $cell->state->isPending() && in_array($cell->responsibleId, $userIds, true)
                        && ! $cell->period->start()->greaterThan($today)) {
                        $cells[] = $cell;
                    }
                }
            }
        }
        usort($cells, static fn (ServicePlanCell $a, ServicePlanCell $b): int => [$a->overdue ? 0 : 1, $a->due?->getTimestamp() ?? PHP_INT_MAX, $a->customerId]
            <=> [$b->overdue ? 0 : 1, $b->due?->getTimestamp() ?? PHP_INT_MAX, $b->customerId]);

        return $cells;
    }

    /**
     * Kunden, bei denen die Personen zuständig sind (Fristmerkmale) oder Aufträge mit Periode haben.
     *
     * @param  list<int>  $userIds
     * @return list<int>
     */
    public function customerIds(int $organizationId, array $userIds): array {
        if ($userIds === []) {
            return [];
        }
        $fromProfiles = CustomerDeadlineProfile::query()->withoutGlobalScope(OrganizationScope::class)
            ->where('organization_id', $organizationId)
            ->whereIn('responsible_user_id', $userIds)
            ->distinct()
            ->pluck('customer_id')
            ->all();
        $fromOrders = DiaryEntry::query()->withoutGlobalScopes()
            ->where('organization_id', $organizationId)
            ->whereIn('assigned_user_id', $userIds)
            ->whereNotNull('period_key')
            ->whereNotNull('customer_id')
            ->distinct()
            ->pluck('customer_id')
            ->all();

        return array_values(array_unique(array_map(static fn (mixed $id): int => (int) $id, [...$fromProfiles, ...$fromOrders])));
    }
}
