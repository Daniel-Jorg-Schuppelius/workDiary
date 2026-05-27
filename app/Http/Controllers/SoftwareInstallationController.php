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

use App\Exceptions\SoftwareInstallationException;
use App\Models\{Asset, Software, SoftwareInstallation, User};
use App\Services\Software\SoftwareInstallationService;
use Illuminate\Http\{RedirectResponse, Request};
use Illuminate\Support\Facades\Gate;

class SoftwareInstallationController extends Controller {
    public function __construct(
        private readonly SoftwareInstallationService $installations,
    ) {
    }

    public function store(Request $request, Asset $asset): RedirectResponse {
        Gate::authorize('update', $asset);
        $user = $request->user();

        if (! $user instanceof User) {
            abort(403);
        }

        $orgId = (int) $user->organization_id;

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
            return back()->withInput()->withErrors([
                'software_id' => __($exception->getMessage()),
            ]);
        }

        return redirect()
            ->route('assets.show', $asset)
            ->with('success', __('Software hinzugefügt.'));
    }

    public function update(Request $request, Asset $asset, SoftwareInstallation $installation): RedirectResponse {
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
            return back()->withInput()->withErrors([
                'is_operating_system' => __($exception->getMessage()),
            ]);
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

    private function ensureOwnership(Asset $asset, SoftwareInstallation $installation): void {
        if ((int) $installation->asset_id !== (int) $asset->id) {
            abort(404);
        }
    }
}
