<?php
/*
 * Created on   : Sat Aug 01 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : PrintOrderController.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Http\Controllers\Print;

use App\Enums\Print\{PrintOrderStatus, PrintOutputKind};
use App\Http\Controllers\Concerns\{ResolvesCurrentOrganization, ResolvesGlobalDateRange};
use App\Http\Controllers\Controller;
use App\Models\{Article, Asset, Customer, Organization, Shipment};
use App\Models\Print\PrintOrder;
use App\Services\Document\DocumentService;
use App\Services\Manufacturing\ManufacturingOrderService;
use App\Services\Print\PrintOrderService;
use App\Support\Sqid;
use Illuminate\Http\{RedirectResponse, Request};
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

/**
 * Druckaufträge (MVP-459): Fachakte am Fertigungsauftrag mit Dateicheck,
 * Druckfreigabe (Hash-Bindung), Produktions-, QK- und Ausgabe-Gates.
 * Sichtbar nur mit installiertem Branchenprofil `druck-kopiershop` (404);
 * Rechte laufen über die Fertigungs-Policy (1:1-Spezialisierung).
 */
class PrintOrderController extends Controller {
    use ResolvesCurrentOrganization;
    use ResolvesGlobalDateRange;

    public function __construct(
        private readonly PrintOrderService $orders,
        private readonly ManufacturingOrderService $manufacturing,
        private readonly DocumentService $documents,
    ) {}

    public function index(Request $request): View {
        Gate::authorize('viewAny', PrintOrder::class);
        $this->printOrganization();
        [$from, $to] = $this->globalDateRangeBounds();

        $statusFilter = PrintOrderStatus::tryFrom($request->string('status')->toString())?->value;
        $orders = PrintOrder::query()
            ->with(['manufacturingOrder.article', 'documentVersion'])
            // Offene Aufträge immer zeigen; geschlossene nur im globalen Zeitraum.
            ->where(function ($query) use ($from, $to): void {
                $query->open()->orWhereBetween('created_at', [$from, $to]);
            })
            ->when($statusFilter !== null, fn($q) => $q->where('status', $statusFilter))
            ->orderByDesc('id')
            ->paginate(25)
            ->withQueryString();

        return view('print.orders.index', [
            'orders' => $orders,
            'openCount' => PrintOrder::query()->open()->count(),
        ]);
    }

    public function show(PrintOrder $order): View {
        Gate::authorize('view', $order);
        $this->assertInOrganization($order->organization_id);

        $order->load(['manufacturingOrder.article', 'document', 'documentVersion', 'asset', 'shipment', 'approver', 'qcChecker']);

        return view('print.orders.show', [
            'order' => $order,
            'machines' => Asset::query()->orderBy('name')->get(['id', 'name']),
            'shipments' => Shipment::query()->orderByDesc('id')->limit(50)->get(),
        ]);
    }

    /** Dialogfragment „Neuer Druckauftrag" (data-entry-modal-trigger). */
    public function create(): View {
        Gate::authorize('create', PrintOrder::class);
        $this->printOrganization();

        return view('print.orders._form_dialog', [
            'articles' => Article::query()->where('manufacturable', true)->orderBy('name')->get(['id', 'name']),
            'customers' => Customer::query()->orderBy('name')->get(['id', 'name']),
        ]);
    }

    /** Legt Fertigungsauftrag (Entwurf) + Druck-Fachakte in einem Zug an. */
    public function store(Request $request): RedirectResponse {
        Gate::authorize('create', PrintOrder::class);
        $organization = $this->printOrganization();

        $request->merge([
            'article_id' => Sqid::decodeOrNumeric(Article::class, $request->input('article_id')),
            'customer_id' => Sqid::decodeOrNumeric(Customer::class, $request->input('customer_id')),
        ]);
        $validated = $request->validate([
            'article_id' => ['required', 'integer', new \App\Rules\ExistsInCurrentOrganization('articles')],
            'customer_id' => ['nullable', 'integer', new \App\Rules\ExistsInCurrentOrganization('customers')],
            'target_qty' => ['required', 'numeric', 'min:0.0001'],
            'unit' => ['required', 'string', 'max:16'],
            'due_at' => ['nullable', 'date'],
            'output_kind' => ['required', 'string', 'in:' . implode(',', PrintOutputKind::values())],
            'files_retain_until' => ['nullable', 'date', 'after:today'],
        ]);

        $article = Article::query()->findOrFail((int) $validated['article_id']);
        $manufacturingOrder = $this->manufacturing->createDraft($organization, $article, null, (string) $validated['target_qty'], (string) $validated['unit'], [
            'customer_id' => $validated['customer_id'] ?? null,
            'due_at' => $validated['due_at'] ?? null,
        ]);
        $order = $this->orders->open($manufacturingOrder, $request->user() ?? abort(401), [
            'output_kind' => $validated['output_kind'],
            'files_retain_until' => $validated['files_retain_until'] ?? null,
        ]);

        return redirect()->route('print-orders.show', $order)->with('status', (string) __('print.flash.created'));
    }

