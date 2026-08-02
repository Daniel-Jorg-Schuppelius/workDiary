<?php
/*
 * Created on   : Sun Aug 02 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : DisposalReportController.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Http\Controllers\Reporting;

use App\Http\Controllers\Controller;
use App\Models\Disposal\{DisposalItem, DisposalJob};
use App\Services\UI\DateRangeContext;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Gate;

/**
 * Entsorgungsbericht (Feature 100, MVP-476): entsorgte Mengen je Kunde,
 * Periode und AVV-Abfallschlüssel über abgeschlossene Entsorgungsakten.
 * Zeitraum kommt aus dem globalen Header-Zeitraum (AGENTS §8).
 */
class DisposalReportController extends Controller {
    public function index(DateRangeContext $range): View {
        Gate::authorize('viewAny', DisposalJob::class);

        $current = $range->current();
        $from = $current['from']->startOfDay();
        $to = $current['to']->endOfDay();

        $items = DisposalItem::query()
            ->whereHas('job', fn($q) => $q
                ->where('status', \App\Enums\Disposal\DisposalJobStatus::Completed->value)
                ->whereBetween('completed_at', [$from, $to]))
            ->with('job.customer')
            ->get();

        $jobCount = $items->pluck('disposal_job_id')->unique()->count();
        $deviceCount = (int) $items->sum('quantity');
        $totalWeight = (float) $items->sum(fn(DisposalItem $item): float => (float) ($item->weight_kg ?? 0));
        $hazardousWeight = (float) $items
            ->filter(fn(DisposalItem $item): bool => $item->is_hazardous)
            ->sum(fn(DisposalItem $item): float => (float) ($item->weight_kg ?? 0));

        $byCustomer = $items
            ->groupBy(fn(DisposalItem $item): string => $item->job->customer->name ?? (string) __('Ohne Kunde'))
            ->map(fn($group) => [
                'jobs' => $group->pluck('disposal_job_id')->unique()->count(),
                'devices' => (int) $group->sum('quantity'),
                'weight' => (float) $group->sum(fn(DisposalItem $item): float => (float) ($item->weight_kg ?? 0)),
                'hazardous_weight' => (float) $group->filter(fn(DisposalItem $item): bool => $item->is_hazardous)
                    ->sum(fn(DisposalItem $item): float => (float) ($item->weight_kg ?? 0)),
            ])
            ->sortByDesc('weight');

        $byWasteCode = $items
            ->groupBy('avv_code')
            ->map(fn($group, string $code) => [
                'devices' => (int) $group->sum('quantity'),
                'weight' => (float) $group->sum(fn(DisposalItem $item): float => (float) ($item->weight_kg ?? 0)),
                // Gefährlichkeit steckt kanonisch im Stern des Schlüssels.
                'is_hazardous' => str_ends_with($code, '*'),
            ])
            ->sortByDesc('weight');

        $byMonth = $items
            ->groupBy(fn(DisposalItem $item): string => $item->job->completed_at?->format('Y-m') ?? '')
            ->sortKeys()
            ->map(fn($group) => [
                'devices' => (int) $group->sum('quantity'),
                'weight' => (float) $group->sum(fn(DisposalItem $item): float => (float) ($item->weight_kg ?? 0)),
            ]);

        return view('disposal.reports', [
            'jobCount' => $jobCount,
            'deviceCount' => $deviceCount,
            'totalWeight' => $totalWeight,
            'hazardousWeight' => $hazardousWeight,
            'byCustomer' => $byCustomer,
            'byWasteCode' => $byWasteCode,
            'byMonth' => $byMonth,
        ]);
    }
}
