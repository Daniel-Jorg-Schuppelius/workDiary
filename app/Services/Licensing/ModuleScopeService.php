<?php
/*
 * Created on   : Wed Jul 15 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ModuleScopeService.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Licensing;

use App\Enums\Licensing\ModuleStatus;
use App\Enums\User\UserRole;
use App\Models\Audit\AuditLog;
use App\Models\Platform\{LicenseFlagOverride, Organization, User};
use App\Services\Navigation\{NavFocusService, StartPageResolver};
use App\Settings\SettingScope;
use App\Support\{BranchProfileFiles, MorphMap, Setting};
use Carbon\CarbonImmutable;

/**
 * Funktionsumfang der Organisation (Feature 081, MVP-373; Feature 165, MVP-1188).
 *
 * Presets und die Seite „Funktionsumfang" sind reine Schreibhilfen für die
 * vorhandene Modulkonfiguration (MVP-052): Es wird ausschließlich der
 * Modulstatus über {@see LicenseFlagOverride} geschrieben (nur deaktivierend,
 * Option A) — nie lizenzerweiternd, nie datenlöschend, immer auditiert und
 * idempotent. Nicht lizenzierte oder systemseitig gesperrte Module bleiben
 * unberührt (D15).
 *
 * Seit Feature 165 ist dies die einzige Schreibstelle des Org-Modulstatus
 * (Gate `ModuleOverrideWriteRuleTest`): `organizations.settings[scope_profile]`
 * merkt Vorgabe, Version, Geltungsmodus und die bewusst gewählten Module.
 * Im Modus „bewusst begrenzt" hält {@see reconcile()} neu lizenzierte, nicht
 * gewählte Module per Override mit Grund `scope:growth` deaktiviert. Die
 * Metadaten sind Abgleichsgrundlage, nie Berechtigungsquelle — Gates lesen
 * nur Overrides (E2).
 */
class ModuleScopeService {
    public const SETTING_PROFILE = 'scope_profile';

    public const MODE_LIMITED = 'limited';

    public const MODE_FULL = 'full';

    /** Override-Grund für Lizenzzuwachs, der noch nicht gewählt wurde. */
    public const REASON_GROWTH = 'scope:growth';

    public function __construct(
        private readonly ModuleStatusResolver $status,
        private readonly ModuleCatalog $catalog,
        private readonly FeatureFlagResolver $resolver,
        private readonly PackageCatalog $packages,
        private readonly NavFocusService $focus,
        private readonly LicenseService $licenses,
    ) {}

    /**
     * Kuratierte Presets aus config/plans.php (validierte Form) plus die
     * Einstiegsvorgaben der Produktpakete (Feature 165) — eine Liste, keine
     * zweite Quelle: Paket-Presets heißen wie das Paket und tragen dessen
     * `default_active`.
     *
     * @return array<string, array{label: string, description: string, modules: list<string>|null, package: ?string}>
     */
    public function presets(): array {
        $out = [];
        foreach ((array) config('plans.presets', []) as $key => $preset) {
            if (! is_string($key) || $key === '' || ! is_array($preset)) {
                continue;
            }
            $modules = $preset['modules'] ?? null;
            $out[$key] = [
                'label' => (string) ($preset['label'] ?? $key),
                'description' => (string) ($preset['description'] ?? ''),
                'modules' => is_array($modules)
                    ? array_values(array_filter(array_map(static fn($m): string => (string) $m, $modules), fn(string $m): bool => $this->catalog->has($m)))
                    : null,
                'package' => null,
            ];
        }
        foreach ($this->packages->packages() as $key => $package) {
            if (isset($out[$key])) {
                continue;
            }
            $out[$key] = [
                'label' => $package['label'],
                'description' => $package['description'],
                'modules' => array_values(array_filter($package['default_active'], fn(string $m): bool => $this->catalog->has($m))),
                'package' => $key,
            ];
        }

        return $out;
    }

    /**
     * Modul-Empfehlung der installierten Branchenprofile der Organisation
     * (`modules_recommended` im Profil-Array), auf Katalogmodule gefiltert.
     * Seit MVP-839 die Vereinigung aller installierten Profile, Hauptprofil
     * zuerst; `code`/`label` nennen das Hauptprofil (Label listet alle).
     * null = kein Profil installiert oder keine Empfehlung hinterlegt.
     *
     * @return array{code: string, label: string, modules: list<string>}|null
     */
    public function branchProfileRecommendation(Organization $organization): ?array {
        $codes = array_values(array_filter($organization->installedBranchProfileCodes(), BranchProfileFiles::isValidCode(...)));
        if ($codes === []) {
            return null;
        }

        $modules = [];
        $labels = [];
        foreach ($codes as $code) {
            $profile = BranchProfileFiles::profile($code);
            $recommended = $profile['modules_recommended'] ?? null;
            if (! is_array($recommended) || $recommended === []) {
                continue;
            }
            $labels[] = (string) ($profile['label'] ?? $code);
            foreach ($recommended as $m) {
                $m = (string) $m;
                if ($this->catalog->has($m) && ! in_array($m, $modules, true)) {
                    $modules[] = $m;
                }
            }
        }
        if ($modules === []) {
            return null;
        }

        return [
            'code' => $codes[0],
            'label' => implode(' + ', $labels),
            'modules' => $modules,
        ];
    }

    // ── Umfangsprofil (Feature 165) ─────────────────────────────────────

