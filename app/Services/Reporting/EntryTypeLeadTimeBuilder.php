<?php
/*
 * Created on   : Thu Nov 12 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : EntryTypeLeadTimeBuilder.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Reporting;

use App\Enums\Diary\Status;
use App\Models\Classification\EntryType;
use App\Models\Diary\DiaryEntry;
use App\Models\Scopes\OrganizationScope;
use Carbon\CarbonImmutable;
use CommonToolkit\Helper\Data\NumberHelper;

/**
 * Durchlaufzeit je Auftragsart (Feature 179, MVP-1670): Median der Tage vom
 * Anlegen bis zum Abschluss (`completed_at`) der im Zeitraum abgeschlossenen
 * Aufträge, gegliedert nach Eintragsart. Gemessen wird am Vorgang, nie je
 * Person — der Builder kennt weder Bearbeitende noch Zuständige (Linie aus
 * Feature 002, 064, 065, 025: keine personenbezogenen Ranglisten).
 *
 * Gemeinsame Rechnung für die Auftragstypanalyse und die Kennzahl der
 * Leitungssicht ({@see Management\LeadTimeKpiSource}).
 */
final class EntryTypeLeadTimeBuilder {
    /**
     * @param  list<int>  $excludedCustomerIds  Feature 002: Aufträge ausgeblendeter Kunden entfallen (kundenlose bleiben)
     * @return list<array{entryTypeId: int, entryTypeName: string, completed: int, medianDays: float}> nach Anzahl absteigend
     */
    public function build(
        int $organizationId,
        CarbonImmutable $from,
        CarbonImmutable $to,
        array $excludedCustomerIds = [],
        ?int $entryTypeId = null,
        ?int $customerId = null,
        ?int $projectId = null,
    ): array {
        $entries = DiaryEntry::query()
            ->withoutGlobalScope(OrganizationScope::class)
            ->where('organization_id', $organizationId)
            ->whereNotNull('completed_at')
            ->where('status', '!=', Status::Cancelled->value)
            ->whereBetween('completed_at', [$from->startOfDay(), $to->endOfDay()])
            ->when($entryTypeId !== null, static fn ($q) => $q->where('entry_type_id', $entryTypeId))
            ->when($customerId !== null, static fn ($q) => $q->where('customer_id', $customerId))
            ->when($projectId !== null, static fn ($q) => $q->where('project_id', $projectId))
            // NOT IN würde NULL-Kunden mit verwerfen — kundenlose Aufträge bleiben sichtbar.
            ->when($excludedCustomerIds !== [], static fn ($q) => $q->where(
                static fn ($w) => $w->whereNull('customer_id')->orWhereNotIn('customer_id', $excludedCustomerIds),
            ))
            ->toBase()
            ->get(['entry_type_id', 'created_at', 'completed_at']);

        /** @var array<int, list<float>> $days */
        $days = [];
        foreach ($entries as $entry) {
            if ($entry->created_at === null || $entry->completed_at === null) {
                continue;
            }
            $start = CarbonImmutable::parse((string) $entry->created_at);
            $end = CarbonImmutable::parse((string) $entry->completed_at);
            $days[(int) ($entry->entry_type_id ?? 0)][] = max(0.0, $start->diffInSeconds($end) / 86400);
        }
        if ($days === []) {
            return [];
        }

        $labels = EntryType::query()
            ->withoutGlobalScope(OrganizationScope::class)
            ->whereIn('id', array_filter(array_keys($days)))
            ->pluck('label', 'id')
            ->all();

        $rows = [];
        foreach ($days as $typeId => $values) {
            $rows[] = [
                'entryTypeId' => $typeId,
                'entryTypeName' => $typeId > 0 ? (string) ($labels[$typeId] ?? ('#' . $typeId)) : (string) __('Ohne Auftragstyp'),
                'completed' => count($values),
                'medianDays' => round(NumberHelper::percentile($values, 50), 1),
            ];
        }
        usort($rows, static fn (array $a, array $b): int => [$b['completed'], $a['entryTypeName']] <=> [$a['completed'], $b['entryTypeName']]);

        return $rows;
    }
}
