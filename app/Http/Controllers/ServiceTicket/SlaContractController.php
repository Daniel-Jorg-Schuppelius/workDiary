<?php
/*
 * Created on   : Mon Jul 06 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : SlaContractController.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Http\Controllers\ServiceTicket;

use App\Enums\User\Permission;
use App\Http\Controllers\Concerns\ResolvesCurrentOrganization;
use App\Http\Controllers\Controller;
use App\Models\Customer\Customer;
use App\Models\Platform\{Team, User};
use App\Models\Project\Project;
use App\Models\ServiceTicket\SlaContract;
use App\Services\ServiceTicket\SlaQuotaService;
use App\Support\SortableQuery;
use CommonToolkit\Helper\Data\NumberHelper;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Spatie\Permission\Models\Role;

/**
 * SLA-Vertrags-Detailseite (Feature 010): read-only Übersicht und Detail der
 * SLA-Verträge einer Organisation. Trägerseite für die Anzeige-Reste der
 * Feature-010-Restpunkte — Inklusivzeit-Kontingente (Rang 44) und
 * vertragspflichtige Wartungstermine (Rang 43).
 *
 * Verträge kommen aus den Branchenprofilen oder werden hier gepflegt: Anlegen
 * und Ändern laufen über `store()`/`update()` (Recht `slaContract.manage`),
 * Ansehen über `slaContract.view`. Der frühere Docblock nannte die Seite
 * read-only und das Bearbeiten eine separate Ausbaustufe — überholt,
 * nachgeführt am 2026-09-16 (MVP-796, Befund C2-12).
 */
class SlaContractController extends Controller {
    use ResolvesCurrentOrganization;
    public function index(Request $request): View {
        $this->authorizeView($request);

        // Standard wie bisher: der Standardvertrag zuerst, dann nach Code.
        [$sort, $dir] = SortableQuery::resolve($request, ['code', 'label', 'customer', 'project', 'quotas', 'status'], 'status', 'desc');
        $query = SlaContract::query()
            ->with(['customer:id,name', 'project:id,name'])
            ->withCount('quotas');
        match ($sort) {
            'customer' => $query->orderBy(Customer::query()->select('name')->whereColumn('customers.id', 'sla_contracts.customer_id'), $dir),
            'project' => $query->orderBy(Project::query()->select('name')->whereColumn('projects.id', 'sla_contracts.project_id'), $dir),
            'quotas' => $query->orderBy('quotas_count', $dir),
            'status' => $query->orderBy('is_default', $dir)->orderBy('is_active', $dir),
            default => $query->orderBy($sort, $dir),
        };
        $contracts = $query->orderBy('code')->orderBy('id')->paginate(25)->withQueryString();

        $canManage = $request->user()?->can(\App\Enums\User\Permission::SlaContractManage->value) ?? false;

        return view('sla-contracts.index', [
            'contracts' => $contracts,
            'sort' => $sort,
            'dir' => $dir,
            'canManage' => $canManage,
            // Projekt-Auswahl fürs Admin-Formular (W5.4), org-gescopt via Global Scope.
            'projects' => $canManage
                ? Project::query()->orderBy('name')->get(['id', 'name', 'customer_id', 'foreign_customer_id'])
                : collect(),
            ...($canManage ? $this->formOptions() : ['teams' => collect(), 'orgUsers' => collect(), 'roleNames' => []]),
        ]);
    }

    /**
     * SLA-Vertrags-CRUD (Feature 065, P3 — bewusst das BESTEHENDE Recht
     * slaContract.manage, keine zweite Mechanik). priority_table und
     * business_hours als JSON (Admin-Werkzeug), pause_rules als Checkboxen,
     * OLA-Kennzeichnung (internes Ziel je Team).
     */
    public function store(Request $request): \Illuminate\Http\RedirectResponse {
        \Illuminate\Support\Facades\Gate::authorize(\App\Enums\User\Permission::SlaContractManage->value);

        $data = $this->validatedContract($request);
        $data['organization_id'] = (int) $this->currentOrganization()->id;
        $contract = SlaContract::query()->create($data);
        $this->ensureSingleDefault($contract);

        return redirect()->toList('sla-contracts.index')->with('success', __('SLA-Vertrag angelegt.'));
    }

    /** Bearbeiten im Dialog der Detailseite (Vollscan 2026-10-21). */
    public function edit(SlaContract $slaContract): View {
        \Illuminate\Support\Facades\Gate::authorize(\App\Enums\User\Permission::SlaContractManage->value);

        return view('sla-contracts._form_dialog', [
            'contract' => $slaContract->loadMissing('project'),
            'projects' => Project::query()->orderBy('name')->get(['id', 'name', 'customer_id', 'foreign_customer_id']),
            ...$this->formOptions(),
        ]);
    }

