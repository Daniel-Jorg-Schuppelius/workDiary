<?php
/*
 * Created on   : Fri Jun 19 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : PurchaseOrderController.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Http\Controllers;

use App\Enums\Procurement\PurchaseOrderStatus;
use App\Enums\User\Permission as P;
use App\Http\Controllers\Concerns\{ResolvesCurrentOrganization, ResolvesGlobalDateRange};
use App\Http\Requests\SavePurchaseOrderRequest;
use App\Models\{Article, PurchaseOrder, PurchaseOrderAdvice, PurchaseOrderLine, Supplier, Warehouse};
use App\Services\Procurement\{AdviceService, DespatchAdviceImportService, GoodsReceiptService, ProcurementSuggestionService, PurchaseOrderExportService, PurchaseOrderPdfRenderer, PurchaseOrderService, UglInvoiceReconciler};
use App\Services\SqidEncoder;
use ERechnungToolkit\Parsers\UglInvoiceParser;
use Illuminate\Http\{RedirectResponse, Request};
use Illuminate\Support\Facades\{Auth, Gate};
use Illuminate\View\View;
use RuntimeException;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;

/**
 * Beschaffungs-UI (Feature 048, E4): Bestellungen anlegen, Zeilen pflegen,
 * bestellen, Wareneingang gegen die Bestellzeile buchen sowie automatische
 * Bestellvorschläge. Modul-Gating über `purchase-orders.*` → module.lager.
 */
class PurchaseOrderController extends Controller {
    use ResolvesCurrentOrganization;
    use ResolvesGlobalDateRange;

    public function __construct(
        private readonly PurchaseOrderService $orders,
        private readonly GoodsReceiptService $receipts,
        private readonly ProcurementSuggestionService $suggestions,
    ) {}

    public function index(Request $request): View {
        $this->canView();
        $status = $request->string('status')->toString() ?: 'all';

        $range = $this->globalDateRange();
        $orders = PurchaseOrder::query()
            ->with('supplier')
            ->when($status !== 'all', fn ($q) => $q->where('status', $status))
            ->whereBetween('created_at', [$range['from']->startOfDay(), $range['to']->endOfDay()])
            ->latest()
            ->paginate(25)
            ->withQueryString();

        return view('purchase-orders.index', [
            'orders' => $orders,
            'status' => $status,
            'statuses' => PurchaseOrderStatus::cases(),
        ]);
    }

    /** Erwartete Wareneingänge: offene Bestellzeilen bestellter Aufträge (Feature 048, E4). */
    public function incoming(): View {
        $this->canView();

        $range = $this->globalDateRange();
        $lines = PurchaseOrderLine::query()
            ->whereColumn('received_qty', '<', 'ordered_qty')
            ->whereHas('purchaseOrder', fn ($q) => $q
                ->whereIn('status', [
                    PurchaseOrderStatus::Ordered->value, PurchaseOrderStatus::PartiallyReceived->value,
                ])
                // Erwartete Wareneingänge nach Liefertermin im Header-Zeitraum; ohne Termin bleiben sie sichtbar.
                ->where(fn ($w) => $w
                    ->whereBetween('expected_at', [$range['from']->startOfDay(), $range['to']->endOfDay()])
                    ->orWhereNull('expected_at')))
            ->with(['purchaseOrder.supplier', 'article'])
            ->get()
            ->sortBy(function (PurchaseOrderLine $line): int {
                $expected = $line->purchaseOrder?->expected_at;

                return $expected instanceof \Illuminate\Support\Carbon ? (int) $expected->timestamp : PHP_INT_MAX;
            })
            ->values();

        return view('purchase-orders.incoming', ['lines' => $lines]);
    }

    public function create(): View {
        $this->canManage();

        return view('purchase-orders._form_dialog', [
            'isDialog' => true,
            'suppliers' => Supplier::query()->orderBy('name')->limit(500)->get(),
            'warehouses' => Warehouse::query()->orderBy('name')->get(),
        ]);
    }

    public function store(SavePurchaseOrderRequest $request): RedirectResponse {
        $this->canManage();
        $data = $request->validated();

        $supplier = Supplier::query()->findOrFail((int) $data['supplier']);
        $warehouse = Warehouse::query()->findOrFail((int) $data['warehouse']);

        $order = $this->orders->createDraft($this->currentOrganization(), $supplier, $warehouse, [
            'expected_at' => $data['expected_at'] ?? null,
            'note' => $data['note'] ?? null,
            'created_by' => Auth::id(),
        ]);

        return redirect()->route('purchase-orders.show', $order)->with('success', __('procurement.flash.created'));
    }

