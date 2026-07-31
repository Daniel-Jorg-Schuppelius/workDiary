<?php
/*
 * Created on   : Mon Jul 06 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : DataQualityReportController.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Http\Controllers\Reporting;

use App\Enums\User\Permission;
use App\Http\Controllers\Concerns\ResolvesGlobalDateRange;
use App\Http\Controllers\Controller;
use App\Http\Controllers\Reporting\Concerns\ResolvesStandardReportFilters;
use App\Models\{Customer, DiaryEntry, User};
use App\Services\Classification\DataQualityInspector;
use App\Support\CarbonFmt;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Datenqualitäts-Report „Objekte ohne Pflichtklassifikation" (Feature 024 →
 * Rang 57): listet Aufträge des Zeitraums mit fehlenden Pflichtklassifikationen,
 * aggregiert nach Domäne, Phase und Schwere, mit Direktlink zum Auftrag (dort
 * lässt sich die Klassifikation nachtragen). Rein lesend — nichts wird erzwungen.
 *
 * Hinweis: Pflichtklassifikationen sind heute an die Auftragsart
 * (`entry_type_code`) gebunden; Assets/Artikel haben keine eigenen
 * Anforderungen und sind daher (noch) nicht Teil des Reports.
 */
class DataQualityReportController extends Controller {
    use ResolvesGlobalDateRange;
    use ResolvesStandardReportFilters;

    public function __construct(private readonly DataQualityInspector $inspector) {}

    public function index(Request $request): View {
        $user = $request->user();
        abort_unless($user instanceof User && $user->can(Permission::ReportView->value), 403);

        [$from, $to] = $this->resolveRange($request);
        $filters = $this->standardFilters($request, ['customer', 'project', 'entry_type'], $from, $to);

        $entriesQuery = DiaryEntry::query()
            ->with(['entryType', 'customer:id,name'])
            ->where('is_archived', false)
            ->overlappingDateRange($from->toDateString(), $to->toDateString())
            ->orderByDesc('start_at')
            ->limit(1000);
        $filters->applyToDiaryEntryQuery($entriesQuery);
        $entries = $entriesQuery->get();

        $report = $this->inspector->report($entries);

        /** @var array<int, string> $customerByEntry */
        $customerByEntry = [];
        foreach ($entries as $entry) {
            $customerByEntry[(int) $entry->id] = $entry->customer instanceof Customer
                ? (string) $entry->customer->name
                : (string) __('Ohne Kunde');
        }

        return view('reports.data-quality', $report + [
            'label' => CarbonFmt::fdate($from) . ' – ' . CarbonFmt::fdate($to),
            'from' => $from->toDateString(),
            'to' => $to->toDateString(),
            'standardFilters' => $filters,
            'filterFields' => ['customer', 'project', 'entry_type'],
            'gapsMonthlySeries' => $this->gapsMonthlySeries($report['rows'], $from, $to),
            'gapsByCustomerSeries' => $this->gapsByCustomerSeries($report['rows'], $customerByEntry),
            ...$this->standardFilterOptions(['customer', 'project', 'entry_type'], $filters),
        ]);
    }

    /**
     * Aufträge mit fehlenden Pflichtklassifikationen je Monat.
     *
     * @param  list<array{id:int, sqid:string, title:string, date:string|null, gaps: list<array{domain:string, label:string, severity:string, blocking:bool, phase:string}>}>  $rows
     * @return list<array{x: string, y: int}>
     */
    private function gapsMonthlySeries(array $rows, CarbonImmutable $from, CarbonImmutable $to): array {
        if ($rows === []) {
            return []; // Leerzustand statt Null-Serie (§Diagramm-UX).
        }

        $months = $this->buildMonthsInRange($from, $to);
        /** @var array<string, int> $byMonth */
        $byMonth = [];
        foreach ($months as $month) {
            $byMonth[$month['key']] = 0;
        }
        foreach ($rows as $row) {
            if ($row['date'] === null) {
                continue;
            }
            $monthKey = substr($row['date'], 0, 7);
            if (array_key_exists($monthKey, $byMonth)) {
                $byMonth[$monthKey]++;
            }
        }

        $series = [];
        foreach ($months as $month) {
            $series[] = ['x' => $month['shortLabel'], 'y' => $byMonth[$month['key']]];
        }

        return $series;
    }

    /**
     * Fehlende Pflichtklassifikationen je Kunde (Top 15).
     *
     * @param  list<array{id:int, sqid:string, title:string, date:string|null, gaps: list<array{domain:string, label:string, severity:string, blocking:bool, phase:string}>}>  $rows
     * @param  array<int, string>  $customerByEntry
     * @return list<array{x: string, y: int}>
     */
    private function gapsByCustomerSeries(array $rows, array $customerByEntry): array {
        /** @var array<string, int> $byCustomer */
        $byCustomer = [];
        foreach ($rows as $row) {
            $name = $customerByEntry[$row['id']] ?? (string) __('Ohne Kunde');
            $byCustomer[$name] = ($byCustomer[$name] ?? 0) + count($row['gaps']);
        }
        arsort($byCustomer);

        $series = [];
        foreach (array_slice($byCustomer, 0, 15, true) as $name => $gapCount) {
            $series[] = ['x' => $name, 'y' => $gapCount];
        }

        return $series;
    }
}
