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
use App\Models\{AuditLog, Organization, User};
use App\Services\Classification\BranchProfileInstaller;
use Illuminate\Http\{RedirectResponse, Request};
use Illuminate\Support\{Arr, Collection};
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class BranchProfileController extends Controller {
    public function __construct(
        private readonly BranchProfileInstaller $installer,
    ) {}

    public function index(Request $request): View {
        $this->authorizeViewCatalog();

        $organization = $this->currentOrganization();
        $profiles = $this->availableProfiles();
        $query = trim($request->string('q')->toString());
        $installedFilter = $this->normalizeInstalledFilter($request->string('installed')->toString());

        $installedCodes = AuditLog::query()
            ->where('organization_id', $organization->id)
            ->where('event', 'branch_profile.installed')
            ->orderByDesc('id')
            ->get()
            ->pluck('changes.profile_code')
            ->filter(static fn($value): bool => is_string($value) && $value !== '')
            ->unique()
            ->values()
            ->all();
        $installedSet = array_fill_keys($installedCodes, true);

        if ($query !== '') {
            $needle = mb_strtolower($query);

            $profiles = $profiles->filter(static function (array $profile) use ($needle): bool {
                $haystack = mb_strtolower((string) Arr::get($profile, 'label', '') . ' ' . (string) Arr::get($profile, 'code', ''));

                return str_contains($haystack, $needle);
            })->values();
        }

        if ($installedFilter !== 'all') {
            $profiles = $profiles->filter(static function (array $profile) use ($installedFilter, $installedSet): bool {
                $isInstalled = isset($installedSet[(string) Arr::get($profile, 'code', '')]);

                if ($installedFilter === 'installed') {
                    return $isInstalled;
                }

                return ! $isInstalled;
            })->values();
        }

        return view('admin.branch-profiles.index', [
            'organization' => $organization,
            'profiles' => $profiles,
            'installedCodes' => $installedCodes,
            'activeFilters' => [
                'q' => $query,
                'installed' => $installedFilter,
            ],
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

    private function normalizeInstalledFilter(string $value): string {
        return match ($value) {
            'installed' => 'installed',
            'not_installed' => 'not_installed',
            default => 'all',
        };
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