    /**
     * Gespeichertes Umfangsprofil in normalisierter Form. Ohne Profil gilt
     * der bisherige Vollumfang (Bestandsorganisationen bleiben unberührt).
     * `applied` ist der Sollstand des zuletzt übernommenen Bündels (für den
     * Konfliktvergleich der Rücknahme), `snapshot` der Zustand davor.
     *
     * @return array{source: ?string, key: ?string, version: int, mode: string, chosen: list<string>, applied_at: ?string, applied_by: ?int, snapshot: array<string, mixed>, applied: array<string, mixed>}
     */
    public function profile(Organization $organization): array {
        $settings = is_array($organization->settings) ? $organization->settings : [];
        $raw = is_array($settings[self::SETTING_PROFILE] ?? null) ? $settings[self::SETTING_PROFILE] : [];
        $mode = (string) ($raw['mode'] ?? self::MODE_FULL);

        return [
            'source' => isset($raw['source']) && is_string($raw['source']) ? $raw['source'] : null,
            'key' => isset($raw['key']) && is_string($raw['key']) ? $raw['key'] : null,
            'version' => max(1, (int) ($raw['version'] ?? 1)),
            'mode' => $mode === self::MODE_LIMITED ? self::MODE_LIMITED : self::MODE_FULL,
            'chosen' => array_values(array_filter(array_map(static fn($m): string => is_scalar($m) ? (string) $m : '', (array) ($raw['chosen'] ?? [])), static fn(string $m): bool => $m !== '')),
            'applied_at' => isset($raw['applied_at']) && is_string($raw['applied_at']) ? $raw['applied_at'] : null,
            'applied_by' => isset($raw['applied_by']) && is_numeric($raw['applied_by']) ? (int) $raw['applied_by'] : null,
            'snapshot' => is_array($raw['snapshot'] ?? null) ? $raw['snapshot'] : [],
            'applied' => is_array($raw['applied'] ?? null) ? $raw['applied'] : [],
        ];
    }

    /**
     * Neuere Paketvorgabe als die übernommene (MVP-1195): wird nur angeboten,
     * nie automatisch angewendet (E8). null = keine neuere Version.
     */
    public function pendingPackageVersion(Organization $organization): ?int {
        $profile = $this->profile($organization);
        if ($profile['source'] !== 'package' || $profile['key'] === null) {
            return null;
        }
        $package = $this->packages->package($profile['key']);
        if ($package === null || $package['version'] <= $profile['version']) {
            return null;
        }

        return $package['version'];
    }

    // ── Erweiterung und Rücknahme (MVP-1195/1196) ───────────────────────

    /**
     * Module, deren DATEV-/Banking-Funktionen das private Paket
     * php-financial-formats brauchen (Hinweis „zusätzliches Paket nötig").
     * Die Verfügbarkeit prüft die Seite selbst — der Plattformdienst darf das
     * Kernmodul Billing nicht aufrufen (Modulgrenze).
     */
    public const REQUIRES_FINANCIAL_FORMATS = ['module.finance'];

    /**
     * Übersicht für „Funktionsumfang erweitern" (§7.2): je Modul ein Zustand
     * aus active | disabled | growth | not_licensed | blocked, dazu Pakete und
     * Bündel, die es enthalten, Abhängigkeiten, zugehörige kompakte Bereiche
     * und der Hinweis auf ein fehlendes Zusatzpaket.
     *
     * @return list<array{code: string, label: string, description: string, status: ModuleStatus, state: string, packages: list<string>, requires: list<string>, requires_missing: list<string>, workspaces: list<string>, chosen: bool}>
     */
    public function growthOverview(Organization $organization): array {
        $profile = $this->profile($organization);
        $growth = $this->growthModules($organization);
        $requirements = app(\App\Modules\ModuleRegistry::class)->licenseRequirements();
        $workspacesByModule = $this->workspacesByModule();
        $statusByCode = [];
        foreach ($this->status->forOrganization($organization) as $row) {
            $statusByCode[$row['code']] = $row['status'];
        }

        $out = [];
        foreach ($this->status->forOrganization($organization) as $row) {
            $code = $row['code'];
            $state = match ($row['status']) {
                ModuleStatus::Active => 'active',
                ModuleStatus::InactiveByCustomer => in_array($code, $growth, true) ? 'growth' : 'disabled',
                ModuleStatus::Blocked => 'blocked',
                ModuleStatus::NotLicensed => 'not_licensed',
            };
            $required = $requirements[$code] ?? [];
            $out[] = [
                'code' => $code,
                'label' => $row['label'],
                'description' => $row['description'],
                'status' => $row['status'],
                'state' => $state,
                'packages' => array_map(static fn(array $p): string => $p['label'], $this->packages->containing($code)),
                'requires' => $required,
                'requires_missing' => array_values(array_filter($required, static fn(string $r): bool => ($statusByCode[$r] ?? ModuleStatus::NotLicensed) !== ModuleStatus::Active)),
                'workspaces' => $workspacesByModule[$code] ?? [],
                'chosen' => in_array($code, $profile['chosen'], true),
            ];
        }

        return $out;
    }