    public function show(PurchaseOrder $purchaseOrder): View {
        $this->canView();
        $purchaseOrder->load(['supplier', 'warehouse', 'lines.article', 'advices.lines.line']);

        return view('purchase-orders.show', [
            'order' => $purchaseOrder,
            'articles' => Article::query()->where('purchasable', true)->orderBy('name')->limit(500)->get(),
            'canManage' => Auth::user()?->can(P::InventoryPost->value) ?? false,
            'canImportAdvice' => app(DespatchAdviceImportService::class)->available(),
        ]);
    }

    /**
     * Exportiert die Bestellung als elektronische Bestellung (Feature 048, E4):
     * UBL XBestellung (Peppol BIS Order) bzw. CII Order-X über das
     * php-erechnung-toolkit. Format per `?format=orderx` (Default: xbestellung).
     */
    public function downloadOrder(
        PurchaseOrder $purchaseOrder,
        Request $request,
        PurchaseOrderExportService $export,
    ): SymfonyResponse {
        $this->canView();
        abort_unless($export->available(), 404);

        $format = $request->string('format')->lower()->toString();
        if ($format === 'orderx' || $format === 'order-x') {
            $xml = $export->toOrderX($purchaseOrder);
            $prefix = 'OrderX';
        } elseif ($format === 'opentrans' || $format === 'opentrans-order') {
            $xml = $export->toOpenTrans($purchaseOrder);
            $prefix = 'openTRANS';
        } elseif ($format === 'gaeb' || $format === 'x96') {
            // GAEB-Handelsdatei: eigene Endung, damit die Warenwirtschaft der
            // Gegenseite sie erkennt.
            $result = $export->toGaeb($purchaseOrder);

            return response($result['content'], 200, [
                'Content-Type' => 'application/xml; charset=UTF-8',
                'Content-Disposition' => 'attachment; filename="' . $result['filename'] . '"',
            ]);
        } elseif ($format === 'ugl') {
            // UGL ist ASCII-Festsatz (ISO-8859-1), kein XML.
            $content = $export->toUgl($purchaseOrder);
            $filename = 'UGL_' . preg_replace('/[^A-Za-z0-9._-]/', '_', (string) $purchaseOrder->number) . '.ugl';

            return response($content, 200, [
                'Content-Type' => 'text/plain; charset=ISO-8859-1',
                'Content-Disposition' => 'attachment; filename="' . $filename . '"',
            ]);
        } else {
            $xml = $export->toXBestellung($purchaseOrder);
            $prefix = 'XBestellung';
        }

        $filename = $prefix . '_' . preg_replace('/[^A-Za-z0-9._-]/', '_', (string) $purchaseOrder->number) . '.xml';

        return response($xml, 200, [
            'Content-Type' => 'application/xml; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="' . $filename . '"',
        ]);
    }

