<?php
/*
 * Created on   : Thu Jul 30 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : RecipeMenuController.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Http\Controllers\Recipes;

use App\Http\Controllers\Controller;
use App\Models\{Organization, ProcedureTemplate, User};
use App\Models\Recipes\{RecipeMenu, RecipeMenuItem, RecipeProfile};
use App\Services\Recipes\RecipeService;
use Illuminate\Http\{RedirectResponse, Request};
use Illuminate\Support\Facades\{Auth, Gate};
use Illuminate\View\View;

/**
 * Menü-/Buffetplanung (MVP-455): partyservicebezogener Einstieg in die
 * Rezepturen. Die Gästezahl aggregiert die Bedarfe der veröffentlichten
 * Rezeptstände (keine Positionsduplikate) und weist Menü-Allergene aus.
 * Nur bei installiertem Branchenprofil `partyservice` erreichbar (404) und
 * über `module.lager` gegatet (config/plans.php).
 */
class RecipeMenuController extends Controller {
    public function __construct(private readonly RecipeService $recipes) {}

    public function index(): View {
        Gate::authorize('viewAny', ProcedureTemplate::class);
        $organization = $this->partyOrganization();

        return view('recipes.menus.index', [
            'menus' => RecipeMenu::query()
                ->where('organization_id', $organization->id)
                ->withCount('items')
                ->orderByDesc('id')
                ->get(),
        ]);
    }

    public function store(Request $request): RedirectResponse {
        Gate::authorize('create', ProcedureTemplate::class);
        $organization = $this->partyOrganization();

        $data = $request->validate([
            'name' => ['required', 'string', 'max:160'],
            'event_date' => ['nullable', 'date'],
            'guest_count' => ['nullable', 'integer', 'min:1', 'max:100000'],
        ]);

        /** @var User $user */
        $user = Auth::user();
        $menu = RecipeMenu::query()->create($data + [
            'organization_id' => $organization->id,
            'created_by' => $user->id,
        ]);
        $menu->audit('recipe.menu_created', ['name' => $menu->name]);

        return redirect()->route('recipe-menus.show', $menu)->with('success', __('recipes.flash.menu_saved'));
    }

    public function show(Request $request, RecipeMenu $menu): View {
        Gate::authorize('viewAny', ProcedureTemplate::class);
        $organization = $this->partyOrganization();
        abort_unless($menu->organization_id === $organization->id, 404);

        $menu->load('items.template');
        $guests = max(1, (int) $request->query('guests', (string) ($menu->guest_count ?? 1)));

        // Gerichte = Templates mit Rezeptprofil (irgendeine Version).
        $dishOptions = ProcedureTemplate::query()
            ->where('organization_id', $organization->id)
            ->where('active', true)
            ->whereIn('id', RecipeProfile::query()
                ->where('organization_id', $organization->id)
                ->join('procedure_template_versions', 'procedure_template_versions.id', '=', 'recipe_profiles.procedure_template_version_id')
                ->pluck('procedure_template_versions.procedure_template_id'))
            ->orderBy('name')
            ->get(['id', 'name']);

        return view('recipes.menus.show', [
            'menu' => $menu,
            'guests' => $guests,
            'aggregate' => $this->recipes->aggregateMenu($menu, $guests),
            'allergens' => $this->recipes->allergensForMenu($menu),
            'dishOptions' => $dishOptions,
        ]);
    }

    public function storeItem(Request $request, RecipeMenu $menu): RedirectResponse {
        Gate::authorize('create', ProcedureTemplate::class);
        $organization = $this->partyOrganization();
        abort_unless($menu->organization_id === $organization->id, 404);

        $data = $request->validate([
            'dish' => ['required', 'string'],
            'portions_per_guest' => ['nullable', 'numeric', 'gt:0', 'max:100'],
        ]);

        $template = ProcedureTemplate::query()
            ->where('organization_id', $organization->id)
            ->whereKey(app(\App\Services\SqidEncoder::class)->decode(ProcedureTemplate::class, (string) $data['dish']))
            ->first();
        abort_unless($template instanceof ProcedureTemplate, 404);

        RecipeMenuItem::query()->updateOrCreate(
            ['recipe_menu_id' => $menu->id, 'procedure_template_id' => $template->id],
            [
                'organization_id' => $organization->id,
                'portions_per_guest' => $data['portions_per_guest'] ?? '1',
                'sort_order' => (int) ($menu->items()->max('sort_order') ?? 0) + 10,
            ],
        );
        $menu->audit('recipe.menu_item_added', ['template_id' => $template->id]);

        return redirect()->route('recipe-menus.show', $menu)->with('success', __('recipes.flash.menu_saved'));
    }

    public function destroyItem(RecipeMenu $menu, RecipeMenuItem $item): RedirectResponse {
        Gate::authorize('create', ProcedureTemplate::class);
        $organization = $this->partyOrganization();
        abort_unless($menu->organization_id === $organization->id, 404);
        abort_unless($item->recipe_menu_id === $menu->id, 404);

        $item->delete();
        $menu->audit('recipe.menu_item_removed', ['template_id' => $item->procedure_template_id]);

        return redirect()->route('recipe-menus.show', $menu)->with('success', __('recipes.flash.menu_saved'));
    }

    private function partyOrganization(): Organization {
        /** @var User $user */
        $user = Auth::user();
        $organization = $user->organization;
        abort_unless($organization instanceof Organization, 404);
        abort_unless($this->recipes->isPartyserviceActive($organization), 404);

        return $organization;
    }
}
