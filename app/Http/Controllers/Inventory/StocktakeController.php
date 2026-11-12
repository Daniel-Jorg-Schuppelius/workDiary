<?php
/*
 * Created on   : Tue Jun 16 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : StocktakeController.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Http\Controllers\Inventory;

use App\Enums\Inventory\StockCountStatus;
use App\Enums\User\Permission as P;
use App\Http\Controllers\Concerns\ResolvesGlobalDateRange;
use App\Http\Controllers\Controller;
use App\Models\Inventory\{StockCount, Warehouse};
use App\Services\Inventory\{CycleCountPlanner, StocktakeService};
use App\Support\{ErrorText, Sqid};
use Illuminate\Http\{RedirectResponse, Request};
use Illuminate\Support\Facades\{Auth, Gate};
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use RuntimeException;

/**
 * Stichtagsbezogene Inventur-UI (Feature 048, MVP-069): Inventur eröffnen,
 * zählen und freigegebene Differenzen als Korrekturbuchungen buchen. Sehen mit
 * inventory.viewAny, Zählen mit inventory.post, Differenzfreigabe mit
 * inventory.configure.
 */
class StocktakeController extends Controller {
    use ResolvesGlobalDateRange;

    public function __construct(
        private readonly StocktakeService $stocktake,
        private readonly CycleCountPlanner $planner,
    ) {}

    /** Eröffnet eine zyklische Inventur über eine ABC-Klasse (Feature 048, E6). */
    public function openCycle(Request $request): RedirectResponse {
        Gate::authorize(P::InventoryCount->value);

        $warehouse = Warehouse::query()->findOrFail(Sqid::decodeOrNumeric(Warehouse::class, $request->input('warehouse')));
        $class = strtoupper($request->string('abc_class')->toString() ?: 'A');
        $variantIds = $this->planner->dueVariants($warehouse, [$class]);
        if ($variantIds === []) {
            return back()->with('error', __('inventory.count_ui.cycle_empty'));
        }

        $count = $this->stocktake->openCycle($warehouse, $variantIds, Auth::id() !== null ? (int) Auth::id() : null);

        return redirect()->route('inventory.counts.show', $count)->with('success', __('inventory.count_ui.opened'));
    }

    /** Erfasst eine Zählmenge per Scan in eine laufende Inventur (Feature 048, E6). */
    public function recordScan(Request $request, StockCount $count): RedirectResponse {
        Gate::authorize(P::InventoryCount->value);
        abort_unless($count->status->isCounting(), 422);

        $data = $request->validate([
            'code' => ['required', 'string'],
            'qty' => ['required', 'numeric', 'min:0'],
            'unit' => ['nullable', 'string', 'max:20'],
        ]);

        try {
            $this->stocktake->recordByScan($count, (string) $data['code'], (string) $data['qty'], Auth::id() !== null ? (int) Auth::id() : null, $data['unit'] ?? null);
        } catch (RuntimeException $e) {
            return back()->with('error', ErrorText::for($e));
        }

        return redirect()->route('inventory.counts.show', $count)->with('success', __('inventory.count_ui.saved'));
    }

    /** Zählansicht fürs Handy (MVP-898): Scan für Scan, jede Erfassung addiert. */
    public function mobile(StockCount $count): View {
        Gate::authorize(P::InventoryCount->value);

        $count->load(['warehouse', 'lines.variant.article.units' => fn ($q) => $q->where('active', true)->orderBy('code')]);
        $lastId = session('inventory.count.last_line');

        return view('inventory.counts.mobile', [
            'count' => $count,
            'last' => is_int($lastId) ? $count->lines->firstWhere('id', $lastId) : null,
            'countedLines' => $count->lines->whereNotNull('counted_qty')->count(),
        ]);
    }

    public function scanAdd(Request $request, StockCount $count): RedirectResponse {
        Gate::authorize(P::InventoryCount->value);
        abort_unless($count->status->isCounting(), 422);

        $data = $request->validate([
            'code' => ['required', 'string', 'max:120'],
            'qty' => ['required', 'numeric', 'gt:0'],
            'unit' => ['nullable', 'string', 'max:20'],
        ]);

        try {
            $line = $this->stocktake->addByScan($count, (string) $data['code'], (string) $data['qty'], Auth::id() !== null ? (int) Auth::id() : null, $data['unit'] ?? null);
        } catch (RuntimeException $e) {
            return redirect()->route('inventory.counts.mobile', $count)->with('error', ErrorText::for($e));
        }

        return redirect()->route('inventory.counts.mobile', $count)->with('inventory.count.last_line', $line->id);
    }

