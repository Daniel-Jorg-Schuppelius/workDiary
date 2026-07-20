<?php
/*
 * Created on   : Sun Jul 20 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : AiUsageReportController.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Http\Controllers\Admin\Ai;

use App\Enums\Ai\AiFamily;
use App\Http\Controllers\Controller;
use App\Http\Controllers\Reporting\Concerns\WritesReportCsv;
use App\Models\Ai\{AiProviderConnection, AiTextSuggestion, AiUsagePeriod};
use App\Models\Organization;
use App\Services\Ai\AiBudgetService;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\Response;

/**
 * KI-Verbrauchsbericht als eigene Auswertungsseite (Feature 025,
 * Phase-36-Restpunkt „Verbrauchsbericht"): Monatsverlauf je Familie aus
 * ai_usage_periods (12 Monate), Budgetauslastung des laufenden Monats und
 * der Vorschlags-Funnel je Capability aus ai_text_suggestions
 * (Übernahmequote, Cache-/Fallback-Anteile). CSV-Export nach dem
 * Report-Standard (MVP-043, WritesReportCsv). Zugriff wie die
 * KI-Dienste-Seite (viewAny auf die Verbindung, module.ai-Gating über
 * die admin.ai.*-Routen).
 */
class AiUsageReportController extends Controller {
    use WritesReportCsv;

    private const MONTHS = 12;

    public function index(Request $request, AiBudgetService $budget): View|Response {
        Gate::authorize('viewAny', AiProviderConnection::class);

        /** @var Organization $organization */
        $organization = app('currentOrganization');

        // Lückenlose Monatsachse (aktueller Monat zuerst) — auch ohne Verbrauch.
        $months = collect(range(0, self::MONTHS - 1))
            ->map(fn (int $i): string => Carbon::now()->subMonthsNoOverflow($i)->format('Y-m'));

        $periods = AiUsagePeriod::query()
            ->whereIn('period', $months->all())
            ->get()
            ->groupBy('period');

        $families = AiFamily::cases();
        $rows = $months->map(function (string $period) use ($periods, $families): array {
            $byFamily = [];
            foreach ($families as $family) {
                $byFamily[$family->value] = (int) ($periods->get($period)?->firstWhere('family', $family)->used_units ?? 0);
            }

            return ['period' => $period, 'families' => $byFamily];
        });

        // Budgetauslastung des laufenden Monats je Familie.
        $currentBudget = [];
        foreach ($families as $family) {
            $limit = $budget->limitFor($organization, $family);
            $used = $budget->usedThisPeriod($organization, $family);
            $currentBudget[$family->value] = [
                'limit' => $limit,
                'used' => $used,
                'percent' => ($limit !== null && $limit > 0) ? min(100.0, round($used / $limit * 100, 1)) : null,
            ];
        }

        // Vorschlags-Funnel je Capability (Entscheidungsstände + Quellen).
        $funnel = AiTextSuggestion::query()
            ->selectRaw('capability, status, COUNT(*) as cnt, SUM(CASE WHEN from_cache THEN 1 ELSE 0 END) as cached, SUM(CASE WHEN fallback_used THEN 1 ELSE 0 END) as fallbacks')
            ->groupBy('capability', 'status')
            ->get()
            ->groupBy('capability')
            ->map(function ($group): array {
                $byStatus = [];
                $cached = 0;
                $fallbacks = 0;
                $total = 0;
                foreach ($group as $row) {
                    $cnt = (int) $row->getAttribute('cnt');
                    $byStatus[(string) $row->getAttribute('status')] = $cnt;
                    $total += $cnt;
                    $cached += (int) $row->getAttribute('cached');
                    $fallbacks += (int) $row->getAttribute('fallbacks');
                }
                $adopted = ($byStatus[AiTextSuggestion::STATUS_ACCEPTED] ?? 0)
                    + ($byStatus[AiTextSuggestion::STATUS_EDITED] ?? 0);
                $decided = $adopted + ($byStatus[AiTextSuggestion::STATUS_REJECTED] ?? 0);

                return [
                    'total' => $total,
                    'byStatus' => $byStatus,
                    'adoptionPercent' => $decided > 0 ? round($adopted / $decided * 100, 1) : null,
                    'cached' => $cached,
                    'fallbacks' => $fallbacks,
                ];
            });

        if ($request->query('export') === 'csv') {
            $csv = [array_merge([__('Monat')], array_map(
                static fn (AiFamily $f): string => $f->label(),
                $families,
            ))];
            foreach ($rows as $row) {
                $csv[] = array_merge([$row['period']], array_values($row['families']));
            }

            return $this->csvWithMetadata($csv, 'ki-verbrauch.csv', 'ai-usage', [], $request);
        }

        return view('admin.ai.usage-report', [
            'months' => $rows,
            'families' => $families,
            'currentBudget' => $currentBudget,
            'funnel' => $funnel,
        ]);
    }
}
