<?php
/*
 * Created on   : Tue Jun 16 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : StocktakeController.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Http\Controllers;

use App\Enums\User\Permission as P;
use App\Http\Controllers\Concerns\ResolvesGlobalDateRange;
use App\Models\{StockCount, Warehouse};
use App\Services\Inventory\{CycleCountPlanner, StocktakeService};
use App\Support\Sqid;
use Illuminate\Http\{RedirectResponse, Request};
use Illuminate\Support\Facades\{Auth, Gate};
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
        abort_unless(Auth::user()?->can(P::InventoryPost->value) ?? false, 403);

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
        abort_unless(Auth::user()?->can(P::InventoryPost->value) ?? false, 403);
        abort_unless($count->status->isOpen(), 422);

        $data = $request->validate([
            'code' => ['required', 'string'],
            'qty' => ['required', 'numeric', 'min:0'],
        ]);

        try {
            $this->stocktake->recordByScan($count, (string) $data['code'], (string) $data['qty'], Auth::id() !== null ? (int) Auth::id() : null);
        } catch (RuntimeException $e) {
            return back()->with('error', $e->getMessage());
        }

        return redirect()->route('inventory.counts.show', $count)->with('success', __('inventory.count_ui.saved'));
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
                ->latest('counted_at')->limit(50)->get()
            : collect();

        return view('inventory.counts.index', [
            'warehouses' => $warehouses,
            'selected' => $selected,
            'counts' => $counts,
            'canCount' => Auth::user()?->can(P::InventoryPost->value) ?? false,
        ]);
    }

    public function open(Request $request): RedirectResponse {
        abort_unless(Auth::user()?->can(P::InventoryPost->value) ?? false, 403);

        $warehouse = Warehouse::query()->findOrFail(Sqid::decodeOrNumeric(Warehouse::class, $request->input('warehouse')));
        $count = $this->stocktake->open($warehouse, Auth::id() !== null ? (int) Auth::id() : null);

        return redirect()->route('inventory.counts.show', $count)->with('success', __('inventory.count_ui.opened'));
    }

    public function show(StockCount $count): View {
        Gate::authorize('viewAny', Warehouse::class);

        $count->load(['warehouse', 'lines.variant.article']);

        return view('inventory.counts.show', [
            'count' => $count,
            'canCount' => Auth::user()?->can(P::InventoryPost->value) ?? false,
            'canApply' => Auth::user()?->can(P::InventoryConfigure->value) ?? false,
        ]);
    }

    public function record(Request $request, StockCount $count): RedirectResponse {
        abort_unless(Auth::user()?->can(P::InventoryPost->value) ?? false, 403);
        abort_unless($count->status->isOpen(), 422);

        $data = $request->validate([
            'counted' => ['required', 'array'],
            'counted.*' => ['nullable', 'numeric', 'min:0'],
        ]);

        $actor = Auth::id() !== null ? (int) Auth::id() : null;
        foreach ($count->lines as $line) {
            $value = $data['counted'][$line->id] ?? null;
            if ($value !== null && $value !== '') {
                $this->stocktake->recordCount($line, (string) $value, $actor);
            }
        }

        return redirect()->route('inventory.counts.show', $count)->with('success', __('inventory.count_ui.saved'));
    }

    public function apply(StockCount $count): RedirectResponse {
        abort_unless(Auth::user()?->can(P::InventoryConfigure->value) ?? false, 403);
        abort_unless($count->status->isOpen(), 422);

        try {
            $this->stocktake->applyDifferences($count, Auth::id() !== null ? (int) Auth::id() : null);
        } catch (RuntimeException $e) {
            return back()->with('error', $e->getMessage());
        }

        return redirect()->route('inventory.counts.show', $count)->with('success', __('inventory.count_ui.applied'));
    }
}