    /**
     * Kompakte Bereiche (Schlüssel) je Modul: Bereiche mit Einzelzielen des
     * Moduls plus der Fachbereich seines Bündels.
     *
     * @return array<string, list<string>>
     */
    public function workspacesByModule(): array {
        $out = [];
        // Routenmuster der Manifeste (wie das Routen-Gate), nicht nur die
        // einzeln benannten Sidebar-Einträge — Sektionen gelten als Ganzes.
        $modules = app(\App\Modules\ModuleRegistry::class);
        foreach ($this->focus->all() as $key => $def) {
            foreach ((array) ($def['keys'] ?? []) as $ref) {
                $ref = (string) $ref;
                if (! str_starts_with($ref, 'item:')) {
                    continue;
                }
                $module = $modules->moduleForRoute(substr($ref, 5));
                if ($module !== null && ! in_array((string) $key, $out[$module] ?? [], true)) {
                    $out[$module][] = (string) $key;
                }
            }
        }
        foreach ($this->packages->bundles() as $bundle) {
            if ($bundle['workspace'] === null) {
                continue;
            }
            foreach ($bundle['modules'] as $module) {
                if (! in_array($bundle['workspace'], $out[$module] ?? [], true)) {
                    $out[$module][] = $bundle['workspace'];
                }
            }
        }

        return $out;
    }

    /**
     * Bewusste Aktivierung aus der Erweiterung: Modulstatus über
     * {@see enableModule()}, zugehörige Bereiche werden der Kuratierung nur
     * ergänzend hinzugefügt (nie ersetzt, nie bei „alle angeboten").
     *
     * @return array{enabled: bool, workspaces: list<string>}
     */
    public function activateModule(Organization $organization, string $code, User $actor, bool $offerWorkspaces = true): array {
        $enabled = $this->enableModule($organization, $code, $actor);
        $added = [];
        $settings = is_array($organization->settings) ? $organization->settings : [];
        $available = $settings[NavFocusService::SETTING_AVAILABLE] ?? null;
        if ($offerWorkspaces && is_array($available)) {
            $wanted = $this->workspacesByModule()[$code] ?? [];
            $added = array_values(array_diff($wanted, $available));
            if ($added !== []) {
                $labels = is_array($settings[NavFocusService::SETTING_LABELS] ?? null) ? $settings[NavFocusService::SETTING_LABELS] : [];
                $roles = is_array($settings[NavFocusService::SETTING_ROLES] ?? null) ? $settings[NavFocusService::SETTING_ROLES] : [];
                $this->focus->saveCuration(
                    $organization->fresh() ?? $organization,
                    array_values(array_merge($available, $added)),
                    (string) ($settings[NavFocusService::SETTING_DEFAULT] ?? 'all'),
                    $roles,
                    $labels,
                );
            }
        }

        return ['enabled' => $enabled, 'workspaces' => $added];
    }

    /**
     * Aktueller Oberflächenzustand in der Form des Bündel-Sollstands
     * (für den Konfliktvergleich der Rücknahme).
     *
     * @return array{modules: list<string>, workspaces: list<string>|null, default: ?string, roles: array<string, string>, startpages: array<string, string>}
     */
    public function currentState(Organization $organization): array {
        $settings = is_array($organization->settings) ? $organization->settings : [];
        $startpages = [];
        foreach (StartPageResolver::CONFIGURABLE_ROLES as $role) {
            $route = data_get($settings, StartPageResolver::settingKey($role));
            if (is_string($route) && $route !== '') {
                $startpages[$role->value] = $route;
            }
        }
        $modules = $this->activeModules($organization);
        sort($modules);
        $available = $settings[NavFocusService::SETTING_AVAILABLE] ?? null;
        $roles = is_array($settings[NavFocusService::SETTING_ROLES] ?? null) ? $settings[NavFocusService::SETTING_ROLES] : [];
        ksort($roles);
        ksort($startpages);

        return [
            'modules' => $modules,
            'workspaces' => is_array($available) ? array_values(array_map('strval', $available)) : null,
            'default' => isset($settings[NavFocusService::SETTING_DEFAULT]) && is_string($settings[NavFocusService::SETTING_DEFAULT]) ? $settings[NavFocusService::SETTING_DEFAULT] : null,
            'roles' => $roles,
            'startpages' => $startpages,
        ];
    }

    /**
     * Vorschau der Rücknahme (§7.3): was der Schnappschuss wiederherstellt
     * und welche Dimensionen seit der Übernahme manuell geändert wurden
     * (aktueller Stand ≠ Sollstand des Bündels) — Konflikte werden nur auf
     * ausdrückliche Wahl überschrieben.
     *
     * @return array{available: bool, taken_at: ?string, conflicts: list<string>, changes: array<string, array{from: mixed, to: mixed}>}
     */
    public function revertPreview(Organization $organization): array {
        $profile = $this->profile($organization);
        $snapshot = $profile['snapshot'];
        if ($snapshot === []) {
            return ['available' => false, 'taken_at' => null, 'conflicts' => [], 'changes' => []];
        }
        $current = $this->currentState($organization);
        $applied = $profile['applied'];
        $licensed = $this->licensedModules($organization);

        $appliedModules = array_values(array_intersect(array_map('strval', (array) ($applied['chosen'] ?? [])), $licensed));
        sort($appliedModules);
        $appliedState = [
            'modules' => $appliedModules,
            'workspaces' => isset($applied['workspaces']) && is_array($applied['workspaces']) ? array_values(array_map('strval', $applied['workspaces'])) : null,
            'default' => isset($applied['default']) && is_string($applied['default']) ? $applied['default'] : null,
            'roles' => is_array($applied['roles'] ?? null) ? $applied['roles'] : [],
            'startpages' => is_array($applied['startpages'] ?? null) ? $applied['startpages'] : [],
        ];
        ksort($appliedState['roles']);
        ksort($appliedState['startpages']);

        $overrides = is_array($snapshot['overrides'] ?? null) ? $snapshot['overrides'] : [];
        $snapshotModules = array_values(array_filter($licensed, static fn(string $m): bool => ! array_key_exists($m, $overrides)));
        sort($snapshotModules);
        $snapshotState = [
            'modules' => $snapshotModules,
            'workspaces' => isset($snapshot[NavFocusService::SETTING_AVAILABLE]) && is_array($snapshot[NavFocusService::SETTING_AVAILABLE]) ? array_values(array_map('strval', $snapshot[NavFocusService::SETTING_AVAILABLE])) : null,
            'default' => isset($snapshot[NavFocusService::SETTING_DEFAULT]) && is_string($snapshot[NavFocusService::SETTING_DEFAULT]) ? $snapshot[NavFocusService::SETTING_DEFAULT] : null,
            'roles' => is_array($snapshot[NavFocusService::SETTING_ROLES] ?? null) ? $snapshot[NavFocusService::SETTING_ROLES] : [],
            'startpages' => is_array($snapshot['startpages'] ?? null) ? $snapshot['startpages'] : [],
        ];
        ksort($snapshotState['roles']);
        ksort($snapshotState['startpages']);

        $conflicts = [];
        $changes = [];
        foreach (['modules', 'workspaces', 'default', 'roles', 'startpages'] as $dimension) {
            if ($applied !== [] && $current[$dimension] != $appliedState[$dimension]) {
                $conflicts[] = $dimension;
            }
            if ($current[$dimension] != $snapshotState[$dimension]) {
                $changes[$dimension] = ['from' => $current[$dimension], 'to' => $snapshotState[$dimension]];
            }
        }

        return [
            'available' => true,
            'taken_at' => isset($snapshot['taken_at']) && is_string($snapshot['taken_at']) ? $snapshot['taken_at'] : null,
            'conflicts' => $conflicts,
            'changes' => $changes,
        ];
    }

