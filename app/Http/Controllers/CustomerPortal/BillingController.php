<?php
/*
 * Created on   : Thu Jul 23 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : BillingController.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Http\Controllers\CustomerPortal;

use App\Http\Controllers\Controller;
use App\Models\Billing\{CustomerBillingAgreement, CustomerBillingStatement};
use App\Models\User;
use App\Services\Billing\CustomerAccountStatementService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\{Auth, URL};
use Illuminate\View\View;

/**
 * Portal-Abrechnung (Feature 098): read-only Monatsübersicht des Kundenkontos
 * (Anwesenheiten + Abrechnungsblock Gesamt/Abgerechnet/Vormonat/Offen) für
 * Konto-Modus-Kunden. monthData() ist die EINZIGE Datenquelle für Seite und
 * PDF (kein internal_rate, keine fremden Kunden); das PDF läuft wie die
 * Fallakte über einen signierten 24-h-Link ohne Portal-Session.
 */
class BillingController extends Controller {
    public function __construct(private readonly CustomerAccountStatementService $statements) {}

    public function index(): View {
        $agreement = $this->agreementOrAbort();
        $this->statements->recalculateOpen($agreement);

        $statements = $agreement->statements()
            ->orderByDesc('year')->orderByDesc('month')
            ->limit(24)
            ->get();

        return view('customer.billing.index', [
            'agreement' => $agreement,
            'statements' => $statements,
        ]);
    }

    public function show(int $year, int $month): View {
        abort_unless($year >= 2000 && $year <= 2100 && $month >= 1 && $month <= 12, 404);
        $agreement = $this->agreementOrAbort();

        $data = $this->statements->monthData($agreement, $year, $month);
        /** @var CustomerBillingStatement $statement */
        $statement = $data['statement'];

        return view('customer.billing.show', $data + [
            'agreement' => $agreement,
            'pdfUrl' => URL::temporarySignedRoute('customer.billing.pdf', now()->addHours(24), [
                'statement' => $statement->getRouteKey(),
            ]),
        ]);
    }

    /**
     * Anwesenheitsnachweis-PDF über signierten Link (Muster customer.diary.pdf):
     * ohne Portal-Session teilbar, Schutz ausschließlich über die Signatur.
     */
    public function pdf(Request $request, CustomerBillingStatement $statement): \Symfony\Component\HttpFoundation\Response {
        abort_unless($request->hasValidSignature(), 403);

        $agreement = $statement->agreement()->firstOrFail();
        $data = $this->statements->monthData($agreement, $statement->year, $statement->month);

        $bytes = app(\App\Services\DocumentDesign\DocumentDesignRenderer::class)->renderPdf(
            \App\Enums\DocumentDesign\RenderDocumentKind::Report,
            'customer.billing.pdf',
            $data + ['agreement' => $agreement],
            $agreement->organization_id,
        );

        return response($bytes, 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="anwesenheitsnachweis-' . $statement->year . '-' . str_pad((string) $statement->month, 2, '0', STR_PAD_LEFT) . '.pdf"',
        ]);
    }

    private function agreementOrAbort(): CustomerBillingAgreement {
        /** @var User $user */
        $user = Auth::guard('customer')->user();

        $agreement = CustomerBillingAgreement::query()
            ->where('customer_id', (int) $user->customer_id)
            ->where('active', true)
            ->first();

        abort_unless($agreement !== null && $agreement->keepsLedger(), 404);

        return $agreement;
    }
}