    /** Produktionsdatei hochladen und mit SHA-256 an den Auftrag binden. */
    public function uploadFile(Request $request, PrintOrder $order): RedirectResponse {
        Gate::authorize('update', $order);
        $this->assertInOrganization($order->organization_id);

        $validated = $request->validate([
            'file' => ['required', 'file', 'max:262144'], // 256 MB — Großformatdaten
        ]);
        $actor = $request->user() ?? abort(401);

        $document = $order->document;
        if ($document === null) {
            $document = $this->documents->create(null, $actor, [
                'title' => (string) __('print.document_title', ['number' => (string) $order->manufacturingOrder?->number]),
                'document_type' => 'other',
            ], $validated['file']);
            $version = $document->versions()->orderByDesc('version_no')->firstOrFail();
        } else {
            $version = $this->documents->addVersion($document, $actor, $validated['file']);
        }

        $this->orders->bindFile($order, $document, $version, $actor);

        return redirect()->route('print-orders.show', $order)->with('status', (string) __('print.flash.file_bound'));
    }

    public function runPreflight(Request $request, PrintOrder $order): RedirectResponse {
        Gate::authorize('update', $order);
        $this->assertInOrganization($order->organization_id);

        $this->orders->runPreflight($order, $request->user() ?? abort(401));

        return redirect()->route('print-orders.show', $order)->with('status', (string) __('print.flash.preflight_recorded'));
    }

    /** Manuellen Befund (Sichtprüfung/externes Werkzeug) dokumentieren. */
    public function recordManualPreflight(Request $request, PrintOrder $order): RedirectResponse {
        Gate::authorize('update', $order);
        $this->assertInOrganization($order->organization_id);

        $validated = $request->validate([
            'errors' => ['nullable', 'string', 'max:4000'],
            'warnings' => ['nullable', 'string', 'max:4000'],
        ]);
        $this->orders->recordManualPreflight(
            $order,
            $this->splitLines($validated['errors'] ?? null),
            $this->splitLines($validated['warnings'] ?? null),
            $request->user() ?? abort(401),
        );

        return redirect()->route('print-orders.show', $order)->with('status', (string) __('print.flash.preflight_recorded'));
    }

    public function overridePreflight(Request $request, PrintOrder $order): RedirectResponse {
        Gate::authorize('update', $order);
        $this->assertInOrganization($order->organization_id);

        $validated = $request->validate(['reason' => ['required', 'string', 'max:1000']]);
        $this->orders->overridePreflight($order, (string) $validated['reason'], $request->user() ?? abort(401));

        return redirect()->route('print-orders.show', $order)->with('status', (string) __('print.flash.preflight_overridden'));
    }

    /** Druckfreigabe: friert Parameter + Datei-Hash als Snapshot ein. */
    public function approve(Request $request, PrintOrder $order): RedirectResponse {
        Gate::authorize('update', $order);
        $this->assertInOrganization($order->organization_id);

        $validated = $request->validate([
            'final_format' => ['required', 'string', 'max:80'],
            'pages' => ['nullable', 'integer', 'min:1', 'max:10000'],
            'orientation' => ['nullable', 'string', 'max:24'],
            'bleed_mm' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'safety_mm' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'color_mode' => ['required', 'string', 'max:40'],
            'color_profile' => ['nullable', 'string', 'max:80'],
            'spot_colors' => ['nullable', 'string', 'max:200'],
            'material' => ['required', 'string', 'max:120'],
            'grammage' => ['nullable', 'string', 'max:40'],
            'quantity' => ['required', 'numeric', 'min:1'],
            'due_date' => ['required', 'date'],
            'finishing' => ['nullable', 'string', 'max:500'],
        ]);
        $validated['finishing'] = $this->splitLines($validated['finishing'] ?? null);
        $this->orders->approve($order, $validated, $request->user() ?? abort(401));

        return redirect()->route('print-orders.show', $order)->with('status', (string) __('print.flash.approved'));
    }