    /**
     * Rücknahme (MVP-1196): stellt Modulstatus, Kuratierung, Startseiten und
     * Profil aus dem Schnappschuss wieder her. Dimensionen mit Konflikt bleiben
     * unberührt, sofern `$overrideConflicts` nicht gesetzt ist. Nie
     * lizenzerweiternd: Das Löschen eines Overrides schaltet nur frei, was die
     * Lizenz hergibt. Audit `license.scopeReverted`.
     *
     * @return array{reverted: list<string>, skipped: list<string>}
     */
    public function revert(Organization $organization, User $actor, bool $overrideConflicts = false): array {
        $preview = $this->revertPreview($organization);
        if (! $preview['available']) {
            return ['reverted' => [], 'skipped' => []];
        }
        $profile = $this->profile($organization);
        $snapshot = $profile['snapshot'];
        $skipped = $overrideConflicts ? [] : $preview['conflicts'];
        $reverted = [];

        if (! in_array('modules', $skipped, true)) {
            $overrides = is_array($snapshot['overrides'] ?? null) ? $snapshot['overrides'] : [];
            foreach ($this->catalog->codes() as $code) {
                if (array_key_exists($code, $overrides)) {
                    LicenseFlagOverride::query()->firstOrCreate(
                        ['organization_id' => $organization->id, 'flag' => $code],
                        ['reason' => is_string($overrides[$code]) ? $overrides[$code] : null, 'disabled_at' => CarbonImmutable::now(), 'disabled_by_user_id' => $actor->id]
                    );
                } else {
                    LicenseFlagOverride::query()->where('organization_id', $organization->id)->where('flag', $code)->delete();
                }
            }
            $reverted[] = 'modules';
        }

        $settings = is_array($organization->settings) ? $organization->settings : [];
        $curation = [
            'available' => in_array('workspaces', $skipped, true) ? ($settings[NavFocusService::SETTING_AVAILABLE] ?? null) : ($snapshot[NavFocusService::SETTING_AVAILABLE] ?? null),
            'default' => in_array('default', $skipped, true) ? ($settings[NavFocusService::SETTING_DEFAULT] ?? 'all') : ($snapshot[NavFocusService::SETTING_DEFAULT] ?? 'all'),
            'roles' => in_array('roles', $skipped, true) ? ($settings[NavFocusService::SETTING_ROLES] ?? []) : ($snapshot[NavFocusService::SETTING_ROLES] ?? []),
            'labels' => $snapshot[NavFocusService::SETTING_LABELS] ?? ($settings[NavFocusService::SETTING_LABELS] ?? []),
        ];
        $this->focus->saveCuration(
            $organization,
            is_array($curation['available']) ? array_values(array_map('strval', $curation['available'])) : null,
            is_string($curation['default']) ? $curation['default'] : 'all',
            is_array($curation['roles']) ? $curation['roles'] : [],
            is_array($curation['labels']) ? $curation['labels'] : [],
        );
        foreach (['workspaces', 'default', 'roles'] as $dimension) {
            if (! in_array($dimension, $skipped, true)) {
                $reverted[] = $dimension;
            }
        }

        if (! in_array('startpages', $skipped, true)) {
            $startpages = is_array($snapshot['startpages'] ?? null) ? $snapshot['startpages'] : [];
            $routes = StartPageResolver::routeOptions();
            foreach (StartPageResolver::CONFIGURABLE_ROLES as $role) {
                $route = $startpages[$role->value] ?? null;
                if (is_string($route) && in_array($route, $routes, true)) {
                    Setting::set(StartPageResolver::settingKey($role), $route, SettingScope::Organization, $organization, $actor->id);
                } else {
                    Setting::reset(StartPageResolver::settingKey($role), SettingScope::Organization, $organization);
                }
            }
            $reverted[] = 'startpages';
        }

        $before = is_array($snapshot['profile'] ?? null) ? $snapshot['profile'] : [];
        $restored = $this->profile($organization->fresh() ?? $organization);
        $restored['source'] = isset($before['source']) && is_string($before['source']) ? $before['source'] : null;
        $restored['key'] = isset($before['key']) && is_string($before['key']) ? $before['key'] : null;
        $restored['version'] = max(1, (int) ($before['version'] ?? 1));
        $restored['mode'] = (($before['mode'] ?? self::MODE_FULL) === self::MODE_LIMITED) ? self::MODE_LIMITED : self::MODE_FULL;
        $restored['chosen'] = array_values(array_map('strval', (array) ($before['chosen'] ?? [])));
        $restored['applied_at'] = CarbonImmutable::now()->toIso8601String();
        $restored['applied_by'] = $actor->id;
        $restored['snapshot'] = [];
        $restored['applied'] = [];
        $this->writeProfile($organization->fresh() ?? $organization, $restored);
        $this->resolver->flush();

        AuditLog::query()->create([
            'organization_id' => $organization->id,
            'user_id' => $actor->id,
            'event' => 'license.scopeReverted',
            'auditable_type' => MorphMap::stableKey(Organization::class),
            'auditable_id' => $organization->id,
            'changes' => ['reverted' => $reverted, 'skipped' => $skipped, 'override_conflicts' => $overrideConflicts],
        ]);

        return ['reverted' => $reverted, 'skipped' => $skipped];
    }

