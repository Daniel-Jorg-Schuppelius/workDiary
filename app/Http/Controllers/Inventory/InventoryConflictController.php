<?php
/*
 * Created on   : Fri Jun 26 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : InventoryConflictController.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Http\Controllers\Inventory;

use App\Enums\Integration\ExternalConflictStatus;
use App\Enums\User\Permission as P;
use App\Http\Controllers\Concerns\ResolvesCurrentOrganization;
use App\Http\Controllers\Controller;
use App\Models\Integration\PendingExternalConflict;
use App\Plugins\PluginManager;
use App\Services\Inventory\InventoryConflictResolver;
use App\Support\ErrorText;
use Illuminate\Http\{RedirectResponse, Request};
use Illuminate\Support\Facades\{Auth, Gate};
use Illuminate\View\View;
use RuntimeException;

/**
 * Konflikt-Inbox der Fremdsysteme (Feature 048, MVP-072).
 *
 * `inventory_outbox`: Schlägt die externe Spiegelung einer lokal gebuchten
 * Bewegung endgültig fehl, kann ein Berechtigter
 *  - keep-local:  den lokalen Stand bewusst beibehalten, oder
 *  - compensate:  die Bewegung per Gegenbuchung ausgleichen.
 *
 * Sehen mit inventory.viewAny, auflösen mit inventory.post.
 */
class InventoryConflictController extends Controller {
    use ResolvesCurrentOrganization;

    public function __construct(private readonly InventoryConflictResolver $resolver, private readonly PluginManager $plugins) {}

    public function index(Request $request): View {
        $user = $this->authUser();
        $types = PendingExternalConflict::typesVisibleTo($user);
        abort_if($types === [], 403);

        $status = (string) $request->input('status', ExternalConflictStatus::Open->value);

        $query = PendingExternalConflict::query()
            ->where('organization_id', $this->currentOrganizationId())
            ->whereIn('conflict_type', $types)
            ->orderByDesc('id');

        if ($status !== '' && $status !== 'all') {
            $query->where('status', $status);
        }

        $conflicts = $query->paginate(25)->withQueryString();

        return view('inventory.conflicts.index', [
            'conflicts' => $conflicts,
            // Anzeigename des meldenden Plugins statt seiner Kennung.
            'pluginLabels' => $conflicts->getCollection()->pluck('plugin_id')->unique()
                ->mapWithKeys(fn (string $id): array => [$id => $this->plugins->find($id)?->name() ?? $id])
                ->all(),
            'filters' => ['status' => $status],
            'canResolveStock' => $user->can(P::InventoryExternalResolve->value),
        ]);
    }

    public function keepLocal(PendingExternalConflict $conflict): RedirectResponse {
        $this->authorizeResolve($conflict);

        try {
            $this->resolver->keepLocal($conflict, Auth::id() !== null ? (int) Auth::id() : null);
        } catch (RuntimeException $e) {
            return back()->with('error', ErrorText::for($e));
        }

        return back()->with('success', __('inventory.conflict.flash.kept_local'));
    }

    public function compensate(PendingExternalConflict $conflict): RedirectResponse {
        $this->authorizeResolve($conflict);

        try {
            $this->resolver->compensate($conflict, Auth::id() !== null ? (int) Auth::id() : null);
        } catch (RuntimeException $e) {
            return back()->with('error', ErrorText::for($e));
        }

        return back()->with('success', __('inventory.conflict.flash.compensated'));
    }

    private function authorizeResolve(PendingExternalConflict $conflict): void {
        Gate::authorize(P::InventoryExternalResolve->value);
        $this->assertSameOrganization($conflict, 403);
    }
}
