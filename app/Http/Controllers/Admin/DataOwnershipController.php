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
use App\Plugins\Contracts\WritesDataDomains;
use App\Plugins\PluginManager;
use App\Services\Integration\{DataOwnershipGuard, DataOwnershipResolver};
use Illuminate\Http\{RedirectResponse, Request};
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * Datenführerschaft-Matrix (Restpunkt 69, MVP-1592): je Datenbereich genau ein
 * führendes System. Zur Wahl stehen nur aktive Plugins, die den Bereich
 * schreiben ({@see WritesDataDomains}); Bestände folgen der Bestandsführung
 * und sind hier nur zu sehen. Kunden und Lieferanten setzen zugleich die
 * Stammdatenhoheit (Feature 122) — auch „Buchhaltung ohne festes Programm“.
 */
class DataOwnershipController extends Controller {
    use ResolvesCurrentOrganization;

    public function index(PluginManager $plugins, DataOwnershipResolver $resolver, DataOwnershipGuard $guard): View {
        $organization = $this->currentOrganizationOrAbort(404);
        Gate::authorize('update', $organization);

        $rows = [];
        foreach (DataDomain::cases() as $domain) {
            $owner = $resolver->ownerFor($organization, $domain);
            $writers = $this->writers($plugins, $domain);
            $rows[] = [
                'domain' => $domain,
                'owner' => $owner,
                'owner_name' => $owner === DataOwnershipResolver::NATIVE ? null : ($plugins->find($owner)?->name() ?? $owner),
                'writers' => $writers,
                'supported' => isset($this->fixedOwners($domain)[$owner]) || isset($writers[$owner]),
                'fixed' => $this->fixedOwners($domain),
                'locked' => $domain === DataDomain::Inventory,
                // Bestände stellt die Anbindung der Warenwirtschaft um (Übernahme-Inventur, Vorprüfung).
                'panels' => $domain === DataDomain::Inventory ? $this->panels($plugins, $domain) : [],
                'conflicts' => $guard->openConflicts($organization, $domain),
            ];
        }

        return view('admin.data-ownership.index', [
            'rows' => $rows,
            'inventoryMode' => $resolver->inventoryMode($organization),
        ]);
    }

    public function update(Request $request, PluginManager $plugins, DataOwnershipResolver $resolver, DataOwnershipGuard $guard): RedirectResponse {
        $organization = $this->currentOrganizationOrAbort(404);
        Gate::authorize('update', $organization);

        $data = $request->validate([
            'domain' => ['required', Rule::enum(DataDomain::class)],
            'owner' => ['required', 'string', 'max:64'],
        ]);
        $domain = DataDomain::from($data['domain']);
        if ($domain === DataDomain::Inventory) {
            return back()->with('error', __('integration.ownership.inventory_locked'));
        }
        $owner = trim((string) $data['owner']);
        if (! isset($this->fixedOwners($domain)[$owner]) && ! isset($this->writers($plugins, $domain)[$owner])) {
            return back()->with('error', __('integration.ownership.matrix.invalid_owner'));
        }

        $from = $resolver->ownerFor($organization, $domain);
        $resolver->setOwner($organization, $domain, $owner);
        $conflicts = $guard->openConflicts($organization, $domain);

        $organization->audit('integration.data_ownership_changed', [
            'domain' => $domain->value,
            'from' => $from,
            'owner' => $resolver->ownerFor($organization->refresh(), $domain),
            'open_conflicts' => $conflicts,
        ]);

        return redirect()->route('admin.data-ownership.index')->with('success', $conflicts > 0
            ? __('integration.ownership.matrix.changed_conflicts', ['count' => $conflicts])
            : __('integration.ownership.matrix.changed'));
    }

    /** @return array<string, string> Führung ohne Plugin-Wahl: Wert → Bezeichnung */
    private function fixedOwners(DataDomain $domain): array {
        $fixed = [DataOwnershipResolver::NATIVE => (string) __('integration.ownership.matrix.native')];
        if ($domain === DataDomain::Customers) {
            $fixed[DataOwnershipResolver::ACCOUNTING] = (string) __('integration.ownership.matrix.accounting_any');
        }

        return $fixed;
    }

    /** @return array<string, string> aktive Plugins, die den Bereich schreiben: ID → Name */
    private function writers(PluginManager $plugins, DataDomain $domain): array {
        return $plugins->enabled()
            ->filter(static fn ($plugin): bool => $plugin instanceof WritesDataDomains && in_array($domain, $plugin->dataDomains(), true))
            ->mapWithKeys(static fn ($plugin): array => [$plugin->id() => $plugin->name()])
            ->sort()
            ->all();
    }

    /** @return list<array{name: string, route: string}> Verwaltungsseiten der Plugins, die den Bereich führen können */
    private function panels(PluginManager $plugins, DataDomain $domain): array {
        $panels = [];
        foreach ($plugins->enabled() as $plugin) {
            $panel = $plugin->adminPanel();
            if ($plugin instanceof WritesDataDomains && in_array($domain, $plugin->dataDomains(), true) && is_string($panel['route'] ?? null)) {
                $panels[] = ['name' => $plugin->name(), 'route' => $panel['route']];
            }
        }

        return $panels;
    }
}