    public function isLimited(Organization $organization): bool {
        return $this->profile($organization)['mode'] === self::MODE_LIMITED;
    }

    /**
     * Setzt den Geltungsmodus. Der Wechsel auf „bewusst begrenzt" friert die
     * aktuell aktiven Module als gewählte ein und gleicht sofort ab; der
     * Wechsel auf „voller Umfang" aktiviert nichts von selbst (E8) — er
     * lässt nur künftigen Lizenzzuwachs sofort wirken.
     */
    public function setMode(Organization $organization, string $mode, User $actor): void {
        $mode = $mode === self::MODE_LIMITED ? self::MODE_LIMITED : self::MODE_FULL;
        $profile = $this->profile($organization);
        if ($profile['mode'] === $mode) {
            return;
        }
        $profile['mode'] = $mode;
        if ($mode === self::MODE_LIMITED) {
            $profile['chosen'] = $this->activeModules($organization);
        }
        $profile['applied_at'] = CarbonImmutable::now()->toIso8601String();
        $profile['applied_by'] = $actor->id;
        $this->writeProfile($organization, $profile);
        $this->auditScope($organization, $actor, ['mode' => $mode, 'chosen' => $profile['chosen']]);

        if ($mode === self::MODE_LIMITED) {
            $this->reconcile($organization, $actor);
        }
    }

    /**
     * Module, die bei Lizenzzuwachs deaktiviert wurden und auf eine bewusste
     * Aktivierung warten (Override mit Grund `scope:growth`).
     *
     * @return list<string>
     */
    public function growthModules(Organization $organization): array {
        return array_values(LicenseFlagOverride::query()
            ->where('organization_id', $organization->id)
            ->where('reason', self::REASON_GROWTH)
            ->orderBy('flag')
            ->pluck('flag')
            ->map(static fn($v): string => (string) $v)
            ->all());
    }

    /**
     * Abgleich bei Lizenzzuwachs (E2): Im Modus „bewusst begrenzt" bekommt
     * jedes lizenzierte, aktive, aber nicht gewählte Modul einen Override
     * mit Grund `scope:growth`. Idempotent, ohne Schreiben am Profil (läuft
     * auch im Organisations-Observer), im Vollumfang ein No-op.
     *
     * @return list<string> Neu deaktivierte Module
     */
    public function reconcile(Organization $organization, ?User $actor = null): array {
        $profile = $this->profile($organization);
        if ($profile['mode'] !== self::MODE_LIMITED) {
            return [];
        }

        $disabled = [];
        foreach ($this->status->forOrganization($organization) as $row) {
            if ($row['status'] !== ModuleStatus::Active || in_array($row['code'], $profile['chosen'], true)) {
                continue;
            }
            $created = LicenseFlagOverride::query()->firstOrCreate(
                ['organization_id' => $organization->id, 'flag' => $row['code']],
                [
                    'reason' => self::REASON_GROWTH,
                    'disabled_at' => CarbonImmutable::now(),
                    'disabled_by_user_id' => $actor?->id,
                ]
            );
            if ($created->wasRecentlyCreated) {
                $this->auditModule($organization, $actor, 'license.moduleDisabled', $row['code'], self::REASON_GROWTH);
                $disabled[] = $row['code'];
            }
        }

        if ($disabled !== []) {
            $this->resolver->flush();
        }

        return $disabled;
    }

    /**
     * Einzelnes Modul bewusst aktivieren (Einzelschaltung der Lizenzseite und
     * der Erweiterung): entfernt nur den Org-Override — plattformweite
     * bleiben dem Plattform-Admin — und nimmt das Modul in die gewählten auf.
     */
    public function enableModule(Organization $organization, string $code, User $actor): bool {
        if (! $this->catalog->has($code)) {
            return false;
        }
        $deleted = LicenseFlagOverride::query()
            ->where('organization_id', $organization->id)
            ->where('flag', $code)
            ->delete();
        if ($deleted > 0) {
            $this->auditModule($organization, $actor, 'license.moduleEnabled', $code, null);
        }
        $this->rememberChoice($organization, $code, true);
        $this->resolver->flush();

        return $deleted > 0;
    }

