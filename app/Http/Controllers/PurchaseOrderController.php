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
use App\Services\Procurement\{AdviceService, GoodsReceiptService, ProcurementSuggestionService, PurchaseOrderService};
use App\Services\SqidEncoder;
use Illuminate\Http\{RedirectResponse, Request};
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;
use RuntimeException;

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
        ]);

        $articleId = app(SqidEncoder::class)->decode(Article::class, (string) $data['article']);
        $article = $articleId !== null ? Article::query()->find($articleId) : null;
        if (! $article instanceof Article) {
            return back()->with('error', __('procurement.flash.unknown_article'));
        }

        $this->orders->addLine($purchaseOrder, $article, (string) $data['qty'], [
            'unit_price' => isset($data['unit_price']) ? (string) $data['unit_price'] : null,
        ]);

        return back()->with('success', __('procurement.flash.line_added'));
    }

    public function submit(PurchaseOrder $purchaseOrder): RedirectResponse {
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
        ]);

        $lineId = app(SqidEncoder::class)->decode(PurchaseOrderLine::class, (string) $data['line']);
        $line = $lineId !== null
            ? PurchaseOrderLine::query()->where('purchase_order_id', $purchaseOrder->id)->find($lineId)
            : null;
        if (! $line instanceof PurchaseOrderLine) {
            return back()->with('error', __('procurement.flash.unknown_line'));
        }

        try {
            $this->receipts->receive($line, (string) $data['qty'], actorUserId: Auth::id() !== null ? (int) Auth::id() : null);
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
        abort_unless(Auth::user()?->can(P::InventoryPost->value) ?? false, 403);
    }
}