    public function index(Request $request): View {
        Gate::authorize('viewAny', Warehouse::class);

        $warehouses = Warehouse::query()->orderByDesc('is_default')->orderBy('name')->get();
        $selectedId = Sqid::decodeOrNumeric(Warehouse::class, $request->query('warehouse'));
        $selected = $selectedId !== null ? $warehouses->firstWhere('id', $selectedId) : $warehouses->first();

        $range = $this->globalDateRange();
        $counts = $selected instanceof Warehouse
            ? StockCount::query()->where('warehouse_id', $selected->id)
                ->whereBetween('created_at', [$range['from']->startOfDay(), $range['to']->endOfDay()])
                ->latest('counted_at')->orderByDesc('id')->paginate(25)->withQueryString()
            : null;

        return view('inventory.counts.index', [
            'warehouses' => $warehouses,
            'selected' => $selected,
            'counts' => $counts,
            'canCount' => Auth::user()?->can(P::InventoryCount->value) ?? false,
        ]);
    }

    public function open(Request $request): RedirectResponse {
        Gate::authorize(P::InventoryCount->value);

        $warehouse = Warehouse::query()->findOrFail(Sqid::decodeOrNumeric(Warehouse::class, $request->input('warehouse')));
        $count = $this->stocktake->open($warehouse, Auth::id() !== null ? (int) Auth::id() : null);

        return redirect()->route('inventory.counts.show', $count)->with('success', __('inventory.count_ui.opened'));
    }

    public function show(StockCount $count): View {
        Gate::authorize('viewAny', Warehouse::class);

        $count->load(['warehouse', 'lines.variant.article.units' => fn ($q) => $q->where('active', true)->orderBy('code')]);

        return view('inventory.counts.show', [
            'count' => $count,
            'canCount' => Auth::user()?->can(P::InventoryCount->value) ?? false,
            'canApply' => Auth::user()?->can(P::InventoryCountApprove->value) ?? false,
        ]);
    }

    public function record(Request $request, StockCount $count): RedirectResponse {
        Gate::authorize(P::InventoryCount->value);
        abort_unless($count->status->isCounting(), 422);

        $data = $request->validate([
            'counted' => ['required', 'array'],
            'counted.*' => ['nullable', 'numeric', 'min:0'],
            // Einheit je Zeile (MVP-1584); leer = Basiseinheit.
            'unit' => ['nullable', 'array'],
            'unit.*' => ['nullable', 'string', 'max:20'],
        ]);

        $actor = Auth::id() !== null ? (int) Auth::id() : null;
        $count->load('lines.variant.article');
        try {
            foreach ($count->lines as $line) {
                $value = $data['counted'][$line->id] ?? null;
                if ($value !== null && $value !== '') {
                    $this->stocktake->recordCount($line, $this->stocktake->inBase($line, (string) $value, $data['unit'][$line->id] ?? null), $actor);
                }
            }
        } catch (RuntimeException $e) {
            return back()->withInput()->with('error', ErrorText::for($e));
        }

        return redirect()->route('inventory.counts.show', $count)->with('success', __('inventory.count_ui.saved'));
    }

    /**
     * Zählung abschließen, zur Korrektur wieder öffnen oder verwerfen (MVP-1620).
     * Abschließen darf, wer zählt; Öffnen und Verwerfen, wer freigibt.
     */
    public function transition(Request $request, StockCount $count): RedirectResponse {
        $data = $request->validate([
            'status' => ['required', Rule::enum(StockCountStatus::class)->only([StockCountStatus::Review, StockCountStatus::Counting, StockCountStatus::Cancelled])],
        ]);
        $target = StockCountStatus::from((string) $data['status']);
        Gate::authorize($target === StockCountStatus::Review ? P::InventoryCount->value : P::InventoryCountApprove->value);

        try {
            $this->stocktake->transition($count, $target, $this->authUser()->id);
        } catch (RuntimeException $e) {
            return back()->with('error', ErrorText::for($e));
        }

        return redirect()->route('inventory.counts.show', $count)->with('success', __('inventory.count_ui.status_flash.' . $target->value));
    }

    public function apply(StockCount $count): RedirectResponse {
        Gate::authorize(P::InventoryCountApprove->value);
        abort_unless($count->status->isOpen(), 422);

        try {
            $this->stocktake->applyDifferences($count, $this->authUser()->id);
        } catch (RuntimeException $e) {
            return back()->with('error', ErrorText::for($e));
        }

        return redirect()->route('inventory.counts.show', $count)->with('success', __('inventory.count_ui.applied'));
    }
}
