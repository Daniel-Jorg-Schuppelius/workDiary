<?php
/*
 * Created on   : Wed Aug 26 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : UserWorkspaceController.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Http\Controllers\Me;

use App\Http\Controllers\Concerns\ResolvesCurrentOrganization;
use App\Http\Controllers\Controller;
use App\Http\Requests\SaveUserWorkspaceRequest;
use App\Models\{User, UserWorkspace};
use App\Services\Navigation\{NavFocusService, NavigationRegistry};
use Illuminate\Http\{RedirectResponse, Request};
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\Response;

/**
 * Eigene Arbeitsbereiche (Feature 082 Phase 2, MVP-731 — Vollscan G17).
 *
 * Persönliche Zusammenstellung von Menüpunkten, gleichberechtigt neben den
 * vordefinierten Fokus-Ansichten im Umschalter. Rein kosmetisch (D13): der
 * Editor bietet ausschließlich an, was die Person laut NavGate ohnehin sieht,
 * und die Auswahl wird beim Speichern **serverseitig** erneut dagegen geprüft.
 *
 * Kein Policy-Gate: Ein Arbeitsbereich gehört seiner Person. Die Grenze zieht
 * die Query (`forUser`) — ein fremder Sqid führt zu 404, nicht zu 403, weil
 * es den Datensatz für diese Person schlicht nicht gibt.
 */
class UserWorkspaceController extends Controller {
    // Beim Plattform-Admin-Switch weichen users.organization_id und die
    // gebundene Organisation voneinander ab — maßgeblich ist die gebundene.
    use ResolvesCurrentOrganization;

    public function __construct(
        private readonly NavigationRegistry $registry,
        private readonly NavFocusService $focus,
    ) {}

    public function index(): View {
        return view('me.workspaces.index', [
            'workspaces' => UserWorkspace::query()->forUser($this->user())->get(),
            'activeKey' => $this->focus->resolveActive(
                $this->user(),
                $this->currentOrganization(),
                session(NavFocusService::SESSION_KEY),
            ),
        ]);
    }

    public function create(): View {
        return $this->form(new UserWorkspace(['items' => [], 'sort' => 0]), false);
    }

    public function store(SaveUserWorkspaceRequest $request): RedirectResponse {
        $user = $this->user();

        UserWorkspace::create([
            'organization_id' => $this->currentOrganization()->id,
            'user_id' => $user->getKey(),
            'name' => $request->string('name')->toString(),
            'icon' => $request->input('icon') ?: null,
            'sort' => (int) ($request->input('sort') ?? 0),
            'items' => $this->items($request),
        ]);

        return redirect()->route('me.workspaces.index')->with('status', __('scope.workspace.flash.created'));
    }

    public function edit(UserWorkspace $workspace): View {
        return $this->form($this->own($workspace), true);
    }

    public function update(SaveUserWorkspaceRequest $request, UserWorkspace $workspace): RedirectResponse {
        $workspace = $this->own($workspace);

        $workspace->update([
            'name' => $request->string('name')->toString(),
            'icon' => $request->input('icon') ?: null,
            'sort' => (int) ($request->input('sort') ?? $workspace->sort),
            'items' => $this->items($request),
        ]);

        return redirect()->route('me.workspaces.index')->with('status', __('scope.workspace.flash.updated'));
    }

    public function destroy(Request $request, UserWorkspace $workspace): RedirectResponse {
        $workspace = $this->own($workspace);
        $key = NavFocusService::personalKey($workspace);
        $workspace->delete();

        // Wer gerade in diesem Arbeitsbereich stand, säße sonst in einem
        // Fokus, den es nicht mehr gibt.
        if (session(NavFocusService::SESSION_KEY) === $key) {
            $request->session()->forget(NavFocusService::SESSION_KEY);
        }
        if ($this->user()->getPreference(NavFocusService::PREFERENCE_KEY) === $key) {
            $this->user()->setPreference(NavFocusService::PREFERENCE_KEY, 'all');
        }

        return redirect()->route('me.workspaces.index')->with('status', __('scope.workspace.flash.deleted'));
    }

    private function form(UserWorkspace $workspace, bool $isEdit): View {
        return view('me.workspaces._form_dialog', [
            'workspace' => $workspace,
            'isEdit' => $isEdit,
            'catalog' => $this->catalog(),
            'selected' => $workspace->keys(),
        ]);
    }

    /**
     * Auswahlkatalog für den Editor: Sektionen mit Gruppen/Einträgen, gefiltert
     * auf das, was die Person sehen darf. Reihenfolge = Sidebar-Reihenfolge.
     *
     * @return list<array{key: string, label: string, icon: string, entries: list<array{key: string, label: string, icon: string, level: int}>}>
     */
    private function catalog(): array {
        $out = [];
        foreach ($this->registry->filterSidebar($this->registry->sidebarBlueprint('duties.index'), []) as $section) {
            $entries = [];
            foreach ((array) ($section['items'] ?? []) as $item) {
                if (is_array($item)) {
                    $entries[] = [
                        'key' => NavigationRegistry::KEY_ITEM . (string) $item['route'],
                        'label' => (string) ($item['label'] ?? $item['route']),
                        'icon' => (string) ($item['icon'] ?? 'circle'),
                        'level' => 1,
                    ];
                }
            }
            foreach ((array) ($section['groups'] ?? []) as $group) {
                if (! is_array($group)) {
                    continue;
                }
                $entries[] = [
                    'key' => NavigationRegistry::KEY_GROUP . (string) $group['key'],
                    'label' => (string) ($group['label'] ?? $group['key']),
                    'icon' => (string) ($group['icon'] ?? 'folder'),
                    'level' => 0,
                ];
                foreach ((array) ($group['items'] ?? []) as $item) {
                    if (is_array($item)) {
                        $entries[] = [
                            'key' => NavigationRegistry::KEY_ITEM . (string) $item['route'],
                            'label' => (string) ($item['label'] ?? $item['route']),
                            'icon' => (string) ($item['icon'] ?? 'circle'),
                            'level' => 1,
                        ];
                    }
                }
            }

            if ($entries !== []) {
                $out[] = [
                    'key' => NavigationRegistry::KEY_SECTION . (string) $section['key'],
                    'label' => (string) ($section['label'] ?? $section['key']),
                    'icon' => (string) ($section['icon'] ?? 'apps'),
                    'entries' => $entries,
                ];
            }
        }

        return $out;
    }

    /** @return list<string> */
    private function items(SaveUserWorkspaceRequest $request): array {
        /** @var list<string> $items */
        $items = array_values((array) $request->validated('items'));

        return $items;
    }

    /** Nur der eigene Arbeitsbereich — sonst gibt es ihn für diese Person nicht. */
    private function own(UserWorkspace $workspace): UserWorkspace {
        abort_if((int) $workspace->user_id !== (int) Auth::id(), Response::HTTP_NOT_FOUND);

        return $workspace;
    }

    private function user(): User {
        /** @var User $user */
        $user = Auth::user();

        return $user;
    }
}
