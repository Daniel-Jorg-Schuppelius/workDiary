<?php
/*
 * Created on   : Mon Jul 06 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : SlaContractController.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Http\Controllers;

use App\Enums\User\Permission;
use App\Models\{SlaContract, User};
use App\Services\ServiceTicket\SlaQuotaService;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * SLA-Vertrags-Detailseite (Feature 010): read-only Übersicht und Detail der
 * SLA-Verträge einer Organisation. Trägerseite für die Anzeige-Reste der
 * Feature-010-Restpunkte — Inklusivzeit-Kontingente (Rang 44) und
 * vertragspflichtige Wartungstermine (Rang 43).
 *
 * Verträge werden heute über Branchenprofile installiert; diese Seite zeigt sie
 * nur an (Recht `slaContract.view`). Bearbeiten ist eine separate Ausbaustufe
 * (`slaContract.manage`).
 */
class SlaContractController extends Controller {
    public function index(Request $request): View {
        $this->authorizeView($request);

        $contracts = SlaContract::query()
            ->with('customer:id,name')
            ->withCount('quotas')
            ->orderByDesc('is_default')
            ->orderBy('code')
            ->get();

        return view('sla-contracts.index', [
            'contracts' => $contracts,
            'canManage' => $request->user()?->can(\App\Enums\User\Permission::SlaContractManage->value) ?? false,
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
        $data['organization_id'] = (int) $request->user()?->organization_id;
        $contract = SlaContract::query()->create($data);
        $this->ensureSingleDefault($contract);

        return redirect()->route('sla-contracts.index')->with('success', __('SLA-Vertrag angelegt.'));
    }

    public function update(Request $request, SlaContract $slaContract): \Illuminate\Http\RedirectResponse {
        \Illuminate\Support\Facades\Gate::authorize(\App\Enums\User\Permission::SlaContractManage->value);

        $slaContract->update($this->validatedContract($request, $slaContract));
        $this->ensureSingleDefault($slaContract);

        return redirect()->route('sla-contracts.index')->with('success', __('SLA-Vertrag gespeichert.'));
    }

    /** @return array<string, mixed> */
    private function validatedContract(Request $request, ?SlaContract $contract = null): array {
        $data = $request->validate([
            'code' => ['required', 'string', 'min:2', 'max:60'],
            'label' => ['required', 'string', 'min:2', 'max:180'],
            'customer_id' => ['nullable', new \App\Rules\ExistsInCurrentOrganization('customers')],
            'priority_table' => ['required', 'json'],
            'business_hours' => ['nullable', 'json'],
            'pause_rules' => ['nullable', 'array'],
            'pause_rules.*' => ['in:waiting_customer,waiting_external,paused'],
            'is_active' => ['nullable', 'boolean'],
            'is_default' => ['nullable', 'boolean'],
            'is_ola' => ['nullable', 'boolean'],
            'ola_team_id' => ['nullable', new \App\Rules\ExistsInCurrentOrganization('teams')],
        ]);

        return [
            'code' => $data['code'],
            'label' => $data['label'],
            'customer_id' => $data['customer_id'] ?? null,
            'priority_table' => json_decode((string) $data['priority_table'], true),
            'business_hours' => ($data['business_hours'] ?? '') !== ''
                ? json_decode((string) $data['business_hours'], true)
                : null,
            'pause_rules' => array_values((array) ($data['pause_rules'] ?? [])),
            'is_active' => (bool) ($data['is_active'] ?? true),
            'is_default' => (bool) ($data['is_default'] ?? false),
            'is_ola' => (bool) ($data['is_ola'] ?? false),
            'ola_team_id' => ($data['is_ola'] ?? false) ? ($data['ola_team_id'] ?? null) : null,
        ];
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

        return view('sla-contracts.show', [
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
