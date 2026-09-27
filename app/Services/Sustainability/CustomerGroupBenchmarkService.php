<?php
/*
 * Created on   : Sun Sep 27 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : CustomerGroupBenchmarkService.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Sustainability;

use App\Enums\Classification\ClassificationDomain;
use App\Models\Customer\Customer;
use App\Models\Sustainability\SustainabilityActivityRecord;
use App\Support\MorphMap;
use App\Support\Query\DateRange;

/**
 * ESG-Vergleich nach Kundengruppe (MVP-949): Emissionen der Aktivitäten mit
 * Kundenbezug je Kundengruppe (Klassifikation) und Jahr, dazu je Kunde.
 * Aktivitäten ohne Faktor zählen nicht mit.
 */
final class CustomerGroupBenchmarkService {
    public function __construct(private readonly EmissionCalculationService $emissions) {}

    /** @return list<array{group: string, customers: int, co2e_kg: float, per_customer_kg: float, missing: int}> */
    public function benchmark(int $organizationId, int $year): array {
        $query = SustainabilityActivityRecord::query()->where('organization_id', $organizationId)->where('subject_type', MorphMap::alias(Customer::class));
        $query->whereBetween('period_end', DateRange::days($year . '-01-01', $year . '-12-31'));
        $records = $query->get();
        if ($records->isEmpty()) {
            return [];
        }
        $customers = Customer::query()->whereIn('id', $records->pluck('subject_id')->unique())->with(['classifications' => fn ($q) => $q->where('domain', ClassificationDomain::CustomerGroup->value)])->get()->keyBy('id');
        $groups = [];
        foreach ($records as $record) {
            $customer = $customers->get((int) $record->subject_id);
            $group = (string) ($customer?->classifications->first()?->displayLabel() ?? __('sustainability.customer_group.none'));
            $groups[$group] ??= ['group' => $group, 'customers' => [], 'co2e_kg' => 0.0, 'missing' => 0];
            $groups[$group]['customers'][(int) $record->subject_id] = true;
            $co2e = $this->emissions->co2eFor($record)['co2e_kg'];
            if ($co2e === null) {
                $groups[$group]['missing']++;

                continue;
            }
            $groups[$group]['co2e_kg'] += $co2e;
        }
        $rows = [];
        foreach ($groups as $row) {
            $count = count($row['customers']);
            $rows[] = ['group' => $row['group'], 'customers' => $count, 'co2e_kg' => round($row['co2e_kg'], 1), 'per_customer_kg' => round($row['co2e_kg'] / max(1, $count), 1), 'missing' => $row['missing']];
        }
        usort($rows, static fn (array $a, array $b): int => $b['co2e_kg'] <=> $a['co2e_kg']);

        return $rows;
    }
}
