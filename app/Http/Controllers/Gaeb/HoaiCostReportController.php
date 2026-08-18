<?php
/*
 * Created on   : Tue Aug 18 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : HoaiCostReportController.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Http\Controllers\Gaeb;

use App\Enums\DocumentDesign\RenderDocumentKind;
use App\Enums\User\Permission as P;
use App\Http\Controllers\Concerns\ResolvesCurrentOrganization;
use App\Http\Controllers\Controller;
use App\Models\Project;
use App\Services\DocumentDesign\DocumentDesignRenderer;
use App\Services\Gaeb\HoaiCostReportService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\{Request, Response};
use Illuminate\Support\Facades\Gate;

/**
 * Kostenermittlung nach den vier HOAI-Stufen (Feature 109, MVP-644).
 *
 * Der Bericht stellt Schätzung, Berechnung, Anschlag und Feststellung
 * **nebeneinander** — ihr Vergleich ist die Kostenkontrolle, nicht die
 * einzelne Zahl.
 */
class HoaiCostReportController extends Controller {
    use ResolvesCurrentOrganization;

    public function __construct(private readonly HoaiCostReportService $report) {}

    public function show(Request $request, Project $project): View|Response {
        Gate::authorize(P::ProjectViewAny->value);
        abort_unless($project->organization_id === $this->currentOrganization()->id, 404);

        $data = $this->report->forProject($project);

        if ($request->query('export') === 'pdf') {
            // View → Design → PDF über den zentralen Renderer; kein dompdf an
            // der Aufrufstelle.
            $bytes = app(DocumentDesignRenderer::class)->renderPdf(
                RenderDocumentKind::Report,
                'pdf.hoai-cost-report',
                ['project' => $project, 'report' => $data],
                (int) $project->organization_id,
            );

            return response($bytes, 200, [
                'Content-Type' => 'application/pdf',
                'Content-Disposition' => 'attachment; filename="Kostenermittlung-' . $project->sqid . '.pdf"',
            ]);
        }

        return view('gaeb.hoai-report', [
            'project' => $project,
            'report' => $data,
        ]);
    }
}
