<?php
/*
 * Created on   : Mon Jul 06 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : SlaQuotaService.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\ServiceTicket;

use App\Models\{DiaryEntry, Project, SlaContract, SlaContractQuota, TimeEntry};
use App\Models\Scopes\OrganizationScope;
use Carbon\{CarbonImmutable, CarbonInterface};

/**
 * Berechnet den Inklusivzeit-Verbrauch eines SLA-Kontingents (Feature 010 →
 * Rang 44).
 *
 * **Verbrauch** = Summe der Minuten der abrechenbaren (`billable`)
 * Zeiteinträge, die dem Vertragskunden im aktuellen Zeitraum zuzurechnen sind —
 * zugerechnet über das Projekt (`project.customer_id`) oder den verknüpften
 * Auftrag (`diary_entry.customer_id`) des Eintrags. Ist der Vertrag nicht an
 * einen Kunden gebunden (Default-Vertrag), zählt die gesamte abrechenbare Zeit
 * der Organisation im Zeitraum.
 *
 * Die Beträge (`overage_rate`/`flat_fee`) sind reine Nachweisfelder — die
 * Rechnungshoheit liegt beim externen Abrechnungsprogramm.
 */
class SlaQuotaService {
    /**
     * Aktueller Verbrauchsstand eines Kontingents (der Vertrag wird explizit
     * übergeben, damit auch scanner-/systemseitige Aufrufe ohne
     * Mandantenkontext funktionieren).
     *
     * @return array{period_key: string, start: string, end: string, included_minutes: int, consumed_minutes: int, remaining_minutes: int, over_minutes: int, percentage: int, threshold_reached: bool}
     */
    public function usage(SlaContract $contract, SlaContractQuota $quota, ?CarbonInterface $reference = null): array {
        $reference ??= CarbonImmutable::now();
        [$start, $end] = $quota->period_kind->window($reference);

        $included = (int) $quota->included_minutes;
        $consumed = $this->consumedMinutes($contract, $start, $end);
        $remaining = max(0, $included - $consumed);
        $over = max(0, $consumed - $included);
        $percentage = $included > 0 ? (int) floor($consumed * 100 / $included) : 100;

        return [
            'period_key' => $quota->period_kind->key($reference),
            'start' => $start->toDateString(),
            'end' => $end->toDateString(),
            'included_minutes' => $included,
            'consumed_minutes' => $consumed,
            'remaining_minutes' => $remaining,
            'over_minutes' => $over,
            'percentage' => $percentage,
            'threshold_reached' => $included > 0 && $percentage >= (int) $quota->warn_threshold_pct,
        ];
    }

    /**
     * Verbrauchte abrechenbare Minuten des Vertragskunden im Zeitfenster
     * [$start, $end] (inklusiv). Läuft ohne globalen Org-Scope (Scanner-Kontext);
     * die Mandantentrennung erfolgt über die explizite organization_id.
     */
    public function consumedMinutes(SlaContract $contract, CarbonInterface $start, CarbonInterface $end): int {
        $query = TimeEntry::query()
            ->withoutGlobalScope(OrganizationScope::class)
            ->where('organization_id', $contract->organization_id)
            ->where('billable', true)
            ->whereBetween('date', [$start->toDateString(), $end->toDateString()]);

        if ($contract->customer_id !== null) {
            $projectIds = Project::query()->withoutGlobalScope(OrganizationScope::class)
                ->where('organization_id', $contract->organization_id)
                ->where('customer_id', $contract->customer_id)
                ->pluck('id')->all();
            $diaryIds = DiaryEntry::query()->withoutGlobalScope(OrganizationScope::class)
                ->where('organization_id', $contract->organization_id)
                ->where('customer_id', $contract->customer_id)
                ->pluck('id')->all();

            $query->where(function ($sub) use ($projectIds, $diaryIds): void {
                $sub->whereIn('project_id', $projectIds);
                if ($diaryIds !== []) {
                    $sub->orWhereIn('diary_entry_id', $diaryIds);
                }
            });
        }

        return (int) $query->sum('minutes');
    }
}