    /** Bestellung als menschenlesbares PDF (Feature 048, E4) — für Lieferanten ohne E-Beschaffung. */
    public function downloadPdf(PurchaseOrder $purchaseOrder, PurchaseOrderPdfRenderer $renderer): SymfonyResponse {
        $this->canView();

        return response($renderer->render($purchaseOrder), 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'inline; filename="' . $renderer->filename($purchaseOrder) . '.pdf"',
        ]);
    }

    /** Erfasst ein Lieferavis (ASN) zur Bestellung (Feature 048, E4). */
    public function announceAdvice(Request $request, PurchaseOrder $purchaseOrder): RedirectResponse {
        $this->canManage();
        $data = $request->validate([
            'qty' => ['required', 'array'],
            'qty.*' => ['nullable', 'numeric', 'min:0'],
            'reference' => ['nullable', 'string', 'max:128'],
            'expected_at' => ['nullable', 'date'],
        ]);

        $lines = [];
        foreach ((array) $data['qty'] as $sqid => $qty) {
            if ($qty === null || $qty === '' || (float) $qty <= 0.0) {
                continue;
            }
            $lineId = app(SqidEncoder::class)->decode(PurchaseOrderLine::class, (string) $sqid);
            $line = $lineId !== null
                ? PurchaseOrderLine::query()->where('purchase_order_id', $purchaseOrder->id)->find($lineId)
                : null;
            if ($line instanceof PurchaseOrderLine) {
                $lines[] = ['line' => $line, 'qty' => (string) $qty];
            }
        }

        try {
            app(AdviceService::class)->announce($purchaseOrder, $lines, [
                'reference' => $data['reference'] ?? null,
                'expected_at' => $data['expected_at'] ?? null,
                'created_by' => Auth::id(),
            ]);
        } catch (RuntimeException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', __('procurement.advice.flash.announced'));
    }

    /**
     * Importiert einen elektronischen Lieferschein (UBL Despatch Advice) als
     * Lieferavis zur Bestellung (Feature 048, E4).
     */
    public function importAdvice(
        Request $request,
        PurchaseOrder $purchaseOrder,
        DespatchAdviceImportService $import,
    ): RedirectResponse {
        $this->canManage();
        abort_unless($import->available(), 404);

        $request->validate([
            'advice_xml' => ['required', 'file', 'mimetypes:application/xml,text/xml', 'max:2048'],
        ]);

        $xml = (string) file_get_contents((string) $request->file('advice_xml')?->getRealPath());

        try {
            $import->import($xml, Auth::id(), $purchaseOrder);
        } catch (RuntimeException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', __('procurement.advice.flash.imported'));
    }

    /**
     * Gleicht eine hochgeladene UGL-Rechnung (GC-Gruppe / SHK) gegen die Bestellung
     * ab (Feature 050): Positionen über Lieferanten-SKU, Mengen/Beträge mit Toleranz.
     * Reiner Lesevorgang — keine Buchung, Rechnungshoheit bleibt extern.
     */
    public function reconcileInvoice(
        Request $request,
        PurchaseOrder $purchaseOrder,
        UglInvoiceReconciler $reconciler,
    ): View|RedirectResponse {
        $this->canView();

        $request->validate([
            'invoice_ugl' => ['required', 'file', 'max:2048'],
        ]);

        $content = (string) file_get_contents((string) $request->file('invoice_ugl')?->getRealPath());

        try {
            $invoice = (new UglInvoiceParser)->parse($content);
        } catch (RuntimeException) {
            return back()->with('error', __('procurement.reconcile.error.parse'));
        }

        return view('purchase-orders.invoice-reconciliation', [
            'order' => $purchaseOrder,
            'result' => $reconciler->reconcile($purchaseOrder, $invoice),
        ]);
    }

    /** Bucht den Wareneingang aus einem Lieferavis. */
    public function receiveAdvice(PurchaseOrderAdvice $advice): RedirectResponse {
        $this->canManage();
        try {
            app(AdviceService::class)->receive($advice, Auth::id() !== null ? (int) Auth::id() : null);
        } catch (RuntimeException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', __('procurement.advice.flash.received'));
    }

    public function cancelAdvice(PurchaseOrderAdvice $advice): RedirectResponse {
        $this->canManage();
        app(AdviceService::class)->cancel($advice);

        return back()->with('success', __('procurement.advice.flash.cancelled'));
    }

    public function addLine(Request $request, PurchaseOrder $purchaseOrder): RedirectResponse {
        $this->canManage();
        $data = $request->validate([
            'article' => ['required', 'string'],
            'qty' => ['required', 'numeric', 'gt:0'],
            'unit_price' => ['nullable', 'numeric', 'min:0'],
            'note' => ['nullable', 'string', 'max:500'],
        ]);

        $articleId = app(SqidEncoder::class)->decode(Article::class, (string) $data['article']);
        $article = $articleId !== null ? Article::query()->find($articleId) : null;
        if (! $article instanceof Article) {
            return back()->with('error', __('procurement.flash.unknown_article'));
        }

        $this->orders->addLine($purchaseOrder, $article, (string) $data['qty'], [
            'unit_price' => isset($data['unit_price']) ? (string) $data['unit_price'] : null,
            'note' => $data['note'] ?? null,
        ]);

        return back()->with('success', __('procurement.flash.line_added'));
    }

    /** Setzt die Frachtkosten der Bestellung (Entwurf) — Quelle für den UGL-Zuschlag (POZ). */
    public function updateConditions(Request $request, PurchaseOrder $purchaseOrder): RedirectResponse {
        $this->canManage();
        abort_unless($purchaseOrder->status === PurchaseOrderStatus::Draft, 403);

        $data = $request->validate([
            'freight_cost' => ['nullable', 'numeric', 'min:0'],
        ]);

        $purchaseOrder->update([
            'freight_cost' => ($data['freight_cost'] ?? null) !== null ? (string) $data['freight_cost'] : null,
        ]);

        return back()->with('success', __('procurement.flash.conditions_saved'));
    }

    public function submit(PurchaseOrder $purchaseOrder): RedirectResponse {
        // Pflichtnachweise (Feature 117, MVP-606): Die Sperre greift an der
        // BESTELLUNG, weil dort die Verpflichtung entsteht — und nur, wenn die
        // Organisation das Sperren eingeschaltet hat (Default: Warnung).
        $supplier = $purchaseOrder->supplier;
        if ($supplier !== null) {
            $blocking = app(\App\Services\Supplier\SupplierCredentialService::class)->blockingReasons($supplier);
            if ($blocking !== []) {
                return back()->with('error', __('procurement.credentials.blocked', [
                    'names' => implode(', ', $blocking),
                ]));
            }
        }

        return $this->guarded($purchaseOrder, fn () => $this->orders->submit($purchaseOrder), 'procurement.flash.ordered');
    }

    public function cancel(PurchaseOrder $purchaseOrder): RedirectResponse {
        return $this->guarded($purchaseOrder, fn () => $this->orders->cancel($purchaseOrder), 'procurement.flash.cancelled');
    }

    public function receive(Request $request, PurchaseOrder $purchaseOrder): RedirectResponse {
        $this->canManage();
        $data = $request->validate([
            'line' => ['required', 'string'],
            'qty' => ['required', 'numeric', 'gt:0'],
            // Vollaudit 2026-07 (M19): Pflichterfassung bei chargen-/
            // serienpflichtigen Artikeln — der Service erzwingt die Angabe.
            'lot_no' => ['nullable', 'string', 'max:120'],
            'best_before' => ['nullable', 'date'],
            'serial_no' => ['nullable', 'string', 'max:120'],
        ]);

        $lineId = app(SqidEncoder::class)->decode(PurchaseOrderLine::class, (string) $data['line']);
        $line = $lineId !== null
            ? PurchaseOrderLine::query()->where('purchase_order_id', $purchaseOrder->id)->find($lineId)
            : null;
        if (! $line instanceof PurchaseOrderLine) {
            return back()->with('error', __('procurement.flash.unknown_line'));
        }

        try {
            $this->receipts->receive(
                $line,
                (string) $data['qty'],
                actorUserId: Auth::id() !== null ? (int) Auth::id() : null,
                lotNo: $data['lot_no'] ?? null,
                bestBefore: $data['best_before'] ?? null,
                serialNo: $data['serial_no'] ?? null,
            );
        } catch (RuntimeException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', __('procurement.flash.received'));
    }

    public function suggestions(Request $request): View {
        $this->canManage();
        $warehouses = Warehouse::query()->orderBy('name')->get();
        $warehouseId = app(SqidEncoder::class)->decode(Warehouse::class, $request->string('warehouse')->toString());
        $warehouse = $warehouseId !== null ? $warehouses->firstWhere('id', $warehouseId) : $warehouses->first();

        return view('purchase-orders.suggestions', [
            'warehouses' => $warehouses,
            'warehouse' => $warehouse,
            'suggestions' => $warehouse instanceof Warehouse ? $this->suggestions->suggest($warehouse) : [],
        ]);
    }

    public function applySuggestions(Request $request): RedirectResponse {
        $this->canManage();
        $warehouseId = app(SqidEncoder::class)->decode(Warehouse::class, $request->string('warehouse')->toString());
        $warehouse = $warehouseId !== null ? Warehouse::query()->find($warehouseId) : null;
        if (! $warehouse instanceof Warehouse) {
            return back()->with('error', __('procurement.flash.no_warehouse'));
        }

        $created = $this->suggestions->createOrders($warehouse, $this->currentOrganization(), Auth::id() !== null ? (int) Auth::id() : null);

        return redirect()->route('purchase-orders.index')
            ->with('success', __('procurement.flash.suggestions_applied', ['count' => count($created)]));
    }

    /** Führt eine Bestellaktion mit Berechtigung + Fehlerbehandlung aus. */
    private function guarded(PurchaseOrder $order, callable $action, string $successKey): RedirectResponse {
        $this->canManage();
        try {
            $action();
        } catch (RuntimeException $e) {
            return back()->with('error', $e->getMessage());
        }

        return redirect()->route('purchase-orders.show', $order)->with('success', __($successKey));
    }

    private function canView(): void {
        abort_unless((Auth::user()?->can(P::InventoryViewAny->value) ?? false) || (Auth::user()?->can(P::InventoryPost->value) ?? false), 403);
    }

    private function canManage(): void {
        Gate::authorize(P::InventoryPost->value);
    }
}
