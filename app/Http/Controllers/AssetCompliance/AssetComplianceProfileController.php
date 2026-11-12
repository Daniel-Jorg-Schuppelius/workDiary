<?php
/*
 * Created on   : Fri Jul 10 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : AssetComplianceProfileController.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Http\Controllers\AssetCompliance;

use App\Enums\AssetCompliance\{AssetComplianceBlockMode, AssetInspectionKind};
use App\Http\Controllers\Controller;
use App\Models\Asset\Asset;
use App\Models\AssetCompliance\{AssetComplianceAssignment, AssetComplianceNormReference, AssetComplianceProfile, AssetComplianceRequirement};
use App\Models\Platform\User;
use App\Rules\ExistsInCurrentOrganization;
use App\Services\AssetCompliance\AssetComplianceService;
use App\Support\Sqid;
use Illuminate\Contracts\View\View;
use Illuminate\Http\{RedirectResponse, Request};
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

/**
 * Prüfprofile als Katalogdaten (MVP-283, P1: globale Vorlagen + Org-
 * Overrides per Code) und Prüfpflichten-Zuweisung (MVP-284). Die
 * Normen-Referenzmatrix (MVP-293) wird hier angezeigt — ohne
 * Konformitätszusage.
 */
class AssetComplianceProfileController extends Controller {
    use \App\Http\Controllers\Concerns\ResolvesCurrentOrganization;

    public function __construct(private readonly AssetComplianceService $service) {}

    public function index(Request $request): View {
        Gate::authorize('viewAny', AssetComplianceProfile::class);

        $organizationId = (int) ($request->user()->organization_id ?? 0);

        return view('asset-compliance.profiles', [
            'profiles' => $this->service->effectiveProfiles($organizationId)->load('requirements'),
            // Deaktivierte Organisationsprofile bleiben sichtbar, sonst ließen sie sich nicht wieder aktivieren.
            'inactiveProfiles' => AssetComplianceProfile::query()
                ->where('organization_id', $organizationId)
                ->where('is_active', false)
                ->orderBy('code')
                ->get(),
            'kinds' => AssetInspectionKind::cases(),
            'blockModes' => AssetComplianceBlockMode::cases(),
            'assets' => Asset::query()->inOperation()->orderBy('name')->get(['id', 'name']),
            'users' => User::inCurrentOrganization()->orderBy('name')->get(['id', 'name']),
            'externalContacts' => \App\Models\Contacts\ExternalContact::query()->orderBy('name')->limit(200)->get(['id', 'name']),
            'norms' => AssetComplianceNormReference::query()
                ->forOrganization($organizationId)
                ->orderBy('inspection_kind')
                ->get(),
        ]);
    }