    /**
     * Auswahl für OLA-Team und Empfänger der Eskalationsstufen.
     *
     * @return array{teams: \Illuminate\Support\Collection<int, Team>, orgUsers: \Illuminate\Support\Collection<int, User>, roleNames: list<string>}
     */
    private function formOptions(): array {
        $organizationId = (int) $this->currentOrganization()->id;

        return [
            'teams' => Team::query()->orderBy('name')->get(['id', 'name']),
            'orgUsers' => User::query()->where('organization_id', $organizationId)->whereNull('deactivated_at')->orderBy('name')->get(['id', 'name']),
            'roleNames' => array_values(Role::query()
                ->where((string) config('permission.column_names.team_foreign_key', 'team_id'), $organizationId)
                ->where('guard_name', 'web')
                ->orderBy('name')
                ->pluck('name')
                ->all()),
        ];
    }

    public function update(Request $request, SlaContract $slaContract): \Illuminate\Http\RedirectResponse {
        \Illuminate\Support\Facades\Gate::authorize(\App\Enums\User\Permission::SlaContractManage->value);

        $slaContract->update($this->validatedContract($request, $slaContract));
        $this->ensureSingleDefault($slaContract);

        return redirect()->route('sla-contracts.show', $slaContract)->with('success', __('SLA-Vertrag gespeichert.'));
    }

    /** @return array<string, mixed> */
    private function validatedContract(Request $request, ?SlaContract $contract = null): array {
        // Formular sendet Sqids (x-project-select); numerische IDs bleiben für Alt-Aufrufer
        // gültig. Dekodierte Kopie validieren statt mergen, damit old('project_id')
        // bei Fehlern der Sqid bleibt (vgl. DecodesSqidInputs).
        $input = $request->all();
        $input['project_id'] = \App\Support\Sqid::decodeOrNumeric(\App\Models\Project\Project::class, $request->input('project_id'));
        $input['ola_team_id'] = \App\Support\Sqid::decodeOrNumeric(Team::class, $request->input('ola_team_id'));
        $data = \Illuminate\Support\Facades\Validator::make($input, [
            'code' => ['required', 'string', 'min:2', 'max:60'],
            'label' => ['required', 'string', 'min:2', 'max:180'],
            'customer_id' => ['nullable', new \App\Rules\ExistsInCurrentOrganization('customers')],
            'project_id' => ['nullable', new \App\Rules\ExistsInCurrentOrganization('projects')],
            'priority_table' => ['required', 'json'],
            'business_hours' => ['nullable', 'json'],
            'pause_rules' => ['nullable', 'array'],
            'pause_rules.*' => ['in:waiting_customer,waiting_external,paused'],
            'is_active' => ['nullable', 'boolean'],
            'is_default' => ['nullable', 'boolean'],
            'is_ola' => ['nullable', 'boolean'],
            'ola_team_id' => ['nullable', 'required_if_accepted:is_ola', new \App\Rules\ExistsInCurrentOrganization('teams')],
            'escalation' => ['nullable', 'array', 'max:10'],
            'escalation.*.after_minutes' => ['nullable', 'integer', 'min:1', 'max:525600'],
            'escalation.*.notify' => ['nullable', 'string', 'max:140'],
        ], [
            'ola_team_id.required_if_accepted' => __('sla.form.olaTeamRequired'),
        ])->validate();
        // Ein OLA ist ein internes Ziel: nie Standardvertrag und nie an Kunde oder Projekt gebunden.
        $isOla = (bool) ($data['is_ola'] ?? false);
        if ($isOla && (($data['is_default'] ?? false) || ($data['customer_id'] ?? null) !== null || ($data['project_id'] ?? '') !== '')) {
            throw \Illuminate\Validation\ValidationException::withMessages(['is_ola' => __('sla.form.olaNotCustomer')]);
        }

        return [
            'code' => $data['code'],
            'label' => $data['label'],
            'customer_id' => $data['customer_id'] ?? null,
            // Optionale Projektbindung (W5.4): leer = kunden-/org-weiter Vertrag.
            'project_id' => ($data['project_id'] ?? '') !== '' ? (int) $data['project_id'] : null,
            'priority_table' => json_decode((string) $data['priority_table'], true),
            'business_hours' => ($data['business_hours'] ?? '') !== ''
                ? json_decode((string) $data['business_hours'], true)
                : null,
            'pause_rules' => array_values((array) ($data['pause_rules'] ?? [])),
            'is_active' => (bool) ($data['is_active'] ?? true),
            'is_default' => (bool) ($data['is_default'] ?? false),
            'is_ola' => (bool) ($data['is_ola'] ?? false),
            'ola_team_id' => $isOla ? ($data['ola_team_id'] ?? null) : null,
            // Ohne Eskalationsfelder im Request (Alt-Aufrufer) bleibt die Kette des Vertrags.
            'escalation_chain' => array_key_exists('escalation', $data)
                ? $this->escalationChain((array) $data['escalation'])
                : $contract?->escalation_chain,
        ];
    }