    /**
     * Einzelnes Modul bewusst deaktivieren: nur lizenzierte, aktive Module
     * (MVP-052 Akzeptanz 2), keine Datenlöschung, keine Karenz.
     */
    public function disableModule(Organization $organization, string $code, User $actor, ?string $reason = null): bool {
        if (! $this->status->statusFor($organization, $code)->isConfigurable()) {
            return false;
        }
        $override = LicenseFlagOverride::query()->firstOrCreate(
            ['organization_id' => $organization->id, 'flag' => $code],
            [
                'reason' => $reason,
                'disabled_at' => CarbonImmutable::now(),
                'disabled_by_user_id' => $actor->id,
            ]
        );
        $this->auditModule($organization, $actor, 'license.moduleDisabled', $code, $reason);
        $this->rememberChoice($organization, $code, false);
        $this->resolver->flush();

        return $override->wasRecentlyCreated;
    }

    /**
     * Wendet ein Preset an (Schreibhilfe für {@see setActiveModules}). Ein
     * Paket-Preset oder eine Modulliste schaltet in den Modus „bewusst
     * begrenzt", „Voller Umfang" (`modules = null`) in den Vollumfang.
     *
     * @return array{disabled: list<string>, enabled: list<string>}
     */
    public function applyPreset(Organization $organization, string $presetKey, User $actor): array {
        $presets = $this->presets();
        if (! isset($presets[$presetKey])) {
            throw new \InvalidArgumentException("Unbekanntes Funktionsumfang-Preset: {$presetKey}");
        }
        $preset = $presets[$presetKey];

        // Paket-Presets sind Einstiegsvorgaben: Module, Arbeitsbereiche und
        // Startseiten als Bündel (Feature 165, E7).
        if ($preset['package'] !== null) {
            return $this->applyBundle($organization, $this->bundleFor($organization, $presetKey, [], false), $actor, 'package', $presetKey);
        }

        $profile = $this->profile($organization);
        $profile['source'] = 'preset';
        $profile['key'] = $presetKey;
        $profile['version'] = 1;
        $profile['mode'] = $preset['modules'] === null ? self::MODE_FULL : self::MODE_LIMITED;
        $profile['applied_at'] = CarbonImmutable::now()->toIso8601String();
        $profile['applied_by'] = $actor->id;
        $this->writeProfile($organization, $profile);

        return $this->setActiveModules($organization, $preset['modules'], $actor, $presetKey);
    }

    // ── Einstiegsbündel (Feature 165, MVP-1189) ─────────────────────────

    /**
     * Paket der Organisation aus der Lizenz (`package` im Payload), sonst null.
     */
    public function packageFor(Organization $organization): ?string {
        $result = $this->licenses->forOrganization($organization);
        $package = $result->isUsable() ? $result->payload?->package : null;

        return is_string($package) && $this->packages->has($package) ? $package : null;
    }

    /**
     * Baut das Einstiegsbündel eines Pakets für die gewählten Ziele.
     *
     * Ziele: `orders` und `time` sind immer enthalten, `team` ergänzt den
     * Bereich „Team & Planung" nur mit lizenzierter Planung, `billing`
     * schaltet die kaufmännische Kuratierung (E3). Ein unbekanntes Paket
     * fällt auf „start" zurück.
     *
     * @param  list<string>  $goals
     * @return array{package: string, version: int, modules: list<string>, workspaces: list<string>, default: string, roles: array<string, string>, startpages: array<string, string>, commercial: bool, team: bool, team_licensed: bool}
     */
    public function bundleFor(Organization $organization, string $packageKey, array $goals, bool $commercial): array {
        $package = $this->packages->package($packageKey) ?? $this->packages->package('start');
        $packageKey = $package['key'] ?? 'start';
        $teamModules = $this->packages->modulesFor('team');
        $teamOnly = array_values(array_diff($teamModules, $this->packages->modulesFor('start')));
        $licensed = $this->licensedModules($organization);
        $teamLicensed = $teamOnly !== [] && array_diff($teamOnly, $licensed) === [];
        $wantsTeam = in_array('team', $goals, true);

        $modules = $package['default_active'] ?? ['module.vertrieb'];
        $workspaces = $package['workspaces'] ?? ['compact_day', 'compact_orders'];
        $roles = $package['focus_roles'] ?? [];
        $startpages = $package['startpages'] ?? [];
        if ($wantsTeam && $teamLicensed) {
            $modules = array_values(array_unique(array_merge($modules, $teamOnly)));
            $team = $this->packages->package('team');
            $workspaces = array_values(array_unique(array_merge($workspaces, $team['workspaces'] ?? ['compact_team'])));
            $roles = array_merge($roles, $team['focus_roles'] ?? []);
            $startpages = array_merge($startpages, $team['startpages'] ?? []);
        }
        $commercialWorkspace = $package['commercial_workspace'] ?? null;
        if ($commercial && $commercialWorkspace !== null) {
            $workspaces[] = $commercialWorkspace;
            $workspaces = array_values(array_unique($workspaces));
        }
        $workspaces = array_values(array_filter($workspaces, fn(string $key): bool => $this->focus->has($key)));
        $roles = array_filter($roles, static fn(string $key): bool => in_array($key, $workspaces, true));
        $default = in_array('compact_day', $workspaces, true) ? 'compact_day' : ($workspaces[0] ?? 'all');

        return [
            'package' => $packageKey,
            'version' => $package['version'] ?? 1,
            'modules' => $modules,
            'workspaces' => $workspaces,
            'default' => $default,
            'roles' => $roles,
            'startpages' => $startpages,
            'commercial' => $commercial,
            'team' => $wantsTeam,
            'team_licensed' => $teamLicensed,
        ];
    }

