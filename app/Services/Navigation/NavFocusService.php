<?php
/*
 * Created on   : Wed Jul 15 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : NavFocusService.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Navigation;

use App\Models\{Organization, User, UserWorkspace};
use Illuminate\Support\Facades\Auth;

/**
 * Arbeitsbereiche — schaltbare, rein kosmetische Fokus-Ansichten (D13): filtert
 * in {@see NavigationRegistry::build()} nur die sichtbare Menüauswahl, schaltet
 * nie Rechte/Module frei. Kapselt Definition (config/navigation_focus.php),
 * Org-Kuratierung und Auflösung (Session → Preference → Org-Default → Config,
 * jeder Wert gegen die verfügbaren Bereiche validiert).
 * Seit MVP-731 (Phase 2) kommen **eigene Arbeitsbereiche** dazu: Zeilen der
 * Tabelle `user_workspaces`, angeboten unter dem Schlüssel `user:<sqid>`.
 * Sie stehen gleichberechtigt neben den Produkt-Bereichen, sind aber immer
 * nur für ihren Besitzer sichtbar — und filtern ausschließlich die Sidebar
 * (das Verwaltungsmenü bleibt unangetastet, sonst verschwände es komplett).
 * Konzept: Feature 082 (WorkDiary-Architecture).
 */
class NavFocusService {
    /** Per-User-Preference (users.preferences) mit dem gewählten Arbeitsbereich. */
    public const PREFERENCE_KEY = 'nav_focus';

    /** Session-Spiegel der Fokus-Wahl (überlebt bis zum nächsten Wechsel). */
    public const SESSION_KEY = 'nav_focus';

    /** Org-Einstellung (organizations.settings): Default-Arbeitsbereich. */
    public const SETTING_DEFAULT = 'nav_focus_default';

    /** Org-Einstellung: Whitelist der angebotenen Arbeitsbereich-Schlüssel. */
    public const SETTING_AVAILABLE = 'nav_focus_available';

    /** Org-Einstellung: Label-Overrides (Schlüssel → eigener Anzeigename). */
    public const SETTING_LABELS = 'nav_focus_labels';

    /** Org-Einstellung: Zeitpunkt der Arbeitsbereich-Kuratierung (Onboarding). */
    public const SETTING_CONFIGURED_AT = 'nav_focus_configured_at';

    /** Schlüsselpräfix eines eigenen Arbeitsbereichs (`user:<sqid>`). */
    public const PERSONAL_PREFIX = 'user:';

    /** Schlüssel eines eigenen Arbeitsbereichs. */
    public static function personalKey(UserWorkspace $workspace): string {
        return self::PERSONAL_PREFIX . $workspace->sqid;
    }

    public static function isPersonalKey(string $key): bool {
        return str_starts_with($key, self::PERSONAL_PREFIX);
    }

    /**
     * Eigene Arbeitsbereiche der Person (leer ohne Person).
     *
     * @return list<UserWorkspace>
     */
    public function personalFor(?User $user): array {
        if (! $user instanceof User) {
            return [];
        }

        return array_values(UserWorkspace::query()->forUser($user)->get()->all());
    }

    /** Eigener Arbeitsbereich zum Schlüssel — nur der eigene, nie ein fremder. */
    public function personalByKey(?User $user, string $key): ?UserWorkspace {
        if (! self::isPersonalKey($key)) {
            return null;
        }
        foreach ($this->personalFor($user) as $workspace) {
            if (self::personalKey($workspace) === $key) {
                return $workspace;
            }
        }

        return null;
    }

    /** @return array<string, array<string, mixed>> Rohdefinitionen aus der Config. */
    public function all(): array {
        /** @var array<string, array<string, mixed>> $focuses */
        $focuses = (array) config('navigation_focus.focuses', []);

        return $focuses;
    }

    public function has(string $key): bool {
        return array_key_exists($key, $this->all());
    }

    /** Konfigurierter Default (fällt auf 'all' zurück, wenn ungültig). */
    public function configDefault(): string {
        $default = (string) config('navigation_focus.default', 'all');

        return $this->has($default) ? $default : 'all';
    }

    /**
     * Sichtbar bleibende Sidebar-Schlüssel des Fokus; `null` = kein Filter
     * (voller Umfang, z. B. Arbeitsbereich 'all' oder unbekannter Schlüssel).
     *
     * @return list<string>|null
     */
    public function keepKeys(string $key): ?array {
        if (self::isPersonalKey($key)) {
            // Eigener Arbeitsbereich: die gespeicherte Auswahl. Eine leere
            // Auswahl filtert bewusst NICHT (sonst wäre die Sidebar leer).
            $workspace = $this->personalByKey($this->currentUser(), $key);
            $keys = $workspace?->keys() ?? [];

            return $keys !== [] ? $keys : null;
        }

        $keys = $this->all()[$key]['keys'] ?? null;

        return is_array($keys) ? array_values(array_map('strval', $keys)) : null;
    }

    /**
     * Sichtbar bleibende Verwaltungsmenü-Routen (MVP-380); `null` = unverändert.
     *
     * @return list<string>|null
     */
    public function manageKeep(string $key): ?array {
        if (self::isPersonalKey($key)) {
            // Eigene Arbeitsbereiche kuratieren nur die Sidebar; das
            // Verwaltungsmenü bliebe sonst leer.
            return null;
        }

        $manage = $this->all()[$key]['manage'] ?? null;

        return is_array($manage) ? array_values(array_map('strval', $manage)) : null;
    }

