<?php
/*
 * Created on   : Sun Sep 27 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : BranchProfileVariantController.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Enums\User\Permission as P;
use App\Http\Controllers\Concerns\ResolvesCurrentOrganization;
use App\Http\Controllers\Controller;
use App\Models\Classification\BranchProfileVariant;
use App\Services\Classification\BranchProfileVariantService;
use App\Support\{BranchProfileFiles, ErrorText};
use CommonToolkit\Helper\Data\JsonHelper;
use Illuminate\Http\{RedirectResponse, Request, Response};
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\{Rule, ValidationException};
use Illuminate\View\View;

/** Kundenspezifische Profilvarianten (MVP-933): anlegen, überlagern, installieren, exportieren. */
class BranchProfileVariantController extends Controller {
    use ResolvesCurrentOrganization;

    public function __construct(private readonly BranchProfileVariantService $variants) {}

    public function create(): View {
        Gate::authorize(P::BranchProfileInstall->value);

        return view('admin.branch-profiles.variants._form_dialog', ['bases' => $this->variants->bases()]);
    }

    public function store(Request $request): RedirectResponse {
        Gate::authorize(P::BranchProfileInstall->value);
        $organization = $this->currentOrganization();
        $data = $request->validate([
            'base_code' => ['required', 'string', Rule::in(array_keys($this->variants->bases()))],
            'code' => ['required', 'string', 'max:60', 'regex:/^[a-z0-9-]+$/', Rule::unique('branch_profile_variants', 'code')->where('organization_id', $organization->id)],
            'label' => ['required', 'string', 'max:200'],
            'description' => ['nullable', 'string', 'max:2000'],
        ]);
        $variant = BranchProfileVariant::query()->create($data + ['organization_id' => $organization->id, 'created_by' => $this->authUser()->id]);

        return redirect()->route('admin.branch-profile-variants.edit', $variant)->with('success', __('branch_profile.variant.flash.created'));
    }

    public function edit(BranchProfileVariant $variant): View {
        Gate::authorize(P::BranchProfileInstall->value);

        return view('admin.branch-profiles.variants.edit', [
            'variant' => $variant,
            'options' => $this->variants->options(BranchProfileFiles::profile($variant->base_code) ?? []),
            'additions' => $variant->additions !== null ? JsonHelper::encode($variant->additions, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) : '',
            'installed' => data_get($this->currentOrganization()->settings, BranchProfileVariantService::SETTINGS_KEY . '.' . $variant->base_code),
        ]);
    }

    public function update(Request $request, BranchProfileVariant $variant): RedirectResponse {
        Gate::authorize(P::BranchProfileInstall->value);
        $data = $request->validate([
            'label' => ['required', 'string', 'max:200'],
            'description' => ['nullable', 'string', 'max:2000'],
            'removals' => ['nullable', 'array'],
            'removals.*' => ['array'],
            'removals.*.*' => ['string', 'max:200'],
            'additions' => ['nullable', 'string', 'max:65000'],
        ]);

        $options = $this->variants->options(BranchProfileFiles::profile($variant->base_code) ?? []);
        $removals = [];
        foreach ((array) ($data['removals'] ?? []) as $section => $keys) {
            $known = array_column($options[$section] ?? [], 'key');
            $picked = array_values(array_intersect(array_map('strval', (array) $keys), $known));
            if ($picked !== []) {
                $removals[(string) $section] = $picked;
            }
        }

        $additions = null;
        $raw = trim((string) ($data['additions'] ?? ''));
        if ($raw !== '') {
            $decoded = JsonHelper::isValid($raw) ? JsonHelper::decode($raw) : null;
            if (! is_array($decoded) || array_is_list($decoded)) {
                throw ValidationException::withMessages(['additions' => __('branch_profile.variant.error.json')]);
            }
            $error = $this->variants->profileError($decoded);
            if ($error !== null) {
                throw ValidationException::withMessages(['additions' => $error]);
            }
            $additions = $decoded;
        }

        $variant->update([
            'label' => $data['label'],
            'description' => $data['description'] ?? null,
            'removals' => $removals !== [] ? $removals : null,
            'additions' => $additions,
            'version' => $variant->version + 1,
            'updated_by' => $this->authUser()->id,
        ]);

        return back()->with('success', __('branch_profile.variant.flash.saved'));
    }

    public function install(Request $request, BranchProfileVariant $variant): RedirectResponse {
        Gate::authorize(P::BranchProfileInstall->value);
        try {
            $this->variants->install($this->currentOrganization(), $variant, $this->authUser(), $request->boolean('force'));
        } catch (\RuntimeException $e) {
            return back()->with('error', ErrorText::for($e));
        }

        return back()->with('success', __('branch_profile.variant.flash.installed', ['label' => $variant->label]));
    }

    public function export(BranchProfileVariant $variant): Response {
        Gate::authorize(P::BranchProfileViewCatalog->value);

        return response(JsonHelper::encode($this->variants->compose($variant), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), 200, [
            'Content-Type' => 'application/json',
            'Content-Disposition' => 'attachment; filename="' . $variant->code . '.json"',
        ]);
    }

    public function destroy(BranchProfileVariant $variant): RedirectResponse {
        Gate::authorize(P::BranchProfileInstall->value);
        $variant->delete();

        return redirect()->toList('admin.branch-profiles.index')->with('success', __('branch_profile.variant.flash.deleted'));
    }
}