    /**
     * Stufen in der Form, die der SlaTicketScan liest: `after_minutes` und
     * `notify` (leer = Bearbeiter des Tickets, Rollenname oder Nutzer-ID).
     *
     * @param  array<int|string, mixed>  $rows
     * @return list<array{after_minutes: int, notify: string}>|null
     */
    private function escalationChain(array $rows): ?array {
        $organizationId = (int) $this->currentOrganization()->id;
        $chain = [];
        foreach ($rows as $index => $row) {
            $minutes = NumberHelper::toIntOrNull(is_array($row) ? ($row['after_minutes'] ?? null) : null);
            if ($minutes === null) {
                continue;
            }
            $target = (string) (is_array($row) ? ($row['notify'] ?? '') : '');
            $notify = '';
            if (str_starts_with($target, 'user:')) {
                $userId = \App\Support\Sqid::decode(User::class, substr($target, 5));
                if ($userId === null || ! User::query()->whereKey($userId)->where('organization_id', $organizationId)->exists()) {
                    throw \Illuminate\Validation\ValidationException::withMessages(["escalation.{$index}.notify" => __('sla.form.escalationTargetUnknown')]);
                }
                $notify = (string) $userId;
            } elseif (str_starts_with($target, 'role:')) {
                $notify = substr($target, 5);
                if (\App\Services\Org\OrganizationRoles::matching([$notify], $organizationId)->isEmpty()) {
                    throw \Illuminate\Validation\ValidationException::withMessages(["escalation.{$index}.notify" => __('sla.form.escalationTargetUnknown')]);
                }
            }
            $chain[] = ['after_minutes' => $minutes, 'notify' => $notify];
        }
        usort($chain, static fn (array $a, array $b): int => $a['after_minutes'] <=> $b['after_minutes']);

        return $chain !== [] ? $chain : null;
    }

    /** Genau ein Default-Vertrag je Org (ohne Kundenbezug). */
    private function ensureSingleDefault(SlaContract $contract): void {
        if (! $contract->is_default) {
            return;
        }
        SlaContract::query()
            ->whereKeyNot($contract->id)
            ->where('is_default', true)
            ->update(['is_default' => false]);
    }

    public function show(Request $request, SlaContract $slaContract, SlaQuotaService $quotas): View {
        $user = $this->authorizeView($request);
        // Mandantengrenze (der Sqid-Bind greift zwar org-gescopt, hier zusätzlich hart).
        abort_unless((int) $slaContract->organization_id === (int) $user->organization_id, 404);

        $quotaUsage = $slaContract->quotas
            ->map(fn ($quota): array => ['quota' => $quota, 'usage' => $quotas->usage($slaContract, $quota)])
            ->all();

        $maintenancePlans = $slaContract->maintenancePlans()
            ->with('asset:id,name,asset_no')
            ->orderBy('next_due_on')
            ->get();

        $chain = array_values((array) ($slaContract->escalation_chain ?? []));
        $userNames = User::query()
            ->where('organization_id', $slaContract->organization_id)
            ->whereIn('id', array_filter(array_map(static fn (array $step): ?int => ctype_digit($step['notify']) ? (int) $step['notify'] : null, $chain)))
            ->pluck('name', 'id');

        return view('sla-contracts.show', [
            'escalationTargets' => array_map(static fn (array $step): string => match (true) {
                $step['notify'] === '' => (string) __('sla.form.escalationAssignee'),
                ctype_digit($step['notify']) => (string) ($userNames[(int) $step['notify']] ?? __('sla.form.escalationTargetGone')),
                default => \App\Support\Trans::or('user.role.' . $step['notify'], $step['notify']),
            }, $chain),
            'olaTeam' => $slaContract->is_ola && $slaContract->ola_team_id !== null ? Team::query()->find($slaContract->ola_team_id, ['id', 'name']) : null,
            'contract' => $slaContract,
            'quotaUsage' => $quotaUsage,
            'maintenancePlans' => $maintenancePlans,
        ]);
    }

    private function authorizeView(Request $request): User {
        $user = $request->user();
        abort_unless($user instanceof User && $user->can(Permission::SlaContractView->value), 403);

        return $user;
    }
}
