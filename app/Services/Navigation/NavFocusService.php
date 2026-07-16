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

use App\Models\{Organization, User};

/**
 * Arbeitsbereiche — schaltbare Fokus-Ansichten (Feature 082, MVP-377).
 *
 * Kapselt Definition (config/navigation_focus.php), Org-Kuratierung (MVP-379)
 * und Auflösung des aktiven Fokus. Der Fokus ist REIN KOSMETISCH (D13): er wird
 * in {@see NavigationRegistry::build()} als letzter Filterschritt angewandt und
 * kann nie etwas freischalten oder eine Rechte-/Modulgrenze überschreiben.
 *
 * Auflösungsreihenfolge des aktiven Fokus: Session → Nutzer-Preference →
 * Org-Default → Config-Default. Jeder Wert wird gegen die für die Organisation
 * verfügbaren Bereiche validiert; ungültige Werte fallen auf den Default zurück.
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
        $keys = $this->all()[$key]['keys'] ?? null;

        return is_array($keys) ? array_values(array_map('strval', $keys)) : null;
    }

    /**
     * Sichtbar bleibende Verwaltungsmenü-Routen (MVP-380); `null` = unverändert.
     *
     * @return list<string>|null
     */
    public function manageKeep(string $key): ?array {
        $manage = $this->all()[$key]['manage'] ?? null;

        return is_array($manage) ? array_values(array_map('strval', $manage)) : null;
    }

    /**
     * Für die Organisation verfügbare Arbeitsbereiche in Anzeigereihenfolge.
     * Ohne Kuratierung: alle Produkt-Defaults. 'all' ist immer enthalten.
     *
     * @return list<array{key: string, label: string, description: string, icon: string}>
     */
    public function availableFor(?Organization $organization): array {
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
        $settings = $this->settings($organization);
        $labels = is_array($settings[self::SETTING_LABELS] ?? null) ? $settings[self::SETTING_LABELS] : [];
        $def = $this->all()[$key] ?? [];

        return $this->labelFrom($def, $labels, $key);
    }

    public function icon(string $key): string {
        return (string) ($this->all()[$key]['icon'] ?? 'apps');
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
     * Vom installierten Branchenprofil empfohlener Default-Arbeitsbereich
     * (optionales Feld `nav_focus_default` in der Profildatei). Nur ein Vorschlag
     * (D16) — greift ausschließlich, solange die Org keinen eigenen Default setzt.
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

    /** @param array<string, mixed> $def @param array<string, mixed> $labels */
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
