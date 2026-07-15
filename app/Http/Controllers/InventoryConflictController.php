<?php
/*
 * Created on   : Fri Jun 26 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : InventoryConflictController.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Http\Controllers;

use App\Enums\User\Permission as P;
use App\Models\{PendingExternalConflict, User};
use App\Services\Inventory\InventoryConflictResolver;
use Illuminate\Http\{RedirectResponse, Request};
use Illuminate\Support\Facades\{Auth, Gate};
use Illuminate\View\View;
use RuntimeException;

/**
 * Konflikt-Inbox für kompensationspflichtige Inventory-Outbox-Einträge
 * (Feature 048, MVP-072). Schlägt die externe Spiegelung einer lokal gebuchten
 * Bewegung endgültig fehl, entsteht ein `inventory_outbox`-Konflikt, der hier
 * sichtbar wird. Pro Konflikt kann ein Berechtigter:
 *  - keep-local:  den lokalen Stand bewusst beibehalten, oder
 *  - compensate:  die Bewegung per Gegenbuchung ausgleichen.
 *
 * Lesen mit inventory.viewAny, Auflösen (Buchung) mit inventory.post.
 */
class InventoryConflictController extends Controller {
    public function __construct(private readonly InventoryConflictResolver $resolver) {}

    public function index(Request $request): View {
        $user = $this->user();
        Gate::authorize(P::InventoryViewAny->value);

        $status = (string) $request->input('status', PendingExternalConflict::STATUS_OPEN);

        $query = PendingExternalConflict::query()
            ->where('organization_id', $user->organization_id)
            ->where('conflict_type', 'inventory_outbox')
            ->with('referenceable')
            ->orderByDesc('id');

        if ($status !== '' && $status !== 'all') {
            $query->where('status', $status);
        }

        return view('inventory.conflicts.index', [
            'conflicts' => $query->paginate(25)->withQueryString(),
            'filters' => ['status' => $status],
            'canResolve' => $user->can(P::InventoryPost->value),
        ]);
    }

    public function keepLocal(PendingExternalConflict $conflict): RedirectResponse {
        $this->authorizeResolve($conflict);

        try {
            $this->resolver->keepLocal($conflict, Auth::id() !== null ? (int) Auth::id() : null);
        } catch (RuntimeException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', __('inventory.conflict.flash.kept_local'));
    }

    public function compensate(PendingExternalConflict $conflict): RedirectResponse {
        $this->authorizeResolve($conflict);

        try {
            $this->resolver->compensate($conflict, Auth::id() !== null ? (int) Auth::id() : null);
        } catch (RuntimeException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', __('inventory.conflict.flash.compensated'));
    }

    private function authorizeResolve(PendingExternalConflict $conflict): void {
        $user = $this->user();
        Gate::authorize(P::InventoryPost->value);
        abort_unless($conflict->organization_id === $user->organization_id, 403);
    }

    private function user(): User {
        $user = Auth::user();
        abort_unless($user instanceof User, 403);

        return $user;
    }
}
