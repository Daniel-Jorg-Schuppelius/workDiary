<?php
/*
 * Created on   : Wed Jul 15 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : NavigationCustomizationController.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Http\Controllers\Me;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\Navigation\NavigationRegistry;
use Illuminate\Http\{RedirectResponse, Request};
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

/**
 * Per-User-Menüanpassung (Feature 081): Sidebar-Sektionen/-Gruppen/-Einträge und
 * Schnellerstellungs-Gruppen ausblenden. Persistenz in users.preferences
 * (nav_hidden), gegen die Registry-Whitelist validiert. Rein kosmetisch —
 * Gates, Policies, Suche, Deep-Links unberührt (D13).
 */
class NavigationCustomizationController extends Controller {
    public function __construct(private readonly NavigationRegistry $registry) {}

    public function index(): View {
        /** @var User $user */
        $user = Auth::user();

        $hidden = $this->registry->hiddenNavKeys($user);

        return view('me.navigation-customize', [
            'sections' => $this->customizableSections(),
            'createGroups' => $this->customizableCreateGroups(),
            'hidden' => $hidden,
        ]);
    }

    public function save(Request $request): RedirectResponse {
        /** @var User $user */
        $user = Auth::user();

        $payload = $request->validate([
            'visible' => ['nullable', 'array'],
            'visible.*' => ['string', 'max:160'],
        ]);

        // Schalter EIN = sichtbar. Ausgeblendet wird alles aus der Whitelist, das
        // NICHT eingeschaltet ist; die Seite startet mit allem an → normales
        // Speichern blendet nichts aus.
        $allowed = $this->allowedKeys();
        $visible = array_intersect(
            array_map(static fn($v): string => (string) $v, $payload['visible'] ?? []),
            $allowed
        );
        /** @var list<string> $hidden */
        $hidden = array_values(array_diff($allowed, $visible));

        $user->setPreference(NavigationRegistry::PREFERENCE_HIDDEN, $hidden);

        return redirect()->route('me.navigation.customize')
            ->with('status', __('scope.customize.saved'));
    }

    /** Einzelnen Schlüssel wieder einblenden (Funktionskatalog, MVP-375). */
    public function unhide(Request $request): RedirectResponse {
        /** @var User $user */
        $user = Auth::user();

        $data = $request->validate([
            'key' => ['required', 'string', 'max:160'],
        ]);

        $hidden = array_values(array_filter(
            $this->registry->hiddenNavKeys($user),
            static fn(string $key): bool => $key !== (string) $data['key']
        ));
        $user->setPreference(NavigationRegistry::PREFERENCE_HIDDEN, $hidden);

        return back()->with('status', __('scope.customize.unhidden'));
    }

    /**
     * Sidebar-Struktur, die der Nutzer anpassen darf: Modul-/Rechte-Filter an,
     * Per-User-Ausblendungen bewusst NICHT (Ausgeblendetes bleibt rücknehmbar).
     *
     * @return list<array<string, mixed>>
     */
    private function customizableSections(): array {
        return $this->registry->filterSidebar($this->registry->sidebarBlueprint('duties.index'), []);
    }

    /** @return list<array<string, mixed>> */
    private function customizableCreateGroups(): array {
        return $this->registry->filterCreateGroups($this->registry->createGroupsBlueprint(), []);
    }

    /**
     * Whitelist aller ausblendbaren Schlüssel (nur was der Nutzer sehen darf).
     *
     * @return list<string>
     */
    private function allowedKeys(): array {
        $keys = [];
        foreach ($this->customizableSections() as $section) {
            $keys[] = NavigationRegistry::KEY_SECTION . (string) $section['key'];
            foreach ((array) ($section['items'] ?? []) as $item) {
                if (is_array($item)) {
                    $keys[] = NavigationRegistry::KEY_ITEM . (string) $item['route'];
                }
            }
            foreach ((array) ($section['groups'] ?? []) as $group) {
                if (! is_array($group)) {
                    continue;
                }
                $keys[] = NavigationRegistry::KEY_GROUP . (string) $group['key'];
                foreach ((array) ($group['items'] ?? []) as $item) {
                    if (is_array($item)) {
                        $keys[] = NavigationRegistry::KEY_ITEM . (string) $item['route'];
                    }
                }
            }
        }
        foreach ($this->customizableCreateGroups() as $group) {
            $keys[] = NavigationRegistry::KEY_CREATE . (string) $group['key'];
        }

        return array_values(array_unique($keys));
    }
}
