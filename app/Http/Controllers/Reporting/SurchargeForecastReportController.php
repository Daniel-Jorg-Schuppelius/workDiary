<?php
/*
 * Created on   : Thu Aug 13 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : SurchargeForecastReportController.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Http\Controllers\Reporting;

use App\Enums\User\Permission;
use App\Http\Controllers\Controller;
use App\Http\Controllers\Reporting\Concerns\WritesReportCsv;
use App\Models\User;
use App\Services\Surcharge\SurchargeForecastService;
use App\Support\Sqid;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;

/**
 * Zuschlags-Prognose (Feature 103, MVP-533): voraussichtliche
 * Zuschlagsminuten je Monat und Lohnart auf Basis GEPLANTER Dienste —
 * reine Vorschau ohne Persistenz (die Ist-Bewertung bleibt bei der
 * TimeRuleEngine im Zeitexport).
 */
class SurchargeForecastReportController extends Controller {
    use WritesReportCsv;

    public function index(Request $request, SurchargeForecastService $service): View|SymfonyResponse {
        Gate::authorize(Permission::ReportView->value);

        /** @var User $viewer */
        $viewer = $request->user();
        $months = (int) $request->query('months', 3);
        $months = max(1, min(12, $months));
        $userId = Sqid::decodeOrNumeric(User::class, (string) $request->query('user'));

        $forecast = $service->forecast((int) $viewer->organization_id, CarbonImmutable::now(), $months, $userId);
        $filters = ['months' => $months, 'user' => $userId];

        if (in_array($request->query('export'), ['csv', 'xlsx'], true)) {
            return $this->exportCsv($forecast, $filters, $request);
        }

        return view('reports.surcharge-forecast', [
            'forecast' => $forecast,
            'months' => $months,
            'userId' => $userId,
            'userOptions' => User::query()
                ->where('organization_id', $viewer->organization_id)
                ->whereNull('deactivated_at')
                ->orderBy('name')
                ->get(['id', 'name'])
                ->map(fn (User $u): array => ['sqid' => Sqid::encode(User::class, (int) $u->id), 'id' => (int) $u->id, 'name' => (string) $u->name])
                ->values()
                ->all(),
        ]);
    }

    /**
     * @param  array{months: list<string>, rows: list<array{wage_type_code: string, label: string, minutes: array<string, int>, total: int}>, totals: array<string, int>}  $forecast
     * @param  array<string, mixed>  $filters
     */
    private function exportCsv(array $forecast, array $filters, Request $request): SymfonyResponse {
        $rows = [array_merge(
            [(string) __('reporting.surcharge_forecast.col_wage_type'), (string) __('reporting.surcharge_forecast.col_label')],
            $forecast['months'],
            [(string) __('reporting.surcharge_forecast.col_total')],
        )];
        foreach ($forecast['rows'] as $row) {
            $rows[] = array_merge(
                [$row['wage_type_code'], $row['label']],
                array_map(static fn (string $m): int => $row['minutes'][$m] ?? 0, $forecast['months']),
                [$row['total']],
            );
        }

        return $this->csvWithMetadata($rows, 'zuschlags-prognose.csv', 'surcharge-forecast', $filters, $request);
    }
}
