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
use App\Models\{Asset, User};
use App\Models\AssetCompliance\{AssetComplianceNormReference, AssetComplianceProfile};
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
    public function __construct(private readonly AssetComplianceService $service) {}

    public function index(Request $request): View {
        Gate::authorize('viewAny', AssetComplianceProfile::class);

        $organizationId = (int) ($request->user()->organization_id ?? 0);

        return view('asset-compliance.profiles', [
            'profiles' => $this->service->effectiveProfiles($organizationId)->load('requirements'),
            'kinds' => AssetInspectionKind::cases(),
            'blockModes' => AssetComplianceBlockMode::cases(),
            'assets' => Asset::query()->orderBy('name')->get(['id', 'name']),
            'users' => User::query()->orderBy('name')->get(['id', 'name']),
            'externalContacts' => \App\Models\ExternalContact::query()->orderBy('name')->limit(200)->get(['id', 'name']),
            'norms' => AssetComplianceNormReference::query()
                ->forOrganization($organizationId)
                ->orderBy('inspection_kind')
                ->get(),
        ]);
    }

    /** Org-Profil anlegen (überschreibt globale Vorlage gleichen Codes, P1). */
    public function store(Request $request): RedirectResponse {
        Gate::authorize('create', AssetComplianceProfile::class);

        $data = $request->validate([
            'code' => ['required', 'string', 'max:60'],
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
        ]);

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

        $data = $request->validate([
            'label' => ['required', 'string', 'max:255'],
            'code' => ['nullable', 'string', 'max:60'],
            'unit' => ['nullable', 'string', 'max:30'],
            'limit_min' => ['nullable', 'numeric'],
            'limit_max' => ['nullable', 'numeric'],
            'is_mandatory' => ['sometimes', 'boolean'],
        ]);

        $profile->requirements()->create(array_merge($data, [
            'organization_id' => $profile->organization_id,
        ]));

        return back()->with('status', __('Anforderung ergänzt.'));
    }

    /** Prüfpflicht zuweisen (MVP-284). */
    public function assign(Request $request, AssetComplianceProfile $profile): RedirectResponse {
        Gate::authorize('create', AssetComplianceProfile::class);

        foreach (['asset_id' => Asset::class, 'responsible_user_id' => User::class, 'external_contact_id' => \App\Models\ExternalContact::class] as $field => $model) {
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
}