    /** @return array<string, list<mixed>> */
    private function profileRules(): array {
        return [
            'name' => ['required', 'string', 'max:255'],
            'inspection_kind' => ['required', Rule::enum(AssetInspectionKind::class)],
            'interval_months' => ['required', 'integer', 'min:1', 'max:240'],
            'warn_days_before' => ['nullable', 'integer', 'min:0', 'max:365'],
            'tolerance_days' => ['nullable', 'integer', 'min:0', 'max:365'],
            'grace_days' => ['nullable', 'integer', 'min:0', 'max:365'],
            'blocking_mode' => ['required', Rule::enum(AssetComplianceBlockMode::class)],
            'requires_certificate' => ['sometimes', 'boolean'],
            'default_authority' => ['nullable', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:4000'],
        ];
    }

    /** @return array<string, list<mixed>> */
    private function requirementRules(): array {
        return [
            'label' => ['required', 'string', 'max:255'],
            'code' => ['nullable', 'string', 'max:60'],
            'unit' => ['nullable', 'string', 'max:30'],
            'limit_min' => ['nullable', 'numeric', 'between:-9999999999.9999,9999999999.9999'],
            'limit_max' => ['nullable', 'numeric', 'between:-9999999999.9999,9999999999.9999'],
            'is_mandatory' => ['sometimes', 'boolean'],
        ];
    }

    /** Org-Profil anlegen (überschreibt globale Vorlage gleichen Codes, P1). */
    public function store(Request $request): RedirectResponse {
        Gate::authorize('create', AssetComplianceProfile::class);

        $data = $request->validate(['code' => ['required', 'string', 'max:60']] + $this->profileRules());

        $organizationId = (int) ($request->user()->organization_id ?? abort(403));

        $exists = AssetComplianceProfile::query()
            ->where('organization_id', $organizationId)
            ->where('code', $data['code'])
            ->exists();
        if ($exists) {
            return back()->withErrors(['code' => __('Für diesen Code existiert bereits ein Organisationsprofil.')]);
        }

        AssetComplianceProfile::query()->create(array_merge($data, [
            'organization_id' => $organizationId,
            'warn_days_before' => (int) ($data['warn_days_before'] ?? 30),
            'tolerance_days' => (int) ($data['tolerance_days'] ?? 0),
            'grace_days' => (int) ($data['grace_days'] ?? 0),
            'is_active' => true,
        ]));

        return back()->with('status', __('Prüfprofil angelegt.'));
    }

    /** Messbare Anforderung mit Grenzwerten ergänzen (MVP-283). */
    public function storeRequirement(Request $request, AssetComplianceProfile $profile): RedirectResponse {
        Gate::authorize('update', $profile);

        // Globale Vorlagen sind read-only — Overrides laufen über Org-Profile.
        if ($profile->organization_id === null) {
            return back()->withErrors(['profile' => __('Globale Vorlagen sind unveränderlich — Org-Profil mit gleichem Code anlegen.')]);
        }

        $this->ensureOwnProfile($profile);

        $data = $request->validate($this->requirementRules());

        $profile->requirements()->create(array_merge($data, [
            'organization_id' => $profile->organization_id,
        ]));

        return back()->with('status', __('Anforderung ergänzt.'));
    }

    public function edit(AssetComplianceProfile $profile): View {
        Gate::authorize('update', $profile);
        $this->ensureEditableProfile($profile);

        return view('asset-compliance._profile_form_dialog', [
            'profile' => $profile,
            'kinds' => AssetInspectionKind::cases(),
            'blockModes' => AssetComplianceBlockMode::cases(),
        ]);
    }

    /** Org-Profil ändern (MVP-1575); der Code bleibt, er verbindet es mit der globalen Vorlage. */
    public function update(Request $request, AssetComplianceProfile $profile): RedirectResponse {
        Gate::authorize('update', $profile);
        $this->ensureEditableProfile($profile);

        $data = $request->validate($this->profileRules());

        $profile->update(array_merge($data, [
            'warn_days_before' => (int) ($data['warn_days_before'] ?? 0),
            'tolerance_days' => (int) ($data['tolerance_days'] ?? 0),
            'grace_days' => (int) ($data['grace_days'] ?? 0),
            'requires_certificate' => $request->boolean('requires_certificate'),
        ]));

        return redirect()->route('asset-compliance.profiles.index')->with('status', __('asset_compliance.profile.updated'));
    }

    /** Deaktivieren statt löschen (MVP-1575): Pflichten und Protokolle hängen am Profil. */
    public function toggle(AssetComplianceProfile $profile): RedirectResponse {
        Gate::authorize('update', $profile);
        $this->ensureEditableProfile($profile);

        $profile->update(['is_active' => ! $profile->is_active]);

        return back()->with('status', $profile->is_active ? __('asset_compliance.profile.activated') : __('asset_compliance.profile.deactivated'));
    }

    public function editRequirement(AssetComplianceProfile $profile, AssetComplianceRequirement $requirement): View {
        Gate::authorize('update', $profile);
        $this->ensureRequirementOf($profile, $requirement);

        return view('asset-compliance._requirement_form_dialog', ['profile' => $profile, 'requirement' => $requirement]);
    }

    public function updateRequirement(Request $request, AssetComplianceProfile $profile, AssetComplianceRequirement $requirement): RedirectResponse {
        Gate::authorize('update', $profile);
        $this->ensureRequirementOf($profile, $requirement);

        $data = $request->validate($this->requirementRules());
        $requirement->update(array_merge($data, ['is_mandatory' => $request->boolean('is_mandatory')]));

        return redirect()->route('asset-compliance.profiles.index')->with('status', __('asset_compliance.requirement.updated'));
    }

    /** Deaktivierte Anforderungen fragt die Prüfung nicht mehr ab; alte Ergebniszeilen behalten ihren Bezug. */
    public function toggleRequirement(AssetComplianceProfile $profile, AssetComplianceRequirement $requirement): RedirectResponse {
        Gate::authorize('update', $profile);
        $this->ensureRequirementOf($profile, $requirement);

        $requirement->update(['is_active' => ! $requirement->is_active]);

        return back()->with('status', $requirement->is_active ? __('asset_compliance.requirement.activated') : __('asset_compliance.requirement.deactivated'));
    }

    public function editAssignment(AssetComplianceAssignment $assignment): View {
        Gate::authorize('create', AssetComplianceProfile::class);

        return view('asset-compliance._assignment_form_dialog', [
            'assignment' => $assignment->load(['asset:id,name', 'profile']),
            'users' => User::inCurrentOrganization()->orderBy('name')->get(['id', 'name']),
            'externalContacts' => \App\Models\Contacts\ExternalContact::query()->orderBy('name')->limit(200)->get(['id', 'name']),
        ]);
    }

    /** Prüfpflicht ändern (MVP-1575), darunter das Intervall je Asset. */
    public function updateAssignment(Request $request, AssetComplianceAssignment $assignment): RedirectResponse {
        Gate::authorize('create', AssetComplianceProfile::class);

        foreach (['responsible_user_id' => User::class, 'external_contact_id' => \App\Models\Contacts\ExternalContact::class] as $field => $model) {
            $request->merge([$field => $request->filled($field) ? Sqid::decodeOrNumeric($model, $request->input($field)) : null]);
        }

        $data = $request->validate([
            'interval_months_override' => ['nullable', 'integer', 'min:1', 'max:240'],
            'last_done_on' => ['nullable', 'date'],
            'next_due_on' => ['nullable', 'date'],
            'responsible_user_id' => ['nullable', 'integer', new ExistsInCurrentOrganization('users')],
            'external_contact_id' => ['nullable', 'integer', new ExistsInCurrentOrganization('external_contacts')],
            'note' => ['nullable', 'string', 'max:1000'],
        ]);
        // Leeres Fälligkeitsfeld heißt „neu rechnen“, nicht „Fälligkeit löschen“.
        if (empty($data['next_due_on'])) {
            unset($data['next_due_on']);
        }

        $this->service->updateAssignment($assignment, $this->authUser(), $data);

        return redirect()->toList('asset-compliance.index')->with('status', __('asset_compliance.assignment.updated'));
    }

    public function toggleAssignment(AssetComplianceAssignment $assignment): RedirectResponse {
        Gate::authorize('create', AssetComplianceProfile::class);

        $this->service->setAssignmentActive($assignment, ! $assignment->is_active, $this->authUser());

        return back()->with('status', $assignment->is_active ? __('asset_compliance.assignment.activated') : __('asset_compliance.assignment.deactivated'));
    }

    /** Prüfpflicht zuweisen (MVP-284). */
    public function assign(Request $request, AssetComplianceProfile $profile): RedirectResponse {
        Gate::authorize('create', AssetComplianceProfile::class);
        $this->ensureOwnProfile($profile);

        foreach (['asset_id' => Asset::class, 'responsible_user_id' => User::class, 'external_contact_id' => \App\Models\Contacts\ExternalContact::class] as $field => $model) {
            if ($request->filled($field)) {
                $request->merge([$field => Sqid::decodeOrNumeric($model, $request->input($field))]);
            }
        }

        $data = $request->validate([
            'asset_id' => ['required', 'integer', new ExistsInCurrentOrganization('assets')],
            'interval_months_override' => ['nullable', 'integer', 'min:1', 'max:240'],
            'last_done_on' => ['nullable', 'date'],
            'next_due_on' => ['nullable', 'date'],
            'responsible_user_id' => ['nullable', 'integer', new ExistsInCurrentOrganization('users')],
            'external_contact_id' => ['nullable', 'integer', new ExistsInCurrentOrganization('external_contacts')],
            'note' => ['nullable', 'string', 'max:1000'],
        ]);

        $asset = Asset::query()->whereKey($data['asset_id'])->firstOrFail();

        if ($asset->complianceAssignments()->where('asset_compliance_profile_id', $profile->id)->exists()) {
            return back()->withErrors(['asset_id' => __('Diese Prüfpflicht ist dem Asset bereits zugewiesen.')]);
        }

        unset($data['asset_id']);
        $this->service->assign($profile, $asset, $request->user() ?? abort(401), $data);

        return back()->with('status', __('Prüfpflicht zugewiesen.'));
    }

    /**
     * Fremde Org-Profile bleiben tabu.
     *
     * `AssetComplianceProfile` steht bewusst ohne OrganizationScope auf der
     * Allow-List, weil `organization_id === null` eine **globale Vorlage**
     * bezeichnet. Die Route bindet das Modell deshalb org-übergreifend, und
     * die Policy prüft nur das Recht, nicht die Organisation: eine neue
     * Pflicht-Anforderung landete mit `organization_id` des Fremdprofils
     * direkt in dessen Organisation und beeinflusste dort Prüfergebnisse und
     * Sperrlogik (Sicherheitsscan 2026-08-23, S-25).
     *
     * Globale Vorlagen (organization_id === null) sind ausgenommen — sie
     * gehören allen und werden an anderer Stelle vor Änderungen geschützt.
     */
    /** Globale Vorlagen ändert niemand; fremde Organisationsprofile ebenso wenig. */
    private function ensureEditableProfile(AssetComplianceProfile $profile): void {
        abort_if($profile->organization_id === null, 403);
        $this->ensureOwnProfile($profile);
    }

    private function ensureRequirementOf(AssetComplianceProfile $profile, AssetComplianceRequirement $requirement): void {
        $this->ensureEditableProfile($profile);
        abort_unless((int) $requirement->asset_compliance_profile_id === (int) $profile->id, 404);
    }

    private function ensureOwnProfile(AssetComplianceProfile $profile): void {
        if ($profile->organization_id === null) {
            return;
        }

        abort_unless((int) $profile->organization_id === (int) $this->currentOrganization()->id, 403);
    }

}
