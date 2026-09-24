<?php
/*
 * Created on   : Wed Jul 08 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : AgileManagementReportController.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Http\Controllers\Reporting;

use App\Enums\User\Permission;
use App\Http\Controllers\Controller;
use App\Models\Agile\{AgileBoard, AgileSprint, AgileWorkItem};
use App\Services\Agile\AgileMetricsService;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

/**
 * Management-Übersicht (Feature 064, P10/MVP-148): org-weite Sicht über
 * alle Boards — NUR Projekte, die der Betrachter sehen darf (Policy je
 * Projekt, kein Admin-Bypass). Je Board: aktiver Sprint, Velocity-Median,
 * ungeplante Arbeit, Blockierungen, empirische Prognose (Monte-Carlo,
 * unter 4 vergleichbaren Wochen bewusst ohne Ergebnis).
 */
class AgileManagementReportController extends Controller {
    public function __construct(private readonly AgileMetricsService $metrics) {}

    public function index(): View {
        Gate::authorize(Permission::AgileReportView->value);

        $rows = AgileBoard::query()
            ->with('project')
            ->orderBy('name')
            ->get()
            ->filter(fn(AgileBoard $board): bool => $board->project !== null && Gate::allows('view', $board->project))
            ->map(function (AgileBoard $board): array {
                $velocity = $this->metrics->velocity($board);
                $forecast = $this->metrics->forecast($board);

                return [
                    'board' => $board,
                    'project' => $board->project,
                    'active_sprint' => AgileSprint::query()
                        ->where('board_id', $board->id)
                        ->where('status', AgileSprint::STATUS_ACTIVE)
                        ->first(),
                    'velocity_median' => $velocity->data['median'],
                    'scope_added' => array_sum(array_column($velocity->data['sprints'], 'scope_added')),
                    'blocked_count' => AgileWorkItem::query()
                        ->where('board_id', $board->id)
                        ->whereNotNull('blocked_at')
                        ->count(),
                    'forecast' => $forecast->data,
                ];
            })
            ->values();

        return view('agile.reports.overview', ['rows' => $rows]);
    }
}
