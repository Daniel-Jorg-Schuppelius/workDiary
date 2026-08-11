<?php
/*
 * Created on   : Tue Jul 07 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : CostCenterRuleController.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\{CostCenter, CostCenterRule, Team, User};
use App\Rules\ExistsInCurrentOrganization;
use App\Services\SqidEncoder;
use Illuminate\Contracts\View\View;
use Illuminate\Http\{RedirectResponse, Request};
use Illuminate\Support\Facades\Gate;

/**
 * Admin-UI für Kostenstellen-Regeln des Zeitexports (Rang 35): Listenseite +
 * Modal-CRUD analog admin/surcharge-rules. Genau eine Quelle je Regel —
 * Benutzer ODER Team; beide leer = Org-Default. Pflege durch Admin und
 * Buchhaltung/Lohnbüro (costCenterRule.manage).
 */
class CostCenterRuleController extends Controller {
    public function index(): View {
        Gate::authorize('viewAny', CostCenterRule::class);

        $rules = CostCenterRule::query()
            ->with(['user:id,name', 'team:id,name', 'costCenter:id,code,label'])
            ->orderByDesc('priority')
            ->orderBy('id')
            ->get();

        return view('admin.cost-center-rules.index', [
            'rules' => $rules,
            'canManage' => Gate::allows('create', CostCenterRule::class),
        ]);
    }

    public function create(): View {
        Gate::authorize('create', CostCenterRule::class);

        return view('admin.cost-center-rules._form_dialog', [
            'rule' => new CostCenterRule(['priority' => 0]),
            'users' => $this->userOptions(),
            'teams' => $this->teamOptions(),
            'costCenters' => $this->costCenterOptions(),
        ]);
    }

    public function store(Request $request): RedirectResponse {
        Gate::authorize('create', CostCenterRule::class);

        CostCenterRule::query()->create($this->validated($request));

        return redirect()->route('admin.cost-center-rules.index')
            ->with('success', __('costcenter.flash.created'));
    }

    public function edit(CostCenterRule $costCenterRule): View {
        Gate::authorize('update', $costCenterRule);

        return view('admin.cost-center-rules._form_dialog', [
            'rule' => $costCenterRule,
            'users' => $this->userOptions(),
            'teams' => $this->teamOptions(),
            'costCenters' => $this->costCenterOptions(),
        ]);
    }

    public function update(Request $request, CostCenterRule $costCenterRule): RedirectResponse {
        Gate::authorize('update', $costCenterRule);

        $costCenterRule->update($this->validated($request));

        return redirect()->route('admin.cost-center-rules.index')
            ->with('success', __('costcenter.flash.updated'));
    }

    public function destroy(CostCenterRule $costCenterRule): RedirectResponse {
        Gate::authorize('delete', $costCenterRule);

        $costCenterRule->delete();

        return redirect()->route('admin.cost-center-rules.index')
            ->with('success', __('costcenter.flash.deleted'));
    }

    /** @return array<string, mixed> */
    private function validated(Request $request): array {
        $encoder = app(SqidEncoder::class);

        $request->merge([
            'user_id' => $request->filled('user_id') ? $encoder->decode(User::class, (string) $request->input('user_id')) : null,
            'team_id' => $request->filled('team_id') ? $encoder->decode(Team::class, (string) $request->input('team_id')) : null,
            'cost_center_id' => $request->filled('cost_center_id') ? $encoder->decode(CostCenter::class, (string) $request->input('cost_center_id')) : null,
        ]);

        $data = $request->validate([
            'source' => ['required', 'in:default,user,team'],
            'user_id' => ['nullable', 'integer', 'required_if:source,user', new ExistsInCurrentOrganization('users')],
            'team_id' => ['nullable', 'integer', 'required_if:source,team', new ExistsInCurrentOrganization('teams')],
            'cost_center_id' => ['nullable', 'integer', new ExistsInCurrentOrganization('cost_centers')],
            'cost_center' => ['nullable', 'string', 'max:32', 'required_without:cost_center_id'],
            'priority' => ['required', 'integer', 'min:0', 'max:1000'],
        ]);

        // Quelle bestimmt exklusiv die gesetzte Spalte.
        $source = (string) $data['source'];
        $data['user_id'] = $source === 'user' ? $data['user_id'] : null;
        $data['team_id'] = $source === 'team' ? $data['team_id'] : null;
        unset($data['source']);

        // Stammdaten-Auswahl führt: Code-Snapshot aus dem Stammsatz übernehmen
        // (Freitext gilt nur ohne Auswahl — Fallback-Muster wie Investments).
        if ($data['cost_center_id'] !== null) {
            /** @var CostCenter $costCenter */
            $costCenter = CostCenter::query()->findOrFail($data['cost_center_id']);
            $data['cost_center'] = $costCenter->code;
        }

        return $data;
    }

    /** @return array<int, array{sqid: string, label: string}> */
    private function userOptions(): array {
        return User::inCurrentOrganization()
            ->orderBy('name')
            ->get(['id', 'name'])
            ->map(fn (User $u): array => ['sqid' => (string) $u->sqid, 'label' => (string) $u->name])
            ->values()
            ->all();
    }

    /** @return array<int, array{sqid: string, label: string}> */
    private function costCenterOptions(): array {
        return CostCenter::query()
            ->where('active', true)
            ->orderBy('code')
            ->get(['id', 'code', 'label'])
            ->map(fn (CostCenter $c): array => [
                'sqid' => (string) $c->sqid,
                'label' => $c->code . ($c->label !== $c->code ? ' — ' . $c->label : ''),
            ])
            ->values()
            ->all();
    }

    /** @return array<int, array{sqid: string, label: string}> */
    private function teamOptions(): array {
        return Team::query()
            ->whereNull('archived_at')
            ->orderBy('name')
            ->get(['id', 'name'])
            ->map(fn (Team $t): array => ['sqid' => (string) $t->sqid, 'label' => (string) $t->name])
            ->values()
            ->all();
    }
}
