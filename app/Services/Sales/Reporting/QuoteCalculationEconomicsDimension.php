<?php
/*
 * Created on   : Fri Oct 02 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : QuoteCalculationEconomicsDimension.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Sales\Reporting;

use App\Models\Sales\Quote;
use App\Services\Reporting\Contracts\ProjectEconomicsDimension;
use App\Services\Reporting\EconomicsReportBuilder;
use Carbon\CarbonImmutable;

/**
 * Nachkalkulation gegen die Angebotskalkulation (MVP-1055), Dimension
 * `quote_calculation`: Soll aus den angenommenen Angeboten des Projekts
 * (Kalkulationsschnappschuss × Menge), Ist aus dem Wirtschaftlichkeitsbericht
 * im gewählten Zeitraum. Positionen ohne Kalkulation zählen nur im Erlös.
 */
final class QuoteCalculationEconomicsDimension implements ProjectEconomicsDimension {
    public function __construct(private readonly EconomicsReportBuilder $economics) {}

    public function key(): string {
        return 'quote_calculation';
    }

    /**
     * @return array{
     *   quotes: int, calculatedLines: int,
     *   planned: array{minutes: float, costs: array<string, float>, cost: float, revenue: float},
     *   actual: array{minutes: int, costTime: float, costMaterial: float, cost: float, revenue: float}|null
     * }
     */
    public function build(CarbonImmutable $from, CarbonImmutable $to, int $projectId): array {
        $quotes = Quote::query()
            ->where('project_id', $projectId)
            ->whereIn('status', ['accepted', 'partially_accepted'])
            ->with('items')
            ->get();

        $minutes = 0.0;
        $costs = [];
        $revenue = 0.0;
        $calculated = 0;
        foreach ($quotes as $quote) {
            foreach ($quote->items as $item) {
                if (! $item->countsInTotal()) {
                    continue;
                }
                $revenue += $item->netAmount()->toFloat();
                $snapshot = $item->calculation;
                if (! is_array($snapshot)) {
                    continue;
                }
                $calculated++;
                $quantity = (float) $item->quantity;
                $minutes += (float) ($snapshot['labour_minutes'] ?? 0) * $quantity;
                foreach ((array) ($snapshot['kinds'] ?? []) as $kind => $row) {
                    $costs[$kind] = ($costs[$kind] ?? 0.0) + (float) ($row['cost'] ?? 0) * $quantity;
                }
            }
        }

        $actualRow = $this->economics->byProject($from, $to, [$projectId])[0] ?? null;

        return [
            'quotes' => $quotes->count(),
            'calculatedLines' => $calculated,
            'planned' => [
                'minutes' => round($minutes, 2),
                'costs' => array_map(static fn (float $v): float => round($v, 2), $costs),
                'cost' => round(array_sum($costs), 2),
                'revenue' => round($revenue, 2),
            ],
            'actual' => $actualRow === null ? null : [
                'minutes' => (int) $actualRow['totalMinutes'],
                'costTime' => (float) $actualRow['costTime'],
                'costMaterial' => (float) $actualRow['costMaterial'],
                'cost' => (float) $actualRow['cost'],
                'revenue' => (float) $actualRow['revenue'],
            ],
        ];
    }
}
