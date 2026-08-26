<?php
/*
 * Created on   : Sat Jul 18 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : InvoiceApiController.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\InvoiceResource;
use App\Models\{Customer, ExternalReference, Invoice};
use App\Services\Invoicing\InvoicePdfRenderer;
use App\Support\Sqid;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\{Gate, Route};
use OpenApi\Attributes as OA;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;

/**
 * REST-API Rechnungen (Feature 008 MVP „Kernobjekte"; Vollaudit 2026-07, M3):
 * read-first — Zugriff über die InvoicePolicy (Abrechnungsrollen); Erzeugung/
 * Statuswechsel bleiben bewusst der Web-Oberfläche und der Faktura-Pipeline
 * vorbehalten (GoBD). MVP-718: Sqids, InvoiceResource, Kunden-/Typ-Filter und
 * PDF-Download (`/invoices/{invoice}/pdf`, Plan-Gating `module.vertrieb`).
 */
class InvoiceApiController extends Controller {
    #[OA\Get(
        path: '/invoices',
        summary: 'Rechnungen auflisten',
        tags: ['Invoices'],
        security: [['bearerAuth' => ['invoices:read']]],
        parameters: [
            new OA\Parameter(name: 'from', in: 'query', required: false, description: 'issued_on ≥', schema: new OA\Schema(type: 'string', format: 'date')),
            new OA\Parameter(name: 'to', in: 'query', required: false, description: 'issued_on ≤', schema: new OA\Schema(type: 'string', format: 'date')),
            new OA\Parameter(name: 'status', in: 'query', required: false, schema: new OA\Schema(type: 'string', enum: ['draft', 'issued', 'partially_paid', 'paid', 'cancelled'])),
            new OA\Parameter(name: 'type', in: 'query', required: false, schema: new OA\Schema(type: 'string')),
            new OA\Parameter(name: 'customer', in: 'query', required: false, description: 'Kunden-Sqid', schema: new OA\Schema(type: 'string', example: 'Pq9zR1')),
            new OA\Parameter(name: 'per_page', in: 'query', required: false, schema: new OA\Schema(type: 'integer', default: 25, maximum: 100)),
        ],
        responses: [
            new OA\Response(response: 200, description: 'OK'),
            new OA\Response(response: 401, description: 'Unauthenticated'),
            new OA\Response(response: 403, description: 'Forbidden'),
            new OA\Response(response: 423, description: 'Modul nicht lizenziert'),
        ],
    )]
    public function index(Request $request): AnonymousResourceCollection {
        Gate::authorize('viewAny', Invoice::class);

        $query = Invoice::query()
            ->when($request->filled('from'), fn($q) => $q->whereDate('issued_on', '>=', (string) $request->query('from')))
            ->when($request->filled('to'), fn($q) => $q->whereDate('issued_on', '<=', (string) $request->query('to')))
            ->when(in_array((string) $request->query('status', ''), Invoice::STATUSES, true), fn($q) => $q->where('status', (string) $request->query('status')))
            ->when($request->filled('type'), fn($q) => $q->where('type', (string) $request->query('type')))
            ->when($request->filled('customer'), fn($q) => $q->where('customer_id', Sqid::decodeOrAbort(Customer::class, (string) $request->query('customer'))))
            ->with('customer:id,name')
            ->orderByDesc('issued_on')
            ->orderByDesc('id');

        return InvoiceResource::collection($query->paginate(ArticleApiController::perPage($request)));
    }

    #[OA\Get(
        path: '/invoices/{invoice}',
        summary: 'Rechnung anzeigen',
        tags: ['Invoices'],
        security: [['bearerAuth' => ['invoices:read']]],
        parameters: [new OA\Parameter(name: 'invoice', in: 'path', required: true, description: 'Sqid', schema: new OA\Schema(type: 'string', example: 'k7Qx2Ab'))],
        responses: [
            new OA\Response(response: 200, description: 'OK'),
            new OA\Response(response: 401, description: 'Unauthenticated'),
            new OA\Response(response: 403, description: 'Forbidden'),
            new OA\Response(response: 404, description: 'Not Found'),
        ],
    )]
    public function show(Invoice $invoice): InvoiceResource {
        Gate::authorize('view', $invoice);

        return new InvoiceResource($invoice->load('customer:id,name'));
    }

    #[OA\Get(
        path: '/invoices/{invoice}/pdf',
        summary: 'Rechnungs-PDF herunterladen',
        description: 'Liefert das PDF (application/pdf). Führt ein Plugin die Rechnung (z. B. Lexoffice), antwortet der Endpunkt mit 303 auf die Plugin-Route.',
        tags: ['Invoices'],
        security: [['bearerAuth' => ['invoices:read']]],
        parameters: [new OA\Parameter(name: 'invoice', in: 'path', required: true, description: 'Sqid', schema: new OA\Schema(type: 'string', example: 'k7Qx2Ab'))],
        responses: [
            new OA\Response(response: 200, description: 'PDF', content: new OA\MediaType(mediaType: 'application/pdf')),
            new OA\Response(response: 303, description: 'Plugin liefert das offizielle PDF'),
            new OA\Response(response: 401, description: 'Unauthenticated'),
            new OA\Response(response: 403, description: 'Forbidden'),
            new OA\Response(response: 404, description: 'Not Found'),
        ],
    )]
    public function pdf(Invoice $invoice, InvoicePdfRenderer $renderer): SymfonyResponse {
        Gate::authorize('view', $invoice);
        $invoice->load(['items', 'customer', 'project']);

        // Gleiche Plugin-Naht wie InvoiceController::pdf: führt ein Plugin die
        // Rechnung, liegt das offizielle PDF dort.
        $hooked = ExternalReference::query()
            ->where('external_type', 'invoice')
            ->forReferenceable($invoice)
            ->first();
        if ($hooked !== null) {
            $pluginRoute = 'invoices.' . $hooked->plugin_id . '.pdf';
            if (Route::has($pluginRoute)) {
                return redirect()->route($pluginRoute, $invoice, 303);
            }
        }

        return response($renderer->output($invoice), 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="rechnung-' . $invoice->number . '.pdf"',
        ]);
    }
}