    /**
     * Wendet ein Einstiegsbündel an (E7): Schnappschuss des Vorzustands,
     * Profil (Modus „bewusst begrenzt"), Modulstatus über
     * {@see setActiveModules()}, Arbeitsbereiche über den NavFocusService,
     * Startseiten je Rolle über die Settings-Registry; ein Audit-Eintrag.
     * Persönliche Präferenzen bleiben unberührt.
     *
     * @param  array{package: string, version: int, modules: list<string>, workspaces: list<string>, default: string, roles: array<string, string>, startpages: array<string, string>, commercial: bool, team: bool, team_licensed: bool}  $bundle
     * @return array{disabled: list<string>, enabled: list<string>}
     */
    public function applyBundle(Organization $organization, array $bundle, User $actor, string $source, string $key): array {
        $profile = $this->profile($organization);
        $profile['snapshot'] = $this->snapshot($organization);
        $profile['source'] = $source;
        $profile['key'] = $key;
        $profile['version'] = $bundle['version'];
        $profile['mode'] = self::MODE_LIMITED;
        $profile['chosen'] = $bundle['modules'];
        $profile['applied_at'] = CarbonImmutable::now()->toIso8601String();
        $profile['applied_by'] = $actor->id;
        $profile['applied'] = [
            'chosen' => $bundle['modules'],
            'workspaces' => array_values(array_unique(array_merge($bundle['workspaces'], ['all']))),
            'default' => $bundle['default'],
            'roles' => $bundle['roles'],
            'startpages' => $bundle['startpages'],
        ];
        $this->writeProfile($organization, $profile);

        $result = $this->setActiveModules($organization, $bundle['modules'], $actor, $key);

        $settings = is_array($organization->settings) ? $organization->settings : [];
        $labels = is_array($settings[NavFocusService::SETTING_LABELS] ?? null) ? $settings[NavFocusService::SETTING_LABELS] : [];
        $this->focus->saveCuration($organization, $bundle['workspaces'], $bundle['default'], $bundle['roles'], $labels);

        $routes = StartPageResolver::routeOptions();
        foreach ($bundle['startpages'] as $role => $route) {
            $enum = UserRole::tryFrom($role);
            if ($enum === null || ! in_array($enum, StartPageResolver::CONFIGURABLE_ROLES, true) || ! in_array($route, $routes, true)) {
                continue;
            }
            Setting::set(StartPageResolver::settingKey($enum), $route, SettingScope::Organization, $organization, $actor->id);
        }

        $this->auditScope($organization, $actor, [
            'bundle' => $key,
            'source' => $source,
            'version' => $bundle['version'],
            'mode' => self::MODE_LIMITED,
            'modules' => $bundle['modules'],
            'workspaces' => $bundle['workspaces'],
            'commercial' => $bundle['commercial'],
        ]);

        return $result;
    }

    /**
     * Schlanker Standard für eine neu angelegte Organisation (§5.2): Paket aus
     * der Lizenz, sonst „start", jeweils innerhalb der tatsächlichen Lizenz.
     * Nur an den Anlagestellen aufrufen — nie rückwirkend auf Bestand.
     */
    public function applyPackageDefaults(Organization $organization, User $actor): void {
        $package = $this->packageFor($organization) ?? 'start';
        $this->applyBundle($organization, $this->bundleFor($organization, $package, [], false), $actor, 'package', $package);
    }

    /**
     * Vorzustand für die Rücknahme (MVP-1196): Org-Overrides, Kuratierung,
     * Startseiten je Rolle und das bisherige Profil.
     *
     * @return array<string, mixed>
     */
    public function snapshot(Organization $organization): array {
        $settings = is_array($organization->settings) ? $organization->settings : [];
        $overrides = [];
        foreach (LicenseFlagOverride::query()->where('organization_id', $organization->id)->orderBy('flag')->get() as $override) {
            $overrides[(string) $override->flag] = $override->reason;
        }
        $startpages = [];
        foreach (StartPageResolver::CONFIGURABLE_ROLES as $role) {
            $route = data_get($settings, StartPageResolver::settingKey($role));
            if (is_string($route) && $route !== '') {
                $startpages[$role->value] = $route;
            }
        }
        $profile = $this->profile($organization);

        return [
            'taken_at' => CarbonImmutable::now()->toIso8601String(),
            'overrides' => $overrides,
            NavFocusService::SETTING_AVAILABLE => $settings[NavFocusService::SETTING_AVAILABLE] ?? null,
            NavFocusService::SETTING_DEFAULT => $settings[NavFocusService::SETTING_DEFAULT] ?? null,
            NavFocusService::SETTING_ROLES => $settings[NavFocusService::SETTING_ROLES] ?? null,
            NavFocusService::SETTING_LABELS => $settings[NavFocusService::SETTING_LABELS] ?? null,
            'startpages' => $startpages,
            'profile' => ['source' => $profile['source'], 'key' => $profile['key'], 'version' => $profile['version'], 'mode' => $profile['mode'], 'chosen' => $profile['chosen']],
        ];
    }

    /**
     * Lizenzierte Module (unabhängig von Deaktivierungen).
     *
     * @return list<string>
     */
    public function licensedModules(Organization $organization): array {
        $out = [];
        foreach ($this->status->forOrganization($organization) as $row) {
            if ($row['licensed']) {
                $out[] = $row['code'];
            }
        }

        return $out;
    }

