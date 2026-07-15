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

use App\Http\Controllers\Concerns\ResolvesCurrentOrganization;
use App\Http\Controllers\Controller;
use App\Models\{AuditLog, User};
use App\Services\Classification\BranchProfileInstaller;
use Illuminate\Http\{RedirectResponse, Request};
use Illuminate\Support\{Arr, Collection};
use Illuminate\Support\Facades\{Auth, Gate};
use Illuminate\View\View;

class BranchProfileController extends Controller {
    use ResolvesCurrentOrganization;

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
            // Restpunkt 042: angewandte Version je Profil → Update-Erkennung.
            'installedVersions' => (array) data_get((array) ($organization->settings ?? []), 'branch_profile_versions', []),
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

    /**
     * Marketplace-Import (Restpunkt 042): hochgeladenes JSON-Profil wird
     * gegen das Profil-Schema UND die harten Klassifikations-Domänen
     * (ClassificationDomain-Enum) validiert und dann über denselben
     * Installer-Kern angewendet wie die mitgelieferten Profile.
     */
    public function import(Request $request): RedirectResponse {
        $this->authorizeViewCatalog();

        $request->validate([
            'file' => ['required', 'file', 'max:2048', 'mimetypes:application/json,text/plain'],
        ]);

        $profile = json_decode((string) $request->file('file')->get(), true);
        if (! is_array($profile)) {
            return back()->with('error', __('Die Datei enthält kein gültiges JSON-Profil.'));
        }

        foreach (['code', 'label'] as $field) {
            if (! is_string($profile[$field] ?? null) || trim((string) $profile[$field]) === '') {
                return back()->with('error', __('Profil unvollständig: Feld :field fehlt.', ['field' => $field]));
            }
        }

        // Klassifikations-Domänen bleiben hart begrenzt (Branchenprofile-Regel).
        foreach (array_keys((array) ($profile['classifications'] ?? [])) as $domain) {
            if (\App\Enums\Classification\ClassificationDomain::tryFrom((string) $domain) === null) {
                return back()->with('error', __('Unbekannte Klassifikations-Domäne ":domain" — Profil abgelehnt.', ['domain' => $domain]));
            }
        }

        // Feature 081 (MVP-373): Modul-Empfehlungen nur mit bekannten Katalog-Codes.
        foreach ((array) ($profile['modules_recommended'] ?? []) as $module) {
            if (! app(\App\Services\Licensing\ModuleCatalog::class)->has((string) $module)) {
                return back()->with('error', __('Unbekanntes Modul ":module" in der Modul-Empfehlung — Profil abgelehnt.', ['module' => (string) $module]));
            }
        }

        /** @var \App\Models\User $actor */
        $actor = $request->user();

        try {
            $result = $this->installer->installProfile($this->currentOrganization(), $profile, $actor, force: false);
        } catch (\RuntimeException $e) {
            return back()->with('error', $e->getMessage());
        }

        return redirect()->route('admin.branch-profiles.index')
            ->with('success', __('Profil ":label" (v:version) importiert und installiert.', [
                'label' => (string) $profile['label'],
                'version' => (string) $result['version'],
            ]));
    }

    private function authorizeViewCatalog(): void {
        Gate::authorize('branchProfile.viewCatalog');
    }

    private function authorizeInstall(): void {
        Gate::authorize('branchProfile.install');
    }

    private function normalizeInstalledFilter(string $value): string {
        return match ($value) {
            'installed' => 'installed',
            'not_installed' => 'not_installed',
            default => 'all',
        };
    }

    /**
     * @return Collection<int, array{code: string, label: string, version: int, classification_count: int, entry_type_count: int, requirement_count: int, tag_count: int, procedure_count: int, room_requirement_count: int, entry_types: list<string>, procedures: list<string>}>
     */
    private function availableProfiles(): Collection {
        $profiles = [];

        foreach (glob(database_path('data/branchprofiles/*.php')) ?: [] as $file) {
            /** @var array<string, mixed> $profile */
            $profile = require $file;

            /** @var array<string, mixed> $domains */
            $domains = (array) ($profile['classifications'] ?? []);
            $classificationCount = 0;
            foreach ($domains as $rows) {
                $classificationCount += is_array($rows) ? count($rows) : 0;
            }

            /** @var list<array<string, mixed>> $entryTypeRows */
            $entryTypeRows = (array) ($domains['entry_type'] ?? []);
            $entryTypes = [];
            foreach ($entryTypeRows as $row) {
                $entryTypes[] = (string) ($row['label'] ?? $row['code'] ?? '');
            }
            $entryTypes = array_values(array_filter($entryTypes, static fn(string $v): bool => $v !== ''));

            /** @var list<array<string, mixed>> $procedureRows */
            $procedureRows = (array) ($profile['procedure_templates'] ?? []);
            $procedures = [];
            foreach ($procedureRows as $row) {
                if (isset($row['name']) && trim((string) $row['name']) !== '' && ($row['steps'] ?? []) !== []) {
                    $procedures[] = (string) $row['name'];
                }
            }

            $profiles[] = [
                'code' => (string) ($profile['code'] ?? pathinfo($file, PATHINFO_FILENAME)),
                'label' => (string) ($profile['label'] ?? pathinfo($file, PATHINFO_FILENAME)),
                'version' => (int) ($profile['version'] ?? 1),
                'classification_count' => $classificationCount,
                'entry_type_count' => count($entryTypeRows),
                'requirement_count' => count((array) ($profile['classification_requirements'] ?? [])),
                'tag_count' => count((array) ($profile['tags_seed'] ?? [])),
                'procedure_count' => count($procedures),
                'room_requirement_count' => count((array) ($profile['room_requirement_templates_seed'] ?? [])),
                'entry_types' => array_slice($entryTypes, 0, 8),
                'procedures' => array_slice($procedures, 0, 6),
            ];
        }

        return collect($profiles)->sortBy('label')->values();
    }
}
