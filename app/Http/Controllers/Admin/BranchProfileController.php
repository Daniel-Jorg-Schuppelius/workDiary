<?php

/*
 * Created on   : Sat May 23 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : BranchProfileController.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\Organization;
use App\Models\User;
use App\Services\Classification\BranchProfileInstaller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class BranchProfileController extends Controller {
    public function __construct(
        private readonly BranchProfileInstaller $installer,
    ) {}

    public function index(): View {
        $this->authorizeViewCatalog();

        $organization = $this->currentOrganization();
        $profiles = $this->availableProfiles();
        $installedCodes = AuditLog::query()
            ->where('organization_id', $organization->id)
            ->where('event', 'branch_profile.installed')
            ->orderByDesc('id')
            ->get()
            ->pluck('changes.profile_code')
            ->filter(static fn ($value): bool => is_string($value) && $value !== '')
            ->unique()
            ->values()
            ->all();

        return view('admin.branch-profiles.index', [
            'organization' => $organization,
            'profiles' => $profiles,
            'installedCodes' => $installedCodes,
        ]);
    }

    public function install(Request $request, string $profile): RedirectResponse {
        $this->authorizeInstall();

        $availableProfiles = $this->availableProfiles()->keyBy('code');
        abort_unless($availableProfiles->has($profile), 404);

        /** @var User|null $actor */
        $actor = Auth::user();
        $result = $this->installer->install(
            $this->currentOrganization(),
            $profile,
            $actor,
            $request->boolean('force'),
        );

        return redirect()->route('admin.branch-profiles.index')
            ->with('success', __('Profil ":profile" installiert: :classifications Klassifikationen, :requirements Pflichtregeln, :tags Tags.', [
                'profile' => $result['profile_code'],
                'classifications' => $result['created']['classifications'] + $result['updated']['classifications'],
                'requirements' => $result['created']['classification_requirements'] + $result['updated']['classification_requirements'],
                'tags' => $result['created']['tags'] + $result['updated']['tags'],
            ]));
    }

    private function authorizeViewCatalog(): void {
        abort_unless(Auth::user()?->can('branchProfile.viewCatalog') ?? false, 403);
    }

    private function authorizeInstall(): void {
        abort_unless(Auth::user()?->can('branchProfile.install') ?? false, 403);
    }

    private function currentOrganization(): Organization {
        abort_unless(app()->bound('currentOrganization'), 403);

        $organization = app('currentOrganization');
        abort_unless($organization instanceof Organization, 403);

        return $organization;
    }

    /**
     * @return Collection<int, array{code: string, label: string, version: int, classification_count: int, requirement_count: int, tag_count: int}>
     */
    private function availableProfiles(): Collection {
        $profiles = [];

        foreach (glob(database_path('data/branchprofiles/*.php')) ?: [] as $file) {
            /** @var array<string, mixed> $profile */
            $profile = require $file;

            $classificationCount = 0;
            foreach ((array) ($profile['classifications'] ?? []) as $rows) {
                $classificationCount += is_array($rows) ? count($rows) : 0;
            }

            $profiles[] = [
                'code' => (string) ($profile['code'] ?? pathinfo($file, PATHINFO_FILENAME)),
                'label' => (string) ($profile['label'] ?? pathinfo($file, PATHINFO_FILENAME)),
                'version' => (int) ($profile['version'] ?? 1),
                'classification_count' => $classificationCount,
                'requirement_count' => count((array) ($profile['classification_requirements'] ?? [])),
                'tag_count' => count((array) ($profile['tags_seed'] ?? [])),
            ];
        }

        return collect($profiles)->sortBy('label')->values();
    }
}
