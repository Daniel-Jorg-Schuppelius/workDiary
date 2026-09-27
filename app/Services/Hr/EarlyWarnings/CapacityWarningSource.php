<?php
/*
 * Created on   : Sun Sep 27 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : CapacityWarningSource.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Hr\EarlyWarnings;

use App\Models\Platform\Organization;
use App\Services\Hr\CapacityPlanningService;
use App\Services\Reporting\Contracts\EarlyWarningSource;
use App\Services\Reporting\Dto\EarlyWarning;
use App\Support\Setting;

/** Frühwarnung (MVP-940): Teams, deren geplanter Bedarf die Kapazität der nächsten Wochen übersteigt. */
final class CapacityWarningSource implements EarlyWarningSource {
    public function __construct(private readonly CapacityPlanningService $capacity) {}

    public function key(): string {
        return 'capacity';
    }

    public function warnings(Organization $organization): array {
        $threshold = max(50, (int) Setting::get('hr.capacity.threshold', 100));
        $warnings = [];
        foreach ($this->capacity->plan($organization, 4) as $row) {
            if ($row['team'] === null) {
                continue;
            }
            $peak = collect($row['weeks'])->filter(static fn (array $w): bool => $w['utilization'] !== null && $w['utilization'] > $threshold)->sortByDesc('utilization')->first();
            if ($peak === null) {
                continue;
            }
            $warnings[] = new EarlyWarning(
                kind: 'capacity',
                subject: $row['team'],
                title: 'reporting.warning.capacity.title',
                detail: 'reporting.warning.capacity.detail',
                recommendation: 'reporting.warning.capacity.recommendation',
                url: route('teams.capacity'),
                params: ['name' => $row['team']->name, 'percent' => $peak['utilization'], 'week' => $peak['start']->format('d.m.Y')],
            );
        }

        return $warnings;
    }
}
