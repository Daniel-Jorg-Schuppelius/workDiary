<?php
/*
 * Created on   : Sun Sep 27 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ManagementInsightsController.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Http\Controllers\Reporting;

use App\Enums\User\Permission;
use App\Http\Controllers\Concerns\{ResolvesCurrentOrganization, ResolvesGlobalDateRange};
use App\Http\Controllers\Controller;
use App\Http\Controllers\Reporting\Concerns\{RendersReportPdf, WritesReportCsv};
use App\Models\Platform\User;
use App\Services\Reporting\Contracts\TrainingNeedSource;
use App\Services\Reporting\Dto\EarlyWarning;
use App\Services\Reporting\{EarlyWarningService, ManagementOverviewService, ManagementReportPdf};
use Illuminate\Http\Request;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;

/**
 * Leitungssicht (MVP-926, Feature 184): Kennzahlen aller Bereiche im
 * Kopfzeitraum, wiederkehrende Probleme und Schulungsbedarf; als PDF auch per
 * geplantem Versand (`reports:management-digest`).
 */
class ManagementInsightsController extends Controller {
    use RendersReportPdf;
    use ResolvesCurrentOrganization;
    use ResolvesGlobalDateRange;
    use WritesReportCsv;

    public function index(EarlyWarningService $warnings, TrainingNeedSource $training, ManagementOverviewService $overview): View {
        $user = $this->viewer();
        $organization = $this->currentOrganization();
        $range = $this->globalDateRange();

        return view('reports.management-insights', [
            'kpis' => $overview->build($user, $organization, $range['from'], $range['to']),
            'rangeLabel' => $range['label'],
            'groups' => collect($warnings->collect($organization))->groupBy(fn (EarlyWarning $w): string => $w->kind)->sortKeys(),
            'trainingNeeds' => $training->trainingNeeds($organization),
        ]);
    }

    public function pdf(Request $request, ManagementReportPdf $report): SymfonyResponse {
        $user = $this->viewer();
        $range = $this->globalDateRange();

        return $this->pdfDownload(
            ManagementReportPdf::VIEW,
            $report->data($user, $this->currentOrganization(), $range['from'], $range['to']),
            sprintf('leitungsbericht_%s_%s.pdf', $range['from']->toDateString(), $range['to']->toDateString()),
            request: $request,
            reportCode: 'management',
            filters: ['from' => $range['from']->toDateString(), 'to' => $range['to']->toDateString()],
        );
    }

    private function viewer(): User {
        $user = $this->authUser();
        abort_unless($user->isAdmin() || $user->can(Permission::ReportView->value), 403);

        return $user;
    }
}
