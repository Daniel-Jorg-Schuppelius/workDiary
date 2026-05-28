<?php
/*
 * Created on   : Wed May 27 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : SoftwareInstallationController.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Http\Controllers;

use App\Enums\Software\SoftwareKind;
use App\Exceptions\SoftwareInstallationException;
use App\Models\{Asset, Software, SoftwareInstallation, User};
use App\Services\Software\SoftwareInstallationService;
use App\Services\SqidEncoder;
use Illuminate\Http\{JsonResponse, RedirectResponse, Request};
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

class SoftwareInstallationController extends Controller {
    public function __construct(
        private readonly SoftwareInstallationService $installations,
        private readonly SqidEncoder $sqids,
    ) {}

    public function create(Request $request, Asset $asset): View {
        Gate::authorize('update', $asset);
        $user = $request->user();

        if (! $user instanceof User) {
            abort(403);
        }

        $isOperatingSystem = $request->boolean('os');
        $orgId = (int) $user->organization_id;

        $catalog = Software::query()
            ->where('organization_id', $orgId)
            ->where('is_active', true)
            ->when(
                $isOperatingSystem,
                fn($q) => $q->where('kind', SoftwareKind::OperatingSystem),
                fn($q) => $q->where('kind', '!=', SoftwareKind::OperatingSystem),
            )
            ->orderBy('name')
            ->get(['id', 'name', 'vendor', 'kind', 'default_version']);

        return view('assets._software_form_dialog', [
            'asset' => $asset,
            'installation' => $isOperatingSystem ? $asset->operatingSystem : null,
            'isOperatingSystem' => $isOperatingSystem,
            'softwareCatalog' => $catalog,
        ]);
    }

    public function store(Request $request, Asset $asset): RedirectResponse|JsonResponse {
        Gate::authorize('update', $asset);
        $user = $request->user();

        if (! $user instanceof User) {
            abort(403);
        }

        $orgId = (int) $user->organization_id;

        $softwareId = $request->input('software_id');
        if (is_string($softwareId) && $softwareId !== '' && ! ctype_digit($softwareId)) {
            $request->merge(['software_id' => $this->sqids->decode(Software::class, $softwareId)]);
        }

        $validated = $request->validate([
            'software_id' => [
                'required',
                'integer',
                \Illuminate\Validation\Rule::exists('software', 'id')
                    ->where(fn($q) => $q->where('organization_id', $orgId)),
            ],
            'version' => ['nullable', 'string', 'max:64'],
            'license_key' => ['nullable', 'string', 'max:2000'],
            'seats' => ['nullable', 'integer', 'min:1'],
            'installed_on' => ['nullable', 'date'],
            'expires_on' => ['nullable', 'date'],
            'is_operating_system' => ['sometimes', 'boolean'],
            'notes' => ['nullable', 'string', 'max:5000'],
        ]);

        /** @var Software $software */
        $software = Software::query()->findOrFail($validated['software_id']);

        try {
            $this->installations->attach($asset, $software, $user, $validated);
        } catch (SoftwareInstallationException $exception) {
            return $this->validationError($request, 'software_id', __($exception->getMessage()));
        }

        return redirect()
            ->route('assets.show', $asset)
            ->with('success', __('Software hinzugefügt.'));
    }

    public function update(Request $request, Asset $asset, SoftwareInstallation $installation): RedirectResponse|JsonResponse {
        Gate::authorize('update', $asset);
        $this->ensureOwnership($asset, $installation);

        $user = $request->user();
        if (! $user instanceof User) {
            abort(403);
        }

        $validated = $request->validate([
            'version' => ['nullable', 'string', 'max:64'],
            'license_key' => ['nullable', 'string', 'max:2000'],
            'seats' => ['nullable', 'integer', 'min:1'],
            'installed_on' => ['nullable', 'date'],
            'expires_on' => ['nullable', 'date'],
            'is_operating_system' => ['sometimes', 'boolean'],
            'notes' => ['nullable', 'string', 'max:5000'],
        ]);

        try {
            $this->installations->update($installation, $user, $validated);
        } catch (SoftwareInstallationException $exception) {
            return $this->validationError($request, 'is_operating_system', __($exception->getMessage()));
        }

        return redirect()
            ->route('assets.show', $asset)
            ->with('success', __('Installation aktualisiert.'));
    }

    public function destroy(Request $request, Asset $asset, SoftwareInstallation $installation): RedirectResponse {
        Gate::authorize('update', $asset);
        $this->ensureOwnership($asset, $installation);

        $user = $request->user();
        if (! $user instanceof User) {
            abort(403);
        }

        $this->installations->detach($installation, $user);

        return redirect()
            ->route('assets.show', $asset)
            ->with('success', __('Software entfernt.'));
    }

    private function validationError(Request $request, string $field, string $message): RedirectResponse|JsonResponse {
        if ($request->expectsJson()) {
            return response()->json(['errors' => [$field => [$message]]], 422);
        }

        return back()->withInput()->withErrors([$field => $message]);
    }

    private function ensureOwnership(Asset $asset, SoftwareInstallation $installation): void {
        if ((int) $installation->asset_id !== (int) $asset->id) {
            abort(404);
        }
    }
}
