<?php
/*
 * Created on   : Fri Jun 19 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : WorkCenterController.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Http\Controllers;

use App\Enums\User\Permission as P;
use App\Http\Controllers\Concerns\{ResolvesCurrentOrganization, ResolvesGlobalDateRange};
use App\Models\{ManufacturingOrder, WorkCenter};
use App\Services\Manufacturing\CapacityService;
use Illuminate\Http\{RedirectResponse, Request};
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\{Auth, Gate};
use Illuminate\View\View;

/**
 * Kapazitätsboard (Feature 047/048, E7): Arbeitsplätze anlegen und ihre Tageslast
 * (geplante Minuten inkl. Rüstzeit gegen die Tageskapazität) anzeigen. Lesen mit
 * der Fertigungs-Berechtigung, Anlegen mit inventory.post.
 */
class WorkCenterController extends Controller {
    use ResolvesCurrentOrganization;
    use ResolvesGlobalDateRange;

    public function __construct(private readonly CapacityService $capacity) {}

    public function index(): View {
        Gate::authorize('viewAny', ManufacturingOrder::class);

        // Kapazität über den globalen Header-Zeitraum aggregiert (statt Einzeltag).
        $range = $this->globalDateRange();
        $from = Carbon::parse($range['from']->toDateString())->startOfDay();
        $to = Carbon::parse($range['to']->toDateString())->startOfDay();
        $board = WorkCenter::query()->orderBy('name')->get()
            ->map(fn (WorkCenter $center): array => ['center' => $center, 'load' => $this->capacity->loadRange($center, $from, $to)]);

        return view('manufacturing.work-centers.index', [
            'board' => $board,
            'from' => $from,
            'to' => $to,
            'canManage' => Auth::user()?->can(P::InventoryPost->value) ?? false,
        ]);
    }

    public function create(): View {
        abort_unless(Auth::user()?->can(P::InventoryPost->value) ?? false, 403);

        return view('manufacturing.work-centers._form_dialog', ['isDialog' => true]);
    }

    public function store(Request $request): RedirectResponse {
        abort_unless(Auth::user()?->can(P::InventoryPost->value) ?? false, 403);
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'code' => ['nullable', 'string', 'max:32'],
            'capacity_minutes' => ['required', 'integer', 'min:1'],
            'setup_minutes' => ['nullable', 'integer', 'min:0'],
        ]);

        WorkCenter::query()->create([
            'organization_id' => $this->currentOrganization()->id,
            'name' => $data['name'],
            'code' => $data['code'] ?? null,
            'capacity_minutes' => (int) $data['capacity_minutes'],
            'setup_minutes' => (int) ($data['setup_minutes'] ?? 0),
        ]);

        return redirect()->route('work-centers.index')->with('success', __('manufacturing.capacity.flash.created'));
    }
}
