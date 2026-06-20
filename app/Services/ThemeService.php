<?php
/*
 * Created on   : Sat Jun 20 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ThemeService.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Services;

use App\Models\{Organization, User};
use App\Support\ThemeDefinition;
use Illuminate\Support\Facades\Auth;

/**
 * Zentrale Quelle für das aktive Theme und die verfügbaren Themes.
 *
 * Parallel zu BrandingService aufgebaut (gleicher View-Composer-Mechanismus,
 * als `$theme` in die Layouts injiziert). Zuständig für:
 *
 *   - die im Picker wählbaren Themes (Built-in aus config/theme.php +
 *     Org-Custom aus organizations.settings['theme']['custom']);
 *   - die flackerfreie Auflösung des aktiven Theme-Tokens (User → Org → auto);
 *   - das CSS der Org-Custom-Themes (`[data-theme="org-<key>"]{…}`) und den
 *     Seed fürs Inline-Anti-Flash-Skript im <head>.
 *
 * Custom-Themes werden NICHT hart gegatet (Downgrade darf bestehende Themes
 * nicht zerstören) — nur das Erstellen läuft über das Plan-Gating der
 * admin.themes.*-Routen (config/plans.php → module.theming).
 */
class ThemeService {
    /** @var array<int, ThemeDefinition>|null */
    private ?array $customCache = null;

    public function __construct(private readonly BrandingService $branding) {}

    public function currentOrganization(): ?Organization {
        return $this->branding->currentOrganization();
    }

    /**
     * Built-in-Themes aus der Config. @return array<int, array{key:string,label:string,scheme:string}>
     */
    public function builtinThemes(): array {
        /** @var array<string, array{label?:string,scheme?:string}> $builtin */
        $builtin = (array) config('theme.builtin', []);
        $out = [];
        foreach ($builtin as $key => $meta) {
            $out[] = [
                'key' => (string) $key,
                'label' => (string) ($meta['label'] ?? ucfirst((string) $key)),
                'scheme' => ($meta['scheme'] ?? 'light') === 'dark' ? 'dark' : 'light',
            ];
        }

        return $out;
    }

    /**
     * Validierte Custom-Theme-Definitionen der aktuellen Organisation.
     *
     * @return array<int, ThemeDefinition>
     */
    public function customDefinitions(): array {
        if ($this->customCache !== null) {
            return $this->customCache;
        }

        $org = $this->currentOrganization();
        /** @var array<string, mixed> $themeSettings */
        $themeSettings = is_array($org?->settings['theme'] ?? null) ? $org->settings['theme'] : [];
        /** @var array<int, mixed> $rawList */
        $rawList = is_array($themeSettings['custom'] ?? null) ? array_values($themeSettings['custom']) : [];

        $defs = [];
        $seen = [];
        foreach ($rawList as $raw) {
            if (! is_array($raw)) {
                continue;
            }
            $def = ThemeDefinition::fromArray($raw);
            if ($def === null || isset($seen[$def->key])) {
                continue; // ungültige oder doppelte Keys überspringen
            }
            $seen[$def->key] = true;
            $defs[] = $def;
        }

        return $this->customCache = $defs;
    }

    /**
     * Alle im Picker wählbaren Themes (Built-in + Org-Custom), ohne `auto`.
     *
     * @return array<int, array{key:string,label:string,scheme:string}>
     */
    public function availableThemes(): array {
        $themes = $this->builtinThemes();
        foreach ($this->customDefinitions() as $def) {
            $themes[] = $def->toPickerEntry();
        }

        return $themes;
    }

    /**
     * Erlaubte Theme-Tokens (inkl. `auto`) — für Validierung & Seed-Allowlist.
     *
     * @return array<int, string>
     */
    public function allowedKeys(): array {
        $keys = ['auto'];
        foreach ($this->availableThemes() as $theme) {
            $keys[] = $theme['key'];
        }

        return $keys;
    }

