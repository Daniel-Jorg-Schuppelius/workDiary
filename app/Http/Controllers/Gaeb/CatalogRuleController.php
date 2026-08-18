<?php
/*
 * Created on   : Tue Aug 18 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : CatalogRuleController.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Http\Controllers\Gaeb;

use App\Enums\User\Permission as P;
use App\Http\Controllers\Concerns\ResolvesCurrentOrganization;
use App\Http\Controllers\Controller;
use App\Models\Catalog\{CatalogAssignmentRule, CatalogEntry, CatalogRegistry};
use App\Models\User;
use App\Services\SqidEncoder;
use Illuminate\Contracts\View\View;
use Illuminate\Http\{RedirectResponse, Request};
use Illuminate\Support\Facades\{Auth, Gate};
use Illuminate\Validation\Rule;

/**
 * Vorschlagsregeln für Katalogzuordnungen (Feature 109, MVP-640).
 *
 * Die Regeln gehören der Organisation: Welche Leistung auf welche Kostengruppe
 * schlägt, ist eine betriebliche Festlegung, keine Norm.
 */
class CatalogRuleController extends Controller {
    use ResolvesCurrentOrganization;

    public function index(): View {
        Gate::authorize(P::ProjectViewAny->value);

        return view('gaeb.catalog-rules.index', [
            'rules' => CatalogAssignmentRule::query()
                ->with('registry')
                ->orderBy('priority')
                ->orderBy('id')
                ->get(),
            'registries' => $this->registries(),
            'canManage' => Gate::allows(P::ProjectUpdate->value),
        ]);
    }

    public function create(): View {
        Gate::authorize(P::ProjectUpdate->value);

        return view('gaeb.catalog-rules._form_dialog', [
            'rule' => new CatalogAssignmentRule(['active' => true, 'priority' => 100]),
            'registries' => $this->registries(),
        ]);
    }

    public function edit(CatalogAssignmentRule $rule): View {
        Gate::authorize(P::ProjectUpdate->value);
        $this->guard($rule);

        return view('gaeb.catalog-rules._form_dialog', [
            'rule' => $rule,
            'registries' => $this->registries(),
        ]);
    }

    public function store(Request $request): RedirectResponse {
        Gate::authorize(P::ProjectUpdate->value);

        $data = $this->validated($request);
        CatalogAssignmentRule::query()->create($data + [
            'organization_id' => $this->actor()->organization_id,
            'created_by' => $this->actor()->id,
        ]);

        return redirect()->route('catalog-rules.index')->with('success', __('Regel gespeichert.'));
    }

    public function update(Request $request, CatalogAssignmentRule $rule): RedirectResponse {
        Gate::authorize(P::ProjectUpdate->value);
        $this->guard($rule);

        $rule->update($this->validated($request));

        return redirect()->route('catalog-rules.index')->with('success', __('Regel gespeichert.'));
    }

    public function destroy(CatalogAssignmentRule $rule): RedirectResponse {
        Gate::authorize(P::ProjectUpdate->value);
        $this->guard($rule);

        $rule->delete();

        return redirect()->route('catalog-rules.index')->with('success', __('Regel gelöscht.'));
    }

    /**
     * @return array<string, mixed>
     */
    private function validated(Request $request): array {
        $registryId = app(SqidEncoder::class)->decode(CatalogRegistry::class, (string) $request->input('registry'));
        $request->merge(['catalog_registry_id' => $registryId]);

        $data = $request->validate([
            'match_type' => ['required', Rule::in(CatalogAssignmentRule::MATCH_TYPES)],
            'match_value' => ['required', 'string', 'max:200'],
            'catalog_registry_id' => ['required', 'integer'],
            'code' => ['required', 'string', 'max:40'],
            'priority' => ['nullable', 'integer', 'min:1', 'max:9999'],
            'active' => ['nullable', 'boolean'],
        ]);

        // Der Stamm muss sichtbar sein (ausgeliefert oder eigener), und der
        // Schlüssel muss darin stehen — eine Regel auf eine Nummer, die es
        // nicht gibt, schlüge stillschweigend nie an.
        $registry = CatalogRegistry::query()
            ->visibleFor($this->currentOrganization()->id)
            ->whereKey($data['catalog_registry_id'])
            ->first();
        abort_if($registry === null, 422);

        $known = CatalogEntry::query()
            ->where('catalog_registry_id', $registry->id)
            ->where('code', $data['code'])
            ->exists();
        abort_unless($known, 422, (string) __('Der Schlüssel :code steht nicht im Katalog :catalog.', [
            'code' => $data['code'],
            'catalog' => $registry->name,
        ]));

        return [
            'match_type' => $data['match_type'],
            'match_value' => $data['match_value'],
            'catalog_registry_id' => $registry->id,
            'code' => $data['code'],
            'priority' => $data['priority'] ?? 100,
            'active' => $request->boolean('active'),
        ];
    }

    /** @return \Illuminate\Support\Collection<int, CatalogRegistry> */
    private function registries(): \Illuminate\Support\Collection {
        return CatalogRegistry::query()
            ->visibleFor($this->currentOrganization()->id)
            ->where('active', true)
            ->orderBy('name')
            ->get();
    }

    private function guard(CatalogAssignmentRule $rule): void {
        abort_unless($rule->organization_id === $this->currentOrganization()->id, 404);
    }

    private function actor(): User {
        $user = Auth::user();
        abort_unless($user instanceof User, 403);

        return $user;
    }
}
