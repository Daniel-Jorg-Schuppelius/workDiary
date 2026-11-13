<?php
/*
 * Created on   : Thu Nov 12 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ServicePlanPeriodCreator.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\ServicePlan;

use App\Enums\Diary\Status;
use App\Enums\Period\PeriodKind;
use App\Models\Customer\{Customer, CustomerService};
use App\Models\Diary\DiaryEntry;
use App\Models\Platform\{Organization, User};
use App\Models\Scopes\OrganizationScope;
use App\Services\Diary\FollowUpPeriodService;
use App\Services\ServicePlan\Dto\ServiceKey;
use App\Support\Period\PeriodKey;
use Illuminate\Validation\ValidationException;

/**
 * „Perioden des Jahres anlegen“ (Feature 199, MVP-1639) über die Folgeperiode
 * aus Feature 198: je Kunde mit der Leistung entsteht Periode für Periode der
 * Auftrag aus dem jüngsten Auftrag der Reihe (Januar aus dem Dezember des
 * Vorjahres, Februar aus dem eben angelegten Januar …) mit Zuständigkeit,
 * Prüfpflicht, Produktgruppe, Prozedur und Merkern; Dubletten fängt der
 * Folgeperioden-Dienst ab.
 *
 * Angelegt wird nur, wo der Leistungsumfang die Leistung in der Periode
 * führt und keine Zelle „entfällt“ trägt; nach einer solchen Periode geht die
 * Reihe mit dem letzten Auftrag davor weiter. Kunden ohne jeden Vorgänger
 * zählen als „ohne Vorgänger“ — ihren ersten Auftrag legt die Zelle an.
 */
final class ServicePlanPeriodCreator {
    public function __construct(
        private readonly FollowUpPeriodService $followUps,
        private readonly ServicePlanCells $cells,
    ) {}

    /**
     * @return array{created: int, skipped: int, without_source: int}
     */
    public function createYear(Organization $organization, ServiceKey $service, PeriodKind $kind, int $year, User $actor): array {
        if ($kind === PeriodKind::Year) {
            throw ValidationException::withMessages(['kind' => (string) __('service_plan.error.kind_not_periodic')]);
        }
        $yearPeriod = PeriodKey::of(PeriodKind::Year, $year);
        $customerIds = CustomerService::query()->withoutGlobalScope(OrganizationScope::class)
            ->where('organization_id', $organization->id)
            ->where('entry_type_id', $service->entryTypeId)
            ->where('period_kind', $kind->value)
            ->when($service->productGroup === null, static fn ($q) => $q->whereNull('product_group_code'), static fn ($q) => $q->where('product_group_code', $service->productGroup))
            ->validBetween($yearPeriod->start(), $yearPeriod->end())
            ->distinct()
            ->pluck('customer_id')
            ->map(static fn (mixed $id): int => (int) $id)
            ->all();

        $counts = ['created' => 0, 'skipped' => 0, 'without_source' => 0];
        $customers = Customer::query()->withoutGlobalScope(OrganizationScope::class)
            ->where('organization_id', $organization->id)
            ->whereIn('id', $customerIds)
            ->whereNull('archived_at')
            ->orderBy('name')
            ->get();
        foreach ($customers as $customer) {
            $latest = null;
            $series = $this->series($customer, $service, $kind);
            for ($ordinal = 1; $ordinal <= $kind->perYear(); $ordinal++) {
                $target = PeriodKey::of($kind, $year, $ordinal);
                $existing = $series[$target->key()] ?? null;
                $latest = $existing ?? $latest ?? $this->latestBefore($series, $target);
                if ($existing !== null || $this->cells->scope($customer, $service, $target) === null || $this->cells->waiver($customer, $service, $target) !== null) {
                    continue;
                }
                if ($latest === null) {
                    continue;
                }
                try {
                    $latest = $this->followUps->create($latest, $actor, $target);
                    $series[$target->key()] = $latest;
                    $counts['created']++;
                } catch (ValidationException) {
                    $counts['skipped']++;
                }
            }
            if ($latest === null) {
                $counts['without_source']++;
            }
        }

        return $counts;
    }

    /**
     * Nicht stornierte Aufträge der Reihe (Kunde, Leistung, Periodenart) je Periode.
     *
     * @return array<string, DiaryEntry>
     */
    private function series(Customer $customer, ServiceKey $service, PeriodKind $kind): array {
        $out = [];
        $entries = DiaryEntry::query()->withoutGlobalScopes()
            ->where('organization_id', $customer->organization_id)
            ->where('customer_id', $customer->id)
            ->where('entry_type_id', $service->entryTypeId)
            ->whereNotNull('period_key')
            ->where('status', '!=', Status::Cancelled->value)
            ->with('classifications')
            ->orderBy('id')
            ->get();
        foreach ($entries as $entry) {
            $period = $entry->period_key;
            if ($period !== null && $period->kind === $kind && $service->covers((int) $entry->entry_type_id, $entry->productGroupCode())) {
                $out[$period->key()] ??= $entry;
            }
        }

        return $out;
    }

    /** @param  array<string, DiaryEntry>  $series */
    private function latestBefore(array $series, PeriodKey $target): ?DiaryEntry {
        $latest = null;
        foreach ($series as $entry) {
            $period = $entry->period_key;
            if ($period !== null && $period->endsBefore($target) && ($latest?->period_key === null || $period->compareTo($latest->period_key) > 0)) {
                $latest = $entry;
            }
        }

        return $latest;
    }
}
