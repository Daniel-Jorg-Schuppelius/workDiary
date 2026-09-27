<?php
/*
 * Created on   : Sun Sep 27 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : CrisisBcmReportController.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Http\Controllers\Crisis;

use App\Enums\DocumentDesign\RenderDocumentKind;
use App\Http\Controllers\Concerns\ResolvesCurrentOrganization;
use App\Http\Controllers\Controller;
use App\Models\Crisis\CrisisCase;
use App\Services\Crisis\CrisisBcmReportBuilder;
use App\Services\DocumentDesign\DocumentDesignRenderer;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

/** BCM-Auswertung nach ISO 22301 (MVP-944), als Seite und PDF. */
class CrisisBcmReportController extends Controller {
    use ResolvesCurrentOrganization;

    public function index(CrisisBcmReportBuilder $builder): View {
        Gate::authorize('viewAny', CrisisCase::class);

        return view('crisis.bcm-report', ['report' => $builder->build()]);
    }

    public function pdf(CrisisBcmReportBuilder $builder, DocumentDesignRenderer $renderer): Response {
        Gate::authorize('viewAny', CrisisCase::class);
        $organization = $this->currentOrganization();
        $pdf = $renderer->renderPdf(RenderDocumentKind::Report, 'pdf.crisis-bcm-report', ['report' => $builder->build(), 'organization' => $organization], $organization);

        return response($pdf, 200, ['Content-Type' => 'application/pdf', 'Content-Disposition' => 'attachment; filename="BCM-Bericht_' . now()->format('Y-m-d') . '.pdf"']);
    }
}
