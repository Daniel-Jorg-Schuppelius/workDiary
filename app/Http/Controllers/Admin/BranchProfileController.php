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

use App\Http\Controllers\Concerns\{RequiresPlatformOperator, ResolvesCurrentOrganization};
use App\Http\Controllers\Controller;
use App\Models\{AuditLog, User};
use App\Services\Classification\BranchProfileInstaller;
use App\Support\ErrorText;
use CommonToolkit\Helper\FileSystem\{File, Folder};
use Illuminate\Http\{RedirectResponse, Request};
use Illuminate\Support\{Arr, Collection};
use Illuminate\Support\Facades\{Auth, Gate};
use Illuminate\View\View;

class BranchProfileController extends Controller {
    use RequiresPlatformOperator;
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

        // MVP-839: installiert = registriert in den Org-Einstellungen; das
        // Audit dient nur noch Altbeständen vor der Versionsregistrierung.
        $installedCodes = $organization->installedBranchProfileCodes();
        $audited = AuditLog::query()
            ->where('organization_id', $organization->id)
            ->where('event', 'branch_profile.installed')
            ->orderByDesc('id')
            ->get()
            ->pluck('changes.profile_code')
            ->filter(static fn($value): bool => is_string($value) && $value !== '')
            ->unique()
            ->values()
            ->all();
        $uninstalled = AuditLog::query()
            ->where('organization_id', $organization->id)
            ->where('event', 'branch_profile.uninstalled')
            ->get()
            ->pluck('changes.profile_code')
            ->filter(static fn($value): bool => is_string($value) && $value !== '')
            ->all();
        foreach ($audited as $code) {
            if (! in_array($code, $installedCodes, true) && ! in_array($code, $uninstalled, true)) {
                $installedCodes[] = $code;
            }
        }
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
            'primaryCode' => $organization->primaryBranchProfileCode(),
            'canUninstall' => $this->isPlatformOperator() && Gate::allows('branchProfile.uninstall'),
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

        return redirect()->toList('admin.branch-profiles.index')
            ->with('success', __('Profil ":profile" installiert: :classifications Klassifikationen, :requirements Pflichtregeln, :tags Tags.', [
                'profile' => $result['profile_code'],
                'classifications' => $result['created']['classifications'] + $result['updated']['classifications'],
                'requirements' => $result['created']['classification_requirements'] + $result['updated']['classification_requirements'],
                'tags' => $result['created']['tags'] + $result['updated']['tags'],
            ]));
    }

    /** Hauptprofil wechseln (MVP-839): nur unter den installierten Profilen. */
    public function setPrimary(Request $request, string $profile): RedirectResponse {
        $this->authorizeInstall();
        $organization = $this->currentOrganization();
        abort_unless(in_array($profile, $organization->installedBranchProfileCodes(), true), 404);

        /** @var User|null $actor */
        $actor = Auth::user();
        $this->installer->setPrimary($organization, $profile, $actor);

        return redirect()->toList('admin.branch-profiles.index')
            ->with('success', __('Profil ":profile" ist jetzt das Hauptprofil.', ['profile' => $profile]));
    }

    /**
     * Deinstallation (MVP-839, P12-09): entfernt Klassifikationen, Pflichtregeln
     * und Tags des Profils, soweit sie unbenutzt sind; Vorlagen bleiben.
     */
    public function uninstall(Request $request, string $profile): RedirectResponse {
        // Deinstallation ist Betreiber-Sache (Branchenprofil-Doku §10): die
        // org-lokale Admin-Rolle trägt das Recht mit, reicht aber nicht.
        Gate::authorize('branchProfile.uninstall');
        $this->assertPlatformOperator();
        $organization = $this->currentOrganization();
        abort_unless(in_array($profile, $organization->installedBranchProfileCodes(), true), 404);

        /** @var User|null $actor */
        $actor = Auth::user();
        $result = $this->installer->uninstall($organization, $profile, $actor);

        return redirect()->toList('admin.branch-profiles.index')
            ->with('success', __('Profil ":profile" deinstalliert: :removed Klassifikationen entfernt, :deactivated deaktiviert (in Verwendung), :requirements Pflichtregeln und :tags Tags entfernt. Vorlagen bleiben erhalten.', [
                'profile' => $result['profile_code'],
                'removed' => $result['removed']['classifications'],
                'deactivated' => $result['deactivated']['classifications'],
                'requirements' => $result['removed']['classification_requirements'],
                'tags' => $result['removed']['tags'],
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
            return back()->with('error', ErrorText::for($e));
        }

        return redirect()->toList('admin.branch-profiles.index')
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
     * @return Collection<int, array{code: string, label: string, description: string, version: int, classification_count: int, entry_type_count: int, requirement_count: int, tag_count: int, procedure_count: int, room_requirement_count: int, entry_types: list<string>, procedures: list<string>}>
     */
    private function availableProfiles(): Collection {
        $profiles = [];
        $directory = database_path('data/branchprofiles');

        foreach (Folder::exists($directory) ? Folder::findByPattern($directory, '*.php') : [] as $file) {
            /** @var array<string, mixed> $profile */
            $profile = require $file;

            /** @var array<string, mixed> $domains */
            $domains = (array) ($profile['classifications'] ?? []);
            $classificationCount = 0;
            foreach ($domains as $rows) {
                $classificationCount += is_array($rows) ? count($rows) : 0;
            }

            // MVP-841: Vorschau in der Sprache des Nutzers (Beilage i18n/<code>.php).
            $profileCode = (string) ($profile['code'] ?? pathinfo($file, PATHINFO_FILENAME));
            $i18nFile = database_path("data/branchprofiles/i18n/{$profileCode}.php");
            /** @var array<string, array<string, array<string, string>>> $i18n */
            $i18n = File::isFile($i18nFile) ? (array) require $i18nFile : [];
            $locale = strtolower(substr(app()->getLocale(), 0, 2));

            /** @var list<array<string, mixed>> $entryTypeRows */
            $entryTypeRows = (array) ($domains['entry_type'] ?? []);
            $entryTypes = [];
            foreach ($entryTypeRows as $row) {
                $code = (string) ($row['code'] ?? '');
                $translated = $locale !== 'de' ? (string) ($i18n['entry_type'][$code][$locale] ?? '') : '';
                $entryTypes[] = $translated !== '' ? $translated : (string) ($row['label'] ?? $code);
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
                'code' => $profileCode,
                'label' => (string) ($profile['label'] ?? pathinfo($file, PATHINFO_FILENAME)),
                'description' => (string) ($profile['description'] ?? ''),
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
