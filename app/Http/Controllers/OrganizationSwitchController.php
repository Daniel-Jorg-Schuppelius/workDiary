<?php
/*
 * Created on   : Wed May 20 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : OrganizationSwitchController.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Http\Controllers;

use App\Models\Organization;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

/**
 * Erlaubt globalen Admins, den aktiven Organisations-Kontext per Session
 * zu wechseln, ohne ihre eigene users.organization_id zu verändern.
 *
 * Reguläre Benutzer haben keinen Zugriff: Ihr Org-Kontext ergibt sich
 * ausschließlich aus users.organization_id.
 */
class OrganizationSwitchController extends Controller {
    public const SESSION_KEY = 'active_organization_id';

    public function update(Request $request): RedirectResponse {
        /** @var User|null $user */
        $user = Auth::user();

        abort_unless($user instanceof User && $user->isAdmin(), 403);

        $data = $request->validate([
            'organization_id' => ['nullable', 'integer', 'exists:organizations,id'],
        ]);

        $orgId = $data['organization_id'] ?? null;

        if ($orgId === null) {
            $request->session()->forget(self::SESSION_KEY);

            return back()->with('success', __('Organisations-Kontext aufgehoben.'));
        }

        /** @var Organization $org */
        $org = Organization::query()->findOrFail($orgId);

        // Deaktivierte Organisationen dürfen nicht als Kontext gewählt werden.
        // Sie müssen erst über die Verwaltung wieder aktiviert werden.
        if (! $org->is_active) {
            return back()->withErrors([
                'organization_id' => __('Diese Organisation ist deaktiviert und kann nicht ausgewählt werden.'),
            ]);
        }

        $request->session()->put(self::SESSION_KEY, $org->id);

        return back()->with('success', __('Aktive Organisation: :name', ['name' => $org->name]));
    }
}
