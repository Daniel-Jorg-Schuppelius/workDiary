<?php
/*
 * Created on   : Wed Jul 15 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : FocusController.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Http\Controllers\Me;

use App\Http\Controllers\Controller;
use App\Models\{Organization, User};
use App\Services\Navigation\NavFocusService;
use Illuminate\Http\{RedirectResponse, Request};

/**
 * Arbeitsbereich wechseln (Feature 082, MVP-378): per Nutzer umschaltbarer
 * Fokus über die Navigation. Rein kosmetisch (D13) — es werden keine Rechte,
 * Module oder Daten berührt; nur die sichtbare Menüauswahl ändert sich.
 * Persistenz in Session (Spiegel) und users.preferences (überlebt Login/F5).
 */
class FocusController extends Controller {
    public function __construct(private readonly NavFocusService $focus) {}

    public function switch(Request $request, string $focus): RedirectResponse {
        /** @var User|null $user */
        $user = $request->user();
        $organization = $this->organization($user);

        if (! $this->focus->isAvailableFor($organization, $focus)) {
            return back()->with('error', __('scope.focus.flash.unknown'));
        }

        $request->session()->put(NavFocusService::SESSION_KEY, $focus);

        if ($user instanceof User && $user->getPreference(NavFocusService::PREFERENCE_KEY) !== $focus) {
            $user->setPreference(NavFocusService::PREFERENCE_KEY, $focus);
        }

        return back()->with('mode_toast', __('scope.focus.flash.switched', [
            'name' => $this->focus->label($organization, $focus),
        ]));
    }

    private function organization(?User $user): ?Organization {
        if (app()->bound('currentOrganization')) {
            $current = app('currentOrganization');
            if ($current instanceof Organization) {
                return $current;
            }
        }

        return $user?->organization;
    }
}
