<?php
/*
 * Created on   : Fri Jun 19 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ScanController.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Http\Controllers;

use App\Enums\Inventory\ScanAction;
use App\Enums\User\Permission as P;
use App\Models\Warehouse;
use App\Services\Inventory\{BarcodeResolver, ScanActionService};
use App\Support\Sqid;
use Illuminate\Http\{RedirectResponse, Request};
use Illuminate\Support\Facades\{Auth, Gate};
use Illuminate\View\View;
use RuntimeException;

/**
 * Mobile Scan-/Buchungs-UI (Feature 048, E5): einen Code scannen, auflösen und
 * eine Bestandsaktion (Eingang/Entnahme/Umlagerung) buchen. Sehen mit
 * inventory.viewAny, Buchen mit inventory.post.
 */
class ScanController extends Controller {
    public function __construct(
        private readonly ScanActionService $scan,
        private readonly BarcodeResolver $resolver,
    ) {}

    public function index(Request $request): View {
        abort_unless((Auth::user()?->can(P::InventoryViewAny->value) ?? false) || (Auth::user()?->can(P::InventoryPost->value) ?? false), 403);

        $code = $request->string('code')->toString();

        return view('inventory.scan.index', [
            'warehouses' => Warehouse::query()->orderByDesc('is_default')->orderBy('name')->get(),
            'actions' => ScanAction::cases(),
            'code' => $code,
            'match' => $code !== '' ? $this->resolver->resolve($code) : null,
            'canPost' => Auth::user()->can(P::InventoryPost->value),
        ]);
    }

    public function book(Request $request): RedirectResponse {
        Gate::authorize(P::InventoryPost->value);

        $data = $request->validate([
            'code' => ['required', 'string'],
            'action' => ['required', 'string'],
            'warehouse' => ['required', 'string'],
            'qty' => ['required', 'numeric', 'gt:0'],
            'target' => ['nullable', 'string'],
        ]);

        $action = ScanAction::tryFrom((string) $data['action']);
        $warehouse = Warehouse::query()->find(Sqid::decodeOrNumeric(Warehouse::class, (string) $data['warehouse']));
        if ($action === null || ! $warehouse instanceof Warehouse) {
            return back()->with('error', __('inventory.scan.invalid'));
        }

        $options = ['actor' => Auth::id() !== null ? (int) Auth::id() : null];
        $targetRaw = (string) ($data['target'] ?? '');
        if ($targetRaw !== '') {
            $options['target'] = Warehouse::query()->find(Sqid::decodeOrNumeric(Warehouse::class, $targetRaw));
        }

        try {
            $this->scan->book((string) $data['code'], $action, $warehouse, (string) $data['qty'], $options);
        } catch (RuntimeException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', __('inventory.scan.booked'));
    }
}
