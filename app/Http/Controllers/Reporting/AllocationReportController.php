<?php
/*
 * Created on   : Wed Aug 12 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : AllocationReportController.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Http\Controllers\Reporting;

use App\Enums\User\Permission;
use App\Http\Controllers\Concerns\ResolvesGlobalDateRange;
use App\Http\Controllers\Controller;
use App\Http\Controllers\Reporting\Concerns\{RendersReportPdf, WritesReportCsv};
use App\Models\TimeAllocation;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;

/**
 * Zeitaufteilungs-Auswertung (Feature 103, MVP-514 P3): aufgeteilte
 * Minuten je Dimension (Projekt, Kostenstelle, Standort, …) im Zeitraum —
 * gruppiert nach Dimensionstyp, mit CSV-/PDF-Export.
 *
 * Datenbasis sind ausschließlich die {@see TimeAllocation}-Anteile; nicht
 * aufgeteilte Zeit erscheint hier bewusst nicht (dafür gibt es die
 * bestehenden Zeit-Auswertungen).
 */
class AllocationReportController extends Controller {
    use RendersReportPdf;
    use ResolvesGlobalDateRange;
    use WritesReportCsv;

    public function index(Request $request): View|SymfonyResponse {
        Gate::authorize(Permission::ReportView->value);

        [$from, $to] = $this->resolveRange($request);
        $groups = $this->aggregate($from->toDateString(), $to->toDateString());
        $totalMinutes = array_sum(array_map(
            static fn (array $group): int => array_sum(array_column($group['rows'], 'minutes')),
            $groups,
        ));
        $filters = ['from' => $from->toDateString(), 'to' => $to->toDateString()];

        if ($request->query('export') === 'csv') {
            return $this->exportCsv($groups, $filters, $request);
        }
        if ($request->query('export') === 'pdf') {
            return $this->pdfDownload(
                'reports.pdf.allocations',
                ['groups' => $groups, 'totalMinutes' => $totalMinutes, 'from' => $filters['from'], 'to' => $filters['to']],
                'zeitaufteilung-' . $filters['from'] . '-' . $filters['to'] . '.pdf',
                request: $request,
                reportCode: 'allocations',
                filters: $filters,
            );
        }

        return view('reports.allocations', [
            'groups' => $groups,
            'totalMinutes' => $totalMinutes,
            'from' => $filters['from'],
            'to' => $filters['to'],
        ]);
    }

    /**
     * Aufgeteilte Minuten je Dimensionstyp und Ziel im Zeitraum. Freie
     * Mandanten-Dimensionen (P2) bilden je Dimensionstyp eine eigene Gruppe.
     *
     * @return array<string, array{label: string, rows: list<array{name: string, minutes: int, entries: int}>}> Gruppenschlüssel → Label + Zeilen (Minuten absteigend)
     */
    private function aggregate(string $from, string $to): array {
        $rows = TimeAllocation::query()
            ->join('time_entries', 'time_entries.id', '=', 'time_allocations.time_entry_id')
            ->whereBetween('time_entries.date', [$from, $to])
            ->groupBy('time_allocations.allocatable_type', 'time_allocations.allocatable_id')
            ->selectRaw('time_allocations.allocatable_type, time_allocations.allocatable_id, SUM(time_allocations.duration_minutes) as minutes, COUNT(DISTINCT time_allocations.time_entry_id) as entries')
            ->get();
        if ($rows->isEmpty()) {
            return [];
        }

        // Zielnamen je Typ in einem Rutsch auflösen (gelöschte Ziele → „—").
        $names = [];
        $dimensionMeta = [];
        foreach ($rows->groupBy('allocatable_type') as $type => $typeRows) {
            $alias = array_search((string) $type, TimeAllocation::TYPES, true);
            if ($alias === false) {
                continue;
            }
            $ids = $typeRows->pluck('allocatable_id')->all();
            if ($alias === 'dimension') {
                // Freie Dimension: Wert → Name + zugehöriger Typ (für die Gruppe).
                foreach (\App\Models\TimeDimensionValue::query()->whereIn('id', $ids)->with('type:id,name')->get() as $value) {
                    $names[$alias][(int) $value->id] = (string) $value->name;
                    $dimensionMeta[(int) $value->id] = [
                        'key' => 'dimension:' . (int) $value->dimension_type_id,
                        'label' => (string) ($value->type->name ?? '—'),
                    ];
                }

                continue;
            }
            $names[$alias] = match ($alias) {
                'cost_center' => \App\Models\CostCenter::query()->whereIn('id', $ids)->get(['id', 'code', 'label'])
                    ->mapWithKeys(fn (\App\Models\CostCenter $c): array => [(int) $c->id => trim($c->code . ' — ' . $c->label)])->all(),
                'task' => \App\Models\Task::query()->whereIn('id', $ids)->pluck('title', 'id')->all(),
                // Fahrzeuge haben kein name-Feld: Label + Kennzeichen.
                'vehicle' => \App\Models\Vehicle::query()->whereIn('id', $ids)->get(['id', 'label', 'license_plate'])
                    ->mapWithKeys(fn (\App\Models\Vehicle $v): array => [(int) $v->id => trim(($v->label ?? '') . ' ' . ($v->license_plate ?? '')) ?: '#' . $v->id])->all(),
                // Tätigkeiten haben label statt name.
                'activity_category' => \App\Models\ActivityCategory::query()->whereIn('id', $ids)->pluck('label', 'id')->all(),
                default => TimeAllocation::TYPES[$alias]::query()->whereIn('id', $ids)->pluck('name', 'id')->all(),
            };
        }

        $groups = [];
        foreach ($rows as $row) {
            $alias = array_search((string) $row->allocatable_type, TimeAllocation::TYPES, true);
            if ($alias === false) {
                continue;
            }
            $targetId = (int) $row->allocatable_id;
            $key = $alias;
            $label = (string) __('allocation.type.' . $alias);
            if ($alias === 'dimension') {
                $meta = $dimensionMeta[$targetId] ?? ['key' => 'dimension:?', 'label' => '—'];
                $key = $meta['key'];
                $label = $meta['label'];
            }

            $groups[$key]['label'] = $label;
            $groups[$key]['rows'][] = [
                'name' => (string) ($names[$alias][$targetId] ?? '—'),
                'minutes' => (int) $row->getAttribute('minutes'),
                'entries' => (int) $row->getAttribute('entries'),
            ];
        }

        foreach ($groups as &$group) {
            usort($group['rows'], static fn (array $a, array $b): int => $b['minutes'] <=> $a['minutes']);
        }
        ksort($groups);

        return $groups;
    }

    /**
     * @param  array<string, array{label: string, rows: list<array{name: string, minutes: int, entries: int}>}>  $groups
     * @param  array<string, mixed>  $filters
     */
    private function exportCsv(array $groups, array $filters, Request $request): SymfonyResponse {
        $rows = [[
            __('reporting.allocations.col_type'),
            __('reporting.allocations.col_target'),
            __('reporting.allocations.col_minutes'),
            __('reporting.allocations.col_entries'),
        ]];
        foreach ($groups as $group) {
            foreach ($group['rows'] as $row) {
                $rows[] = [$group['label'], $row['name'], $row['minutes'], $row['entries']];
            }
        }

        $filename = 'zeitaufteilung-' . $filters['from'] . '-' . $filters['to'] . '.csv';

        return $this->csvWithMetadata($rows, $filename, 'allocations', $filters, $request);
    }
}
