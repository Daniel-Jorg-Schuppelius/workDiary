<?php
/*
 * Created on   : Wed Jul 08 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : DataOwnershipController.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Enums\Integration\DataDomain;
use App\Http\Controllers\Concerns\ResolvesCurrentOrganization;
use App\Http\Controllers\Controller;
use App\Plugins\PluginManager;
use App\Services\Integration\DataOwnershipResolver;
use Illuminate\Http\{RedirectResponse, Request};
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * Datenführerschaft-Matrix (Restpunkt 69): je Datenbereich genau ein
 * führendes System (native oder Plugin). Bei Plugin-Führung landen
 * Schreibversuche anderer Plugins als Inbox-Konflikt statt als Write.
 */
class DataOwnershipController extends Controller {
    use ResolvesCurrentOrganization;

    public function index(PluginManager $plugins, DataOwnershipResolver $resolver): View {
        Gate::authorize('update', $this->currentOrganizationOrAbort(404));

        $organization = $this->currentOrganizationOrAbort(404);

        return view('admin.data-ownership.index', [
            'organization' => $organization,
            'matrix' => $resolver->matrix($organization),
            'domains' => DataDomain::cases(),
            'plugins' => collect($plugins->enabled())->map(fn($plugin) => [
                'id' => $plugin->id(),
                'name' => $plugin->name(),
            ])->values(),
        ]);
    }

    public function update(Request $request, DataOwnershipResolver $resolver): RedirectResponse {
        Gate::authorize('update', $this->currentOrganizationOrAbort(404));

        $data = $request->validate([
            'domain' => ['required', Rule::enum(DataDomain::class)],
            'owner' => ['required', 'string', 'max:64'],
        ]);

        $organization = $this->currentOrganizationOrAbort(404);
        $domain = DataDomain::from($data['domain']);
        $resolver->setOwner($organization, $domain, $data['owner']);

        $organization->audit('integration.data_ownership_changed', [
            'domain' => $domain->value,
            'owner' => $resolver->ownerFor($organization->refresh(), $domain),
        ]);

        return redirect()->route('admin.data-ownership.index')
            ->with('success', __('Datenführerschaft aktualisiert.'));
    }
}