    /**
     * Für die Organisation verfügbare Arbeitsbereiche in Anzeigereihenfolge.
     * Ohne Kuratierung: alle Produkt-Defaults. 'all' ist immer enthalten.
     * Danach die eigenen Arbeitsbereiche der Person (MVP-731) — sie sind
     * nicht kuratierbar, weil sie niemandem sonst gehören.
     *
     * @return list<array{key: string, label: string, description: string, icon: string, personal: bool}>
     */
    public function availableFor(?Organization $organization, ?User $user = null): array {
        $settings = $this->settings($organization);
        $whitelist = $settings[self::SETTING_AVAILABLE] ?? null;
        $labels = is_array($settings[self::SETTING_LABELS] ?? null) ? $settings[self::SETTING_LABELS] : [];

        $out = [];
        foreach ($this->all() as $key => $def) {
            $curated = is_array($whitelist);
            if ($key !== 'all' && $curated && ! in_array($key, $whitelist, true)) {
                continue;
            }
            $out[] = [
                'key' => (string) $key,
                'label' => $this->labelFrom($def, $labels, (string) $key),
                'description' => __((string) ($def['description'] ?? '')),
                'icon' => (string) ($def['icon'] ?? 'apps'),
                'personal' => false,
            ];
        }

        foreach ($this->personalFor($user ?? $this->currentUser()) as $workspace) {
            $out[] = [
                'key' => self::personalKey($workspace),
                'label' => (string) $workspace->name,
                'description' => (string) __('scope.focus.personal.description'),
                'icon' => (string) ($workspace->icon ?: 'dashboard_customize'),
                'personal' => true,
            ];
        }

        return $out;
    }

    public function isAvailableFor(?Organization $organization, string $key): bool {
        foreach ($this->availableFor($organization) as $focus) {
            if ($focus['key'] === $key) {
                return true;
            }
        }

        return false;
    }

    /** Anzeigename eines Arbeitsbereichs (Org-Label-Override vor Config-Label). */
    public function label(?Organization $organization, string $key): string {
        if (self::isPersonalKey($key)) {
            $workspace = $this->personalByKey($this->currentUser(), $key);

            return $workspace !== null ? (string) $workspace->name : (string) __('scope.focus.personal.title');
        }

        $settings = $this->settings($organization);
        $labels = is_array($settings[self::SETTING_LABELS] ?? null) ? $settings[self::SETTING_LABELS] : [];
        $def = $this->all()[$key] ?? [];

        return $this->labelFrom($def, $labels, $key);
    }

    public function icon(string $key): string {
        if (self::isPersonalKey($key)) {
            $workspace = $this->personalByKey($this->currentUser(), $key);
            $icon = $workspace !== null ? trim((string) $workspace->icon) : '';

            return $icon !== '' ? $icon : 'dashboard_customize';
        }

        return (string) ($this->all()[$key]['icon'] ?? 'apps');
    }

    /** Angemeldete Person (oder null) — die eigenen Bereiche hängen an ihr. */
    private function currentUser(): ?User {
        $user = Auth::user();

        return $user instanceof User ? $user : null;
    }

    /**
     * Default-Arbeitsbereich der Organisation:
     * Org-Einstellung → Branchenprofil-Vorschlag → Config-Default.
     */
    public function defaultFor(?Organization $organization): string {
        $orgDefault = $this->settings($organization)[self::SETTING_DEFAULT] ?? null;
        if (is_string($orgDefault) && $this->isAvailableFor($organization, $orgDefault)) {
            return $orgDefault;
        }

        $branch = $this->branchProfileDefault($organization);
        if ($branch !== null && $this->isAvailableFor($organization, $branch)) {
            return $branch;
        }

        return $this->configDefault();
    }

    /**
     * Vom Branchenprofil empfohlener Default (Feld `nav_focus_default` der
     * Profildatei) — nur ein Vorschlag (D16), greift nur ohne Org-Default.
     */
    private function branchProfileDefault(?Organization $organization): ?string {
        $code = $this->settings($organization)['branch_profile_code'] ?? null;
        if (! is_string($code) || $code === '') {
            return null;
        }

        $path = database_path("data/branchprofiles/{$code}.php");
        if (! is_file($path)) {
            return null;
        }

        /** @var array<string, mixed> $profile */
        $profile = require $path;
        $default = $profile['nav_focus_default'] ?? null;

        return is_string($default) && $this->has($default) ? $default : null;
    }

    /**
     * Aktiver Fokus für den Nutzer, validiert gegen die verfügbaren Bereiche:
     * Session → Nutzer-Preference → Org-Default → Config-Default.
     */
    public function resolveActive(?User $user, ?Organization $organization, ?string $sessionValue): string {
        if (is_string($sessionValue) && $this->isAvailableFor($organization, $sessionValue)) {
            return $sessionValue;
        }

        $pref = $user?->getPreference(self::PREFERENCE_KEY);
        if (is_string($pref) && $this->isAvailableFor($organization, $pref)) {
            return $pref;
        }

        return $this->defaultFor($organization);
    }

    /**
     * @param array<string, mixed> $def
     * @param array<string, mixed> $labels
     */
    private function labelFrom(array $def, array $labels, string $key): string {
        $override = $labels[$key] ?? null;
        if (is_string($override) && $override !== '') {
            return $override;
        }

        return __((string) ($def['label'] ?? $key));
    }

    /** @return array<string, mixed> */
    private function settings(?Organization $organization): array {
        return is_array($organization?->settings) ? $organization->settings : [];
    }
}
