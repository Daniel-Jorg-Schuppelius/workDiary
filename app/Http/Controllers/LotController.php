<?php
/*
 * Created on   : Fri Jun 19 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : LotController.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Http\Controllers;

use App\Enums\User\Permission as P;
use App\Models\StockLot;
use App\Services\Inventory\{LotService, LotSplitService};
use App\Services\SqidEncoder;
use Illuminate\Http\{RedirectResponse, Request};
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;
use RuntimeException;

/**
 * Chargenverwaltung (Feature 047/048, E2/E7): Chargenliste mit Restbestand sowie
 * Los-Split und -Merge. Lesen mit inventory.viewAny, Aktionen mit inventory.post.
 */
class LotController extends Controller {
    public function __construct(
        private readonly LotService $lots,
        private readonly LotSplitService $split,
    ) {}

    public function index(): View {
        $this->canView();

        $lots = StockLot::query()->with('variant.article')->orderByDesc('id')->paginate(40);
        $onHand = [];
        foreach ($lots as $lot) {
            $onHand[$lot->id] = $this->lots->onHand($lot);
        }

        return view('inventory.lots.index', [
            'lots' => $lots,
            'onHand' => $onHand,
            'canManage' => Auth::user()?->can(P::InventoryPost->value) ?? false,
        ]);
    }

    public function splitLot(Request $request): RedirectResponse {
        $this->canManage();
        $data = $request->validate([
            'lot' => ['required', 'string'],
            'qty' => ['required', 'numeric', 'gt:0'],
            'new_lot_no' => ['required', 'string', 'max:80'],
            'best_before' => ['nullable', 'date'],
        ]);

        $lot = $this->resolve((string) $data['lot']);
        if (! $lot instanceof StockLot) {
            return back()->with('error', __('inventory.lot.flash.unknown'));
        }

        try {
            $this->split->split($lot, (string) $data['qty'], (string) $data['new_lot_no'], $data['best_before'] ?? null);
        } catch (RuntimeException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', __('inventory.lot.flash.split'));
    }

    public function mergeLot(Request $request): RedirectResponse {
        $this->canManage();
        $data = $request->validate([
            'from' => ['required', 'string'],
            'into' => ['required', 'string'],
        ]);

        $from = $this->resolve((string) $data['from']);
        $into = $this->resolve((string) $data['into']);
        if (! $from instanceof StockLot || ! $into instanceof StockLot) {
            return back()->with('error', __('inventory.lot.flash.unknown'));
        }

        try {
            $this->split->merge($from, $into);
        } catch (RuntimeException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', __('inventory.lot.flash.merged'));
    }

    private function resolve(string $sqid): ?StockLot {
        $id = app(SqidEncoder::class)->decode(StockLot::class, $sqid);

        return $id !== null ? StockLot::query()->find($id) : null;
    }

    private function canView(): void {
        abort_unless((Auth::user()?->can(P::InventoryViewAny->value) ?? false) || (Auth::user()?->can(P::InventoryPost->value) ?? false), 403);
    }

    private function canManage(): void {
        abort_unless(Auth::user()?->can(P::InventoryPost->value) ?? false, 403);
    }
}