    /**
     * Setzt den aktiven Modulumfang der Organisation idempotent.
     *
     * @param  list<string>|null  $activeModules  Aktiv bleibende Katalogmodule;
     *                                            null = alle lizenzierten aktiv.
     * @return array{disabled: list<string>, enabled: list<string>}
     */
    public function setActiveModules(Organization $organization, ?array $activeModules, User $actor, ?string $presetKey = null): array {
        $active = $activeModules === null
            ? null
            : array_values(array_filter(array_map(static fn($m): string => (string) $m, $activeModules), fn(string $m): bool => $this->catalog->has($m)));

        $disabled = [];
        $enabled = [];
        $reason = $presetKey !== null ? ('scope:' . $presetKey) : 'scope';

        foreach ($this->status->forOrganization($organization) as $row) {
            $code = $row['code'];
            $shouldBeActive = $active === null || in_array($code, $active, true);

            if ($row['status'] === ModuleStatus::Active && ! $shouldBeActive) {
                // Nur lizenzierte, aktive Module deaktivieren (MVP-052 Akzeptanz 2).
                LicenseFlagOverride::query()->firstOrCreate(
                    ['organization_id' => $organization->id, 'flag' => $code],
                    [
                        'reason' => $reason,
                        'disabled_at' => CarbonImmutable::now(),
                        'disabled_by_user_id' => $actor->id,
                    ]
                );
                $this->auditModule($organization, $actor, 'license.moduleDisabled', $code, $reason);
                $disabled[] = $code;

                continue;
            }

            if ($row['status'] === ModuleStatus::InactiveByCustomer && $shouldBeActive) {
                // Nur org-eigene Overrides entfernen — plattformweite bleiben
                // dem Plattform-Admin vorbehalten (wie LicenseAdminController).
                $deleted = LicenseFlagOverride::query()
                    ->where('organization_id', $organization->id)
                    ->where('flag', $code)
                    ->delete();
                if ($deleted > 0) {
                    $this->auditModule($organization, $actor, 'license.moduleEnabled', $code, null);
                    $enabled[] = $code;
                }
            }
            // NotLicensed/Blocked: unberührt — ein Preset schaltet nie frei (Option A).
        }

        if ($disabled !== [] || $enabled !== [] || $presetKey !== null) {
            $this->auditScope($organization, $actor, [
                'preset' => $presetKey,
                'disabled' => $disabled,
                'enabled' => $enabled,
            ]);

            // Onboarding-Schritt `org.scope` (Feature 081): bewusste
            // Umfang-Entscheidung dokumentieren (auch „Voller Umfang").
            $settings = is_array($organization->settings) ? $organization->settings : [];
            $settings['scope_configured_at'] = CarbonImmutable::now()->toIso8601String();
            $organization->settings = $settings;
            $organization->save();
        }

        $this->resolver->flush();

        // Gewählte Module nachführen: Was jetzt aktiv ist, ist bewusst gewählt.
        // Eine explizite Liste zählt auch dann, wenn ein Modul (noch) nicht
        // lizenziert ist — es wird beim Lizenzzuwachs sofort aktiv.
        if ($this->isLimited($organization)) {
            $profile = $this->profile($organization);
            $profile['chosen'] = $active === null
                ? $this->activeModules($organization)
                : array_values(array_unique(array_merge($this->activeModules($organization), $active)));
            $this->writeProfile($organization, $profile);
        }

        return ['disabled' => $disabled, 'enabled' => $enabled];
    }

    /**
     * Aktuell aktive (lizenzierte, nicht deaktivierte) Module.
     *
     * @return list<string>
     */
    public function activeModules(Organization $organization): array {
        $out = [];
        foreach ($this->status->forOrganization($organization) as $row) {
            if ($row['status'] === ModuleStatus::Active) {
                $out[] = $row['code'];
            }
        }

        return $out;
    }

    /** @param array{source: ?string, key: ?string, version: int, mode: string, chosen: list<string>, applied_at: ?string, applied_by: ?int, snapshot: array<string, mixed>} $profile */
    public function writeProfile(Organization $organization, array $profile): void {
        $settings = is_array($organization->settings) ? $organization->settings : [];
        $settings[self::SETTING_PROFILE] = $profile;
        $organization->settings = $settings;
        $organization->save();
    }

    private function rememberChoice(Organization $organization, string $code, bool $chosen): void {
        if (! $this->isLimited($organization)) {
            return;
        }
        $profile = $this->profile($organization);
        $list = array_values(array_filter($profile['chosen'], static fn(string $m): bool => $m !== $code));
        if ($chosen) {
            $list[] = $code;
        }
        $profile['chosen'] = $list;
        $this->writeProfile($organization, $profile);
    }

    /** @param array<string, mixed> $changes */
    private function auditScope(Organization $organization, User $actor, array $changes): void {
        AuditLog::query()->create([
            'organization_id' => $organization->id,
            'user_id' => $actor->id,
            'event' => 'license.scopeConfigured',
            'auditable_type' => MorphMap::stableKey(Organization::class),
            'auditable_id' => $organization->id,
            'changes' => $changes,
        ]);
    }

    private function auditModule(Organization $organization, ?User $actor, string $event, string $module, ?string $reason): void {
        AuditLog::query()->create([
            'organization_id' => $organization->id,
            'user_id' => $actor?->id,
            'event' => $event,
            'auditable_type' => MorphMap::stableKey(Organization::class),
            'auditable_id' => $organization->id,
            'changes' => [
                'module' => $module,
                'reason' => $reason,
            ],
        ]);
    }
}
