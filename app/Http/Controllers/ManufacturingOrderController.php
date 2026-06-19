<?php
/*
 * Created on   : Tue Jun 16 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ManufacturingOrderController.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Http\Controllers;

use App\Enums\Manufacturing\ManufacturingOrderStatus;
use App\Http\Controllers\Concerns\{ResolvesCurrentOrganization, ResolvesGlobalDateRange};
use App\Http\Requests\SaveManufacturingOrderRequest;
use App\Models\{Article, ArticleVariant, Customer, ManufacturingOrder, Supplier, Warehouse, WorkCenter};
use App\Services\Manufacturing\{CapacityService, DeliveryService, ManufacturingInventoryService, ManufacturingOrderService, ManufacturingReportService, SubcontractService};
use App\Services\SqidEncoder;
use Illuminate\Http\{RedirectResponse, Request};
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\{Auth, Gate};
use Illuminate\View\View;
use RuntimeException;

/**
 * Fertigungs-/Montageauftrags-UI (Feature 047). Anlegen, freigeben, starten,
 * Material reservieren, Teilrückmeldungen erfassen und Fertigerzeugnisse
 * ausliefern. Modul-Gating über `manufacturing-orders.*` → module.lager.
 */
class ManufacturingOrderController extends Controller {
    use ResolvesCurrentOrganization;
    use ResolvesGlobalDateRange;

    public function __construct(
        private readonly ManufacturingOrderService $orders,
        private readonly ManufacturingInventoryService $inventory,
        private readonly ManufacturingReportService $reports,
        private readonly DeliveryService $deliveries,
    ) {}

    public function index(Request $request): View {
        Gate::authorize('viewAny', ManufacturingOrder::class);

        $status = $request->string('status')->toString() ?: 'all';
        $range = $this->globalDateRange();
        $orders = ManufacturingOrder::query()
            ->with(['article', 'variant'])
            ->when($status !== 'all', fn ($q) => $q->where('status', $status))
            ->whereBetween('created_at', [$range['from']->startOfDay(), $range['to']->endOfDay()])
            ->latest()
            ->paginate(25)
            ->withQueryString();

        return view('manufacturing.index', [
            'orders' => $orders,
            'status' => $status,
            'statuses' => ManufacturingOrderStatus::cases(),
        ]);
    }

    public function create(): View {
        Gate::authorize('create', ManufacturingOrder::class);

        return view('manufacturing._form_dialog', [
            'isDialog' => true,
            'articles' => Article::query()->where('manufacturable', true)->orderBy('name')->limit(500)->get(),
            'variants' => ArticleVariant::query()->with('article')->orderBy('id')->limit(500)->get(),
            'warehouses' => Warehouse::query()->orderBy('name')->get(),
        ]);
    }

    public function store(SaveManufacturingOrderRequest $request): RedirectResponse {
        Gate::authorize('create', ManufacturingOrder::class);
        $data = $request->validated();

        $article = Article::query()->findOrFail((int) $data['article']);
        $variant = isset($data['variant']) ? ArticleVariant::query()->find((int) $data['variant']) : null;

        $order = $this->orders->createDraft($this->currentOrganization(), $article, $variant, (string) $data['target_qty'], (string) $data['unit'], [
            'warehouse_id' => $data['warehouse'] ?? null,
            'customer_id' => $data['customer'] ?? null,
            'priority' => $data['priority'] ?? 100,
            'due_at' => $data['due_at'] ?? null,
            'created_by' => Auth::id(),
        ]);

        return redirect()->route('manufacturing-orders.show', $order)->with('success', __('manufacturing.order.flash.created'));
    }

    public function show(ManufacturingOrder $order): View {
        Gate::authorize('view', $order);
        $order->load(['article', 'variant', 'warehouse', 'materials', 'reports']);

        return view('manufacturing.show', [
            'order' => $order,
            'suppliers' => Supplier::query()->orderBy('name')->limit(500)->get(),
            'workCenters' => WorkCenter::query()->where('active', true)->orderBy('name')->get(),
            'canManage' => Auth::user()?->can(\App\Enums\User\Permission::InventoryPost->value) ?? false,
        ]);
    }

