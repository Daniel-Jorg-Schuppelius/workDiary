<?php
/*
 * Created on   : Mon Jun 29 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : IntegrationMappingController.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\{ExternalReference, User};
use Illuminate\Http\{RedirectResponse, Request};
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

/**
 * Zuordnungs-Register (Baustein A der Integrations-Drehscheibe): zeigt alle
 * gespeicherten Fremd-ID-Bindungen ({@see ExternalReference}) und erlaubt das
 * Lösen einzelner Verknüpfungen.
 */
class IntegrationMappingController extends Controller {
    public function index(Request $request): View {
        $user = $this->authorizeBilling();

        $plugin = (string) $request->input('plugin', 'all');
        $type = (string) $request->input('type', 'all');

        $query = ExternalReference::query()
            ->where('organization_id', $user->organization_id)
            ->when($plugin !== 'all', fn($q) => $q->where('plugin_id', $plugin))
            ->when($type !== 'all', fn($q) => $q->where('external_type', $type))
            ->with('referenceable')
            ->orderBy('plugin_id')->orderBy('external_type')->orderByDesc('id');

        $base = ExternalReference::query()->where('organization_id', $user->organization_id);

        return view('admin.integration.mappings', [
            'references' => $query->paginate(50)->withQueryString(),
            'filters' => ['plugin' => $plugin, 'type' => $type],
            'plugins' => (clone $base)->distinct()->orderBy('plugin_id')->pluck('plugin_id')->all(),
            'types' => (clone $base)->distinct()->orderBy('external_type')->pluck('external_type')->all(),
        ]);
    }

    public function destroy(ExternalReference $reference): RedirectResponse {
        $user = $this->authorizeBilling();
        abort_unless($reference->organization_id === $user->organization_id, 404);

        $reference->delete();

        return back()->with('success', __('Verknüpfung gelöst.'));
    }

    private function authorizeBilling(): User {
        /** @var User $user */
        $user = Auth::user();
        abort_unless($user->canManageBilling(), 403);

        return $user;
    }
}
