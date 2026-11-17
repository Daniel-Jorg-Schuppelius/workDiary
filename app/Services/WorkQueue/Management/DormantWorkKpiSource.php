<?php
/*
 * Created on   : Thu Nov 12 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : DormantWorkKpiSource.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\WorkQueue\Management;

use App\Models\Platform\{Organization, User};
use App\Services\Reporting\Contracts\ManagementKpiSource;
use App\Services\Reporting\Dto\ManagementKpi;
use App\Services\WorkQueue\Dto\WorkItem;
use App\Services\WorkQueue\WorkQueue;
use Carbon\CarbonImmutable;

/**
 * Kennzahl „ruhende Vorgänge“ der Leitungssicht (Feature 179/184, MVP-1670):
 * Vorgänge im Arbeitsvorrat des Betrachters, die seit der Schwelle der
 * Organisation (Vorgabe 14 Tage) nicht bewegt wurden — Stand heute, unabhängig
 * vom Zeitraum. Nachrechenbar über den Filter „Nur ruhende“ im Arbeitsvorrat;
 * sie zählt Vorgänge, nie Personen. Ohne Vorgang mit „zuletzt bewegt“ entfällt
 * die Kachel.
 */
final class DormantWorkKpiSource implements ManagementKpiSource {
    public function __construct(private readonly WorkQueue $queue) {}

    public function group(): string {
        return 'operations';
    }

    public function order(): int {
        return 50;
    }

    public function kpis(User $viewer, Organization $organization, CarbonImmutable $from, CarbonImmutable $to): array {
        $now = CarbonImmutable::now();
        $threshold = $this->queue->dormantDays($organization);
        $tracked = array_values(array_filter(
            $this->queue->itemsFor($viewer, $organization, now: $now),
            static fn (WorkItem $item): bool => $item->movedAt !== null,
        ));
        if ($tracked === []) {
            return [];
        }
        $dormant = count(array_filter($tracked, static fn (WorkItem $item): bool => $item->isDormant($now, $threshold)));

        return [
            new ManagementKpi(
                __('work-queue.kpi.dormant'),
                (string) $dormant,
                __('work-queue.kpi.dormant_hint', ['days' => $threshold]),
                $dormant > 0 ? 'warning' : 'success',
                route('work-queue.index', ['dormant' => 1, 'sort' => 'idle', 'dir' => 'desc']),
            ),
        ];
    }
}
