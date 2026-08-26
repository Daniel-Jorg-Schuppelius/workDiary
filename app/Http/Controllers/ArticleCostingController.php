<?php
/*
 * Created on   : Tue Aug 25 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ArticleCostingController.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\ResolvesGlobalDateRange;
use App\Models\{Article, ManufacturingOrder};
use App\Services\Manufacturing\ManufacturingCostingService;
use App\Support\CsvExport;
use Carbon\CarbonImmutable;
use CommonToolkit\Helper\Data\NumberHelper;
use Illuminate\Http\{Request, Response};
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

/**
 * Reiter „Nachkalkulation" der Artikel-Detailseite (Feature 047, MVP-715 —
 * Vollscan G14): Plan/Ist-Material, Plan/Ist-Zeit und Stückkosten über die im
 * Zeitraum abgeschlossenen Fertigungsaufträge des Artikels, je Auftrag plus
 * Summenzeile, als Seite oder CSV. Recht: Artikel sehen UND Fertigungsaufträge
 * sehen (Kosten sind Fertigungsdaten, nicht Stammdaten).
 */
class ArticleCostingController extends Controller {
    use ResolvesGlobalDateRange;

    public function __construct(private readonly ManufacturingCostingService $costing) {}

    public function index(Request $request, Article $article): View|Response {
        Gate::authorize('view', $article);
        Gate::authorize('viewAny', ManufacturingOrder::class);

        // Default: die letzten zwölf Monate — Nachkalkulation ist rückblickend.
        [$from, $to] = $this->resolveRangeWithDefault($request, static fn(): array => [
            CarbonImmutable::today()->subYear()->addDay()->startOfDay(),
            CarbonImmutable::today()->endOfDay(),
        ]);

        $result = $this->costing->costingForArticle((int) $article->id, $from, $to);

        if ($request->query('export') === 'csv') {
            return $this->csv($article, $result, $from, $to);
        }

        return view('articles.costing', [
            'article' => $article,
            'result' => $result,
            'from' => $from,
            'to' => $to,
        ]);
    }

    /**
     * @param  array{orders: list<array{order_id: int, number: string, completed_at: ?CarbonImmutable, planned_material: numeric-string, actual_material: numeric-string, labor: numeric-string, total: numeric-string, planned_minutes: int, actual_minutes: int, good: numeric-string, scrap: numeric-string, unit_cost: numeric-string, deviation_abs: numeric-string, deviation_pct: ?numeric-string}>, planned_material: numeric-string, actual_material: numeric-string, labor: numeric-string, total: numeric-string, planned_minutes: int, actual_minutes: int, good: numeric-string, scrap: numeric-string, unit_cost_avg: numeric-string, deviation_abs: numeric-string, deviation_pct: ?numeric-string}  $result
     */
    private function csv(Article $article, array $result, CarbonImmutable $from, CarbonImmutable $to): Response {
        $rows = [];
        foreach ($result['orders'] as $order) {
            $rows[] = [
                $order['number'],
                $order['completed_at']?->toDateString() ?? '',
                NumberHelper::toUSFormat((float) $order['planned_material'], 2),
                NumberHelper::toUSFormat((float) $order['actual_material'], 2),
                NumberHelper::toUSFormat((float) $order['labor'], 2),
                NumberHelper::toUSFormat((float) $order['total'], 2),
                $order['planned_minutes'],
                $order['actual_minutes'],
                NumberHelper::toUSFormat((float) $order['good'], 2),
                NumberHelper::toUSFormat((float) $order['scrap'], 2),
                NumberHelper::toUSFormat((float) $order['unit_cost'], 4),
                NumberHelper::toUSFormat((float) $order['deviation_abs'], 2),
                $order['deviation_pct'] ?? '',
            ];
        }
        $rows[] = [
            __('article.costing.sum'),
            '',
            NumberHelper::toUSFormat((float) $result['planned_material'], 2),
            NumberHelper::toUSFormat((float) $result['actual_material'], 2),
            NumberHelper::toUSFormat((float) $result['labor'], 2),
            NumberHelper::toUSFormat((float) $result['total'], 2),
            $result['planned_minutes'],
            $result['actual_minutes'],
            NumberHelper::toUSFormat((float) $result['good'], 2),
            NumberHelper::toUSFormat((float) $result['scrap'], 2),
            NumberHelper::toUSFormat((float) $result['unit_cost_avg'], 4),
            NumberHelper::toUSFormat((float) $result['deviation_abs'], 2),
            $result['deviation_pct'] ?? '',
        ];

        $header = [
            'Auftrag', 'Abgeschlossen', 'PlanMaterialEUR', 'IstMaterialEUR', 'LohnEUR', 'GesamtEUR',
            'PlanMinuten', 'IstMinuten', 'Gutmenge', 'Ausschuss', 'StueckkostenEUR', 'AbweichungEUR', 'AbweichungProzent',
        ];
        $filename = sprintf('nachkalkulation_%s_%s_%s.csv', $article->number ?? $article->id, $from->toDateString(), $to->toDateString());

        return response(CsvExport::toString($header, $rows), 200, [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="' . $filename . '"',
        ]);
    }
}