    public function startProduction(Request $request, PrintOrder $order): RedirectResponse {
        Gate::authorize('update', $order);
        $this->assertInOrganization($order->organization_id);

        $request->merge(['asset_id' => Sqid::decodeOrNumeric(Asset::class, $request->input('asset_id'))]);
        $validated = $request->validate([
            'asset_id' => ['nullable', 'integer', new \App\Rules\ExistsInCurrentOrganization('assets')],
        ]);
        $machine = isset($validated['asset_id'])
            ? Asset::query()->findOrFail((int) $validated['asset_id'])
            : null;

        try {
            $this->orders->startProduction($order, $machine, $request->user() ?? abort(401));
        } catch (\App\Exceptions\AssetNotUsableException $e) {
            // D12-Guard: strukturiert zurückmelden statt 500 (Maschinen-Gate).
            return redirect()->route('print-orders.show', $order)->withErrors(['asset_id' => $e->getMessage()]);
        }

        return redirect()->route('print-orders.show', $order)->with('status', (string) __('print.flash.production_started'));
    }

    public function qualityCheck(Request $request, PrintOrder $order): RedirectResponse {
        Gate::authorize('update', $order);
        $this->assertInOrganization($order->organization_id);

        $validated = $request->validate([
            'result' => ['required', 'string', 'in:' . implode(',', [PrintOrder::QC_PASSED, PrintOrder::QC_REWORK, PrintOrder::QC_BLOCKED])],
            'note' => ['nullable', 'string', 'max:1000'],
        ]);
        $this->orders->qualityCheck($order, (string) $validated['result'], $validated['note'] ?? null, $request->user() ?? abort(401));

        return redirect()->route('print-orders.show', $order)->with('status', (string) __('print.flash.quality_checked'));
    }

    public function resumeProduction(Request $request, PrintOrder $order): RedirectResponse {
        Gate::authorize('update', $order);
        $this->assertInOrganization($order->organization_id);

        $this->orders->resumeProduction($order, $request->user() ?? abort(401));

        return redirect()->route('print-orders.show', $order)->with('status', (string) __('print.flash.production_started'));
    }

    /** Ausgabe: Abholung (Nachweis), Versand (Sendung) oder Tresen. */
    public function issue(Request $request, PrintOrder $order): RedirectResponse {
        Gate::authorize('update', $order);
        $this->assertInOrganization($order->organization_id);

        $request->merge(['shipment_id' => Sqid::decodeOrNumeric(Shipment::class, $request->input('shipment_id'))]);
        $validated = $request->validate([
            'handover_name' => ['nullable', 'string', 'max:200'],
            'handover_note' => ['nullable', 'string', 'max:1000'],
            'shipment_id' => ['nullable', 'integer', new \App\Rules\ExistsInCurrentOrganization('shipments')],
        ]);
        $shipment = isset($validated['shipment_id'])
            ? Shipment::query()->findOrFail((int) $validated['shipment_id'])
            : null;
        $this->orders->issue($order, [
            'handover_name' => $validated['handover_name'] ?? null,
            'handover_note' => $validated['handover_note'] ?? null,
            'shipment' => $shipment,
        ], $request->user() ?? abort(401));

        return redirect()->route('print-orders.show', $order)->with('status', (string) __('print.flash.issued'));
    }

    public function cancel(Request $request, PrintOrder $order): RedirectResponse {
        Gate::authorize('update', $order);
        $this->assertInOrganization($order->organization_id);

        $validated = $request->validate(['reason' => ['required', 'string', 'max:1000']]);
        $this->orders->cancel($order, (string) $validated['reason'], $request->user() ?? abort(401));

        return redirect()->route('print-orders.show', $order)->with('status', (string) __('print.flash.cancelled'));
    }

    /** @return list<string> */
    private function splitLines(?string $raw): array {
        return array_values(array_filter(array_map('trim', preg_split('/\r\n|\r|\n/', (string) $raw) ?: []), static fn(string $line): bool => $line !== ''));
    }

    private function assertInOrganization(int $organizationId): void {
        $organization = $this->printOrganization();
        abort_unless($organizationId === $organization->id, 404);
    }

    /** Branchenprofil-Gate: 404 ohne installiertes Profil (Muster Recipes). */
    private function printOrganization(): Organization {
        $organization = $this->currentOrganization();
        abort_unless($this->orders->isPrintProfileActive($organization), 404);

        return $organization;
    }
}
