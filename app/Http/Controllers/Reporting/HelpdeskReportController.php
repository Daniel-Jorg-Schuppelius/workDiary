<?php
/*
 * Created on   : Wed Jul 08 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : HelpdeskReportController.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Http\Controllers\Reporting;

use App\Enums\User\Permission;
use App\Http\Controllers\Controller;
use App\Services\ServiceTicket\HelpdeskMetricsService;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

/**
 * Helpdesk-/Service-Desk-Bericht (Feature 065, MVP-159): Volumen,
 * Reaktions-/Lösungszeiten, SLA-Erfüllung, Wartezeiten, Change-Quoten,
 * Problem-Bestand, Katalog-Nachfrage — Rendering über die x-charts.*-
 * Komponenten aus Feature 064. Keine Agenten-Ranglisten (Vorgabe).
 */
class HelpdeskReportController extends Controller {
    public function index(Request $request, HelpdeskMetricsService $metrics): View {
        Gate::authorize(Permission::SlaViewAny->value);

        $from = $request->query('from') !== null ? Carbon::parse((string) $request->query('from')) : now()->subWeeks(8)->startOfWeek();
        $to = $request->query('to') !== null ? Carbon::parse((string) $request->query('to'))->endOfDay() : now();

        return view('helpdesk.reports.index', [
            'from' => $from,
            'to' => $to,
            'metricVersion' => HelpdeskMetricsService::METRIC_VERSION,
            'volume' => $metrics->volumeByQueue($from, $to),
            'times' => $metrics->responseTimes($from, $to),
            'compliance' => $metrics->slaCompliance($from, $to),
            'waiting' => $metrics->waitingByReason($from, $to),
            'changeOutcomes' => $metrics->changeOutcomes($from, $to),
            'problemBacklog' => $metrics->problemBacklog(),
            'catalogDemand' => $metrics->catalogDemand($from, $to),
        ]);
    }
}