    /** Scheme ('light'|'dark') eines Theme-Tokens, oder null wenn unbekannt. */
    public function schemeOf(string $token): ?string {
        foreach ($this->availableThemes() as $theme) {
            if ($theme['key'] === $token) {
                return $theme['scheme'];
            }
        }

        return null;
    }

    /**
     * Org-Default-Theme für hellen System-Modus (settings['theme']['default_light']).
     * Fällt auf das Built-in aus config('theme.auto.light') zurück, wenn nicht
     * gesetzt oder kein gültiges Hell-Theme.
     */
    public function organizationDefaultLight(): string {
        return $this->resolveOrgDefault('default_light', 'light', (string) config('theme.auto.light', 'corporate'));
    }

    /** Org-Default-Theme für dunklen System-Modus (settings['theme']['default_dark']). */
    public function organizationDefaultDark(): string {
        return $this->resolveOrgDefault('default_dark', 'dark', (string) config('theme.auto.dark', 'dim'));
    }

    private function resolveOrgDefault(string $key, string $scheme, string $fallback): string {
        $org = $this->currentOrganization();
        $token = is_string($org?->settings['theme'][$key] ?? null) ? $org->settings['theme'][$key] : null;

        return $token !== null && $this->schemeOf($token) === $scheme ? $token : $fallback;
    }

    /**
     * Aktiver Theme-Token. Priorität:
     *   1. Konkretes persönliches Theme (Profil-Picker, preferences['theme']).
     *   2. Farbmodus des Header-Umschalters (preferences['color_scheme']):
     *      'light' → Org-Hell-Theme, 'dark' → Org-Dunkel-Theme.
     *   3. 'auto' → folgt dem System (Inline-Skript wählt autoLight/autoDark).
     *
     * So bestimmt die Themes-Seite (Org-Paar), WELCHES Theme hell/dunkel ist,
     * und der Header schaltet nur zwischen den beiden um — ohne dass man auf
     * einem festen Theme „hängen bleibt".
     */
    public function resolveActiveKey(): string {
        $user = Auth::user();
        if ($user instanceof User) {
            $prefs = (array) ($user->preferences ?? []);

            $theme = is_string($prefs['theme'] ?? null) ? $prefs['theme'] : null;
            if ($theme !== null && in_array($theme, $this->allowedKeys(), true)) {
                return $theme;
            }

            $scheme = is_string($prefs['color_scheme'] ?? null) ? $prefs['color_scheme'] : null;
            if ($scheme === 'light') {
                return $this->organizationDefaultLight();
            }
            if ($scheme === 'dark') {
                return $this->organizationDefaultDark();
            }
        }

        return 'auto';
    }

    /** CSS aller Org-Custom-Themes (eine `[data-theme]`-Regel je Theme). */
    public function customThemesCss(): string {
        $css = '';
        foreach ($this->customDefinitions() as $def) {
            $css .= $def->toCss();
        }

        return $css;
    }

    /**
     * Seed-Daten fürs Inline-Anti-Flash-Skript im <head>.
     *
     * @return array{authenticated:bool,active:string,autoLight:string,autoDark:string,schemes:array<string,string>,allowed:array<int,string>}
     */
    public function seed(): array {
        $schemes = [];
        foreach ($this->availableThemes() as $theme) {
            $schemes[$theme['key']] = $theme['scheme'];
        }

        return [
            'authenticated' => Auth::check(),
            'active' => $this->resolveActiveKey(),
            // 'auto' nutzt das org-eigene Hell/Dunkel-Paar (Fallback corporate/dim).
            'autoLight' => $this->organizationDefaultLight(),
            'autoDark' => $this->organizationDefaultDark(),
            'schemes' => $schemes,
            'allowed' => $this->allowedKeys(),
        ];
    }

    /** Cache zurücksetzen (nach Änderung der Org-Themes im selben Request). */
    public function flush(): void {
        $this->customCache = null;
    }
}