    /** Weist den Auftrag einem Arbeitsplatz mit geplanter Belegungsdauer zu (E7). */
    public function assignWorkCenter(Request $request, ManufacturingOrder $order): RedirectResponse {
        Gate::authorize('update', $order);
        $data = $request->validate([
            'work_center' => ['required', 'string'],
            'minutes' => ['required', 'integer', 'min:0'],
            'day' => ['nullable', 'date'],
        ]);

        $wcId = app(SqidEncoder::class)->decode(WorkCenter::class, (string) $data['work_center']);
        $workCenter = $wcId !== null ? WorkCenter::query()->find($wcId) : null;
        if (! $workCenter instanceof WorkCenter) {
            return back()->with('error', __('manufacturing.capacity.flash.assign_failed'));
        }

        app(CapacityService::class)->assign(
            $order,
            $workCenter,
            (int) $data['minutes'],
            isset($data['day']) ? Carbon::parse((string) $data['day']) : null,
        );

        return back()->with('success', __('manufacturing.capacity.flash.assigned'));
    }

    /** Vergibt den Auftrag als Fremdfertigung an einen Lieferanten (Feature 047/048, E7). */
    public function subcontract(Request $request, ManufacturingOrder $order): RedirectResponse {
        Gate::authorize('update', $order);
        $supplierId = app(SqidEncoder::class)->decode(Supplier::class, $request->string('supplier')->toString());
        $supplier = $supplierId !== null ? Supplier::query()->find($supplierId) : null;
        if (! $supplier instanceof Supplier) {
            return back()->with('error', __('manufacturing.order.flash.subcontract_failed'));
        }

        try {
            $po = app(SubcontractService::class)->commission($order, $supplier, Auth::id() !== null ? (int) Auth::id() : null);
        } catch (RuntimeException $e) {
            return back()->with('error', $e->getMessage());
        }

        return redirect()->route('purchase-orders.show', $po)->with('success', __('manufacturing.order.flash.subcontracted'));
    }

    public function release(ManufacturingOrder $order): RedirectResponse {
        return $this->guarded($order, fn () => $this->orders->release($order), 'manufacturing.order.flash.released');
    }

    public function start(ManufacturingOrder $order): RedirectResponse {
        return $this->guarded($order, fn () => $this->orders->startExecution($order, Auth::id() !== null ? (int) Auth::id() : null), 'manufacturing.order.flash.started');
    }

    public function reserve(ManufacturingOrder $order): RedirectResponse {
        return $this->guarded($order, fn () => $this->inventory->reserveMaterials($order), 'manufacturing.order.flash.reserved');
    }

    public function report(Request $request, ManufacturingOrder $order): RedirectResponse {
        Gate::authorize('update', $order);
        $data = $request->validate([
            'produced_qty' => ['required', 'numeric', 'min:0'],
            'good_qty' => ['required', 'numeric', 'min:0'],
            'scrap_qty' => ['nullable', 'numeric', 'min:0'],
            'rework_qty' => ['nullable', 'numeric', 'min:0'],
        ]);

        $this->reports->report(
            $order,
            (string) $data['produced_qty'],
            (string) $data['good_qty'],
            (string) ($data['scrap_qty'] ?? '0'),
            (string) ($data['rework_qty'] ?? '0'),
            Auth::id() !== null ? (int) Auth::id() : null,
        );

        return back()->with('success', __('manufacturing.order.flash.reported'));
    }

    public function deliver(Request $request, ManufacturingOrder $order): RedirectResponse {
        Gate::authorize('update', $order);
        $data = $request->validate(['quantity' => ['required', 'numeric', 'gt:0']]);

        $variant = $order->variant ?? ArticleVariant::query()->where('article_id', $order->article_id)->orderByDesc('is_default')->first();
        $warehouse = $order->warehouse;
        if ($variant === null || $warehouse === null) {
            return back()->with('error', __('manufacturing.order.flash.deliver_needs_variant_warehouse'));
        }
        $customer = $order->customer_id !== null ? Customer::query()->find($order->customer_id) : null;

        try {
            $this->deliveries->deliver($variant, $warehouse, (string) $data['quantity'], $order, $customer, createdBy: Auth::id() !== null ? (int) Auth::id() : null);
        } catch (RuntimeException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', __('manufacturing.order.flash.delivered'));
    }

    public function cancel(ManufacturingOrder $order): RedirectResponse {
        return $this->guarded($order, fn () => $this->orders->transition($order, ManufacturingOrderStatus::Cancelled), 'manufacturing.order.flash.cancelled');
    }

    /** Führt eine Auftragsaktion mit Berechtigungsprüfung + Fehlerbehandlung aus. */
    private function guarded(ManufacturingOrder $order, callable $action, string $successKey): RedirectResponse {
        Gate::authorize('update', $order);
        try {
            $action();
        } catch (RuntimeException $e) {
            return back()->with('error', $e->getMessage());
        }

        return redirect()->route('manufacturing-orders.show', $order)->with('success', __($successKey));
    }
}
