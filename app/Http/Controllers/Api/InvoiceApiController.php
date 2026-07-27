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
use App\Models\Invoice;
use Illuminate\Http\{JsonResponse, Request};
use Illuminate\Support\Facades\Gate;
use OpenApi\Attributes as OA;

/**
 * REST-API Rechnungen (Feature 008 MVP „Kernobjekte"; Vollaudit 2026-07, M3):
 * read-first — Zugriff über die InvoicePolicy (Abrechnungsrollen); Erzeugung/
 * Statuswechsel bleiben bewusst der Web-Oberfläche und der Faktura-Pipeline
 * vorbehalten (GoBD).
 */
class InvoiceApiController extends Controller {
    #[OA\Get(
        path: '/invoices',
        summary: 'Rechnungen auflisten',
        tags: ['Invoices'],
        security: [['bearerAuth' => []]],
        parameters: [
            new OA\Parameter(name: 'from', in: 'query', required: false, schema: new OA\Schema(type: 'string', format: 'date')),
            new OA\Parameter(name: 'to', in: 'query', required: false, schema: new OA\Schema(type: 'string', format: 'date')),
            new OA\Parameter(name: 'status', in: 'query', required: false, schema: new OA\Schema(type: 'string')),
            new OA\Parameter(name: 'per_page', in: 'query', required: false, schema: new OA\Schema(type: 'integer', default: 25)),
        ],
        responses: [new OA\Response(response: 200, description: 'OK')],
    )]
    public function index(Request $request): JsonResponse {
        Gate::authorize('viewAny', Invoice::class);

        $query = Invoice::query()
            ->when($request->filled('from'), fn($q) => $q->whereDate('issued_on', '>=', (string) $request->query('from')))
            ->when($request->filled('to'), fn($q) => $q->whereDate('issued_on', '<=', (string) $request->query('to')))
            ->when($request->filled('status'), fn($q) => $q->where('status', (string) $request->query('status')))
            ->with('customer:id,name')
            ->orderByDesc('issued_on')
            ->orderByDesc('id');

        $page = $query->paginate(min(100, (int) $request->input('per_page', 25)));

        return response()->json([
            'data' => collect($page->items())->map(fn(Invoice $invoice): array => $this->serialize($invoice))->all(),
            'meta' => ['current_page' => $page->currentPage(), 'last_page' => $page->lastPage(), 'total' => $page->total()],
        ]);
    }

    #[OA\Get(
        path: '/invoices/{invoice}',
        summary: 'Rechnung anzeigen',
        tags: ['Invoices'],
        security: [['bearerAuth' => []]],
        parameters: [new OA\Parameter(name: 'invoice', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))],
        responses: [new OA\Response(response: 200, description: 'OK'), new OA\Response(response: 404, description: 'Not Found')],
    )]
    public function show(Invoice $invoice): JsonResponse {
        Gate::authorize('view', $invoice);

        return response()->json(['data' => $this->serialize($invoice->load('customer:id,name'))]);
    }

    /** @return array<string, mixed> */
    private function serialize(Invoice $invoice): array {
        return [
            'id' => $invoice->id,
            'number' => $invoice->number,
            'status' => $invoice->status,
            'type' => $invoice->type,
            'customer' => ['id' => $invoice->customer_id, 'name' => $invoice->customer->name ?? null],
            'issued_on' => $invoice->issued_on?->toDateString(),
            'due_on' => $invoice->due_on?->toDateString(),
            'paid_on' => $invoice->paid_on?->toDateString(),
            'subtotal' => $invoice->subtotal?->getAmount(),
            'tax_amount' => $invoice->tax_amount?->getAmount(),
            'total' => $invoice->total?->getAmount(),
        ];
    }
}
