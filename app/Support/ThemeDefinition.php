<?php
/*
 * Created on   : Sat Jun 20 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ThemeDefinition.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Support;

/**
 * Sanitisiertes Value-Object für ein org-eigenes Custom-Theme.
 *
 * Ein DaisyUI-v5-Theme ist eine `[data-theme="x"]{…}`-Regel mit einem festen
 * Satz CSS-Custom-Properties. Dieses Objekt nimmt rohen Admin-Input entgegen,
 * lässt ausschließlich bekannte Properties + valide Werte durch (Allowlist —
 * niemals Blocklist) und rendert daraus garantiert injection-freies CSS:
 *
 *   - Farben nur über Color::normalizeHex() (`#` + 6 Hex-Zeichen).
 *   - scheme ∈ {light, dark}.
 *   - Geometrie nur als `<zahl>rem|px` (oder `0`).
 *   - key nur `[a-z0-9-]{1,32}` (geht in Selektor + localStorage).
 *
 * Fehlende `*-content`-Farben werden per Kontrast aus der Hintergrundfarbe
 * abgeleitet (Color::contrastContent), sodass der Admin nur die 7 Pflichtfarben
 * + scheme angeben muss.
 */
final class ThemeDefinition {
    /** Pflicht-Hintergrundfarben (ohne diese ist das Theme ungültig). */
    public const REQUIRED_COLORS = ['base-100', 'base-200', 'base-300', 'primary', 'secondary', 'accent', 'neutral'];

    /** Status-Farben mit Fallback, falls der Admin sie nicht setzt. */
    public const STATUS_DEFAULTS = [
        'info' => '#0ea5e9',
        'success' => '#16a34a',
        'warning' => '#f59e0b',
        'error' => '#dc2626',
    ];

    /** Farben, für die ein `<name>-content`-Token existiert (abgeleitet, falls leer). */
    private const CONTENT_BASES = ['primary', 'secondary', 'accent', 'neutral', 'info', 'success', 'warning', 'error'];

    /** Erlaubte Geometrie-Tokens (Werte gegen UNIT_PATTERN geprüft). */
    private const GEOMETRY_KEYS = ['radius-box', 'radius-field', 'radius-selector', 'border'];

    private const KEY_PATTERN = '/^[a-z0-9-]{1,32}$/';

    private const UNIT_PATTERN = '/^(0|[0-9]{1,2}(\.[0-9]{1,3})?(rem|px))$/';

    /**
     * @param array<string, string> $colors normalisierte Farb-Tokens (ohne `--color-`-Präfix), inkl. abgeleiteter -content
     * @param array<string, string> $geometry validierte Geometrie-Tokens
     */
    private function __construct(
        public readonly string $key,
        public readonly string $label,
        public readonly string $scheme,
        private readonly array $colors,
        private readonly array $geometry,
    ) {}

    /**
     * Baut eine Definition aus rohem (Form-)Input. Gibt null zurück, wenn key
     * oder eine der Pflichtfarben fehlt/ungültig ist.
     *
     * @param array<string, mixed> $raw
     */
    public static function fromArray(array $raw): ?self {
        $key = self::sanitizeKey($raw['key'] ?? null);
        if ($key === null) {
            return null;
        }

        $label = trim((string) ($raw['label'] ?? ''));
        $label = $label === '' ? $key : mb_substr($label, 0, 60);

        $scheme = ($raw['scheme'] ?? 'light') === 'dark' ? 'dark' : 'light';

        /** @var array<string, mixed> $inColors */
        $inColors = is_array($raw['colors'] ?? null) ? $raw['colors'] : [];
        $colors = [];

        foreach (self::REQUIRED_COLORS as $name) {
            $hex = Color::normalizeHex(is_string($inColors[$name] ?? null) ? $inColors[$name] : null);
            if ($hex === null) {
                return null; // Pflichtfarbe fehlt/ungültig → Theme ungültig
            }
            $colors[$name] = $hex;
        }

        foreach (self::STATUS_DEFAULTS as $name => $default) {
            $hex = Color::normalizeHex(is_string($inColors[$name] ?? null) ? $inColors[$name] : null);
            $colors[$name] = $hex ?? $default;
        }

        // base-content: explizit oder aus base-100 abgeleitet.
        $baseContent = Color::normalizeHex(is_string($inColors['base-content'] ?? null) ? $inColors['base-content'] : null);
        $colors['base-content'] = $baseContent ?? Color::contrastContent($colors['base-100']);

        // <farbe>-content: explizit oder aus der jeweiligen Farbe abgeleitet.
        foreach (self::CONTENT_BASES as $name) {
            $contentKey = $name . '-content';
            $hex = Color::normalizeHex(is_string($inColors[$contentKey] ?? null) ? $inColors[$contentKey] : null);
            $colors[$contentKey] = $hex ?? Color::contrastContent($colors[$name]);
        }

        $geometry = self::sanitizeGeometry(is_array($raw['geometry'] ?? null) ? $raw['geometry'] : []);

        return new self($key, $label, $scheme, $colors, $geometry);
    }

    /** data-theme-Wert dieses Themes (z. B. `org-ocean`). */
    public function token(): string {
        return 'org-' . $this->key;
    }

    /**
     * Kontrast neutral ↔ neutral-content. .wd-badge (resources/css/app.css)
     * mappt die base-Flächen der Sidebars auf die neutral-Palette — unter
     * diesem Verhältnis werden Hover/Border dort unlesbar.
     */
    public function neutralContrast(): float {
        return Color::contrastRatio($this->colors['neutral'], $this->colors['neutral-content']);
    }

    /**
     * Die vollständige Token-Map (Property-Name => Wert) inkl. `color-scheme`.
     *
     * @return array<string, string>
     */
    public function toCssVars(): array {
        $vars = ['color-scheme' => $this->scheme];
        foreach ($this->colors as $name => $hex) {
            $vars['--color-' . $name] = $hex;
        }
        foreach ($this->geometry as $name => $value) {
            $vars['--' . $name] = $value;
        }

        return $vars;
    }

    /** Rendert die `[data-theme="org-<key>"]{ … }`-Regel (injection-frei). */
    public function toCss(): string {
        $lines = [];
        foreach ($this->toCssVars() as $prop => $value) {
            $lines[] = $prop . ':' . $value . ';';
        }

        return '[data-theme="' . $this->token() . '"]{' . implode('', $lines) . '}';
    }

    /**
     * Kanonische, speicherbare Darstellung (für organizations.settings).
     *
     * @return array<string, mixed>
     */
    public function toArray(): array {
        return [
            'key' => $this->key,
            'label' => $this->label,
            'scheme' => $this->scheme,
            'colors' => $this->colors,
            'geometry' => $this->geometry,
        ];
    }

    /** Picker-Eintrag. @return array{key:string,label:string,scheme:string} */
    public function toPickerEntry(): array {
        return ['key' => $this->token(), 'label' => $this->label, 'scheme' => $this->scheme];
    }

    public static function sanitizeKey(mixed $value): ?string {
        if (! is_string($value)) {
            return null;
        }
        $value = strtolower(trim($value));

        return preg_match(self::KEY_PATTERN, $value) === 1 ? $value : null;
    }

    /**
     * @param array<string, mixed> $raw
     * @return array<string, string>
     */
    private static function sanitizeGeometry(array $raw): array {
        /** @var array<string, string> $defaults */
        $defaults = (array) config('theme.geometry', []);
        $out = [];
        foreach (self::GEOMETRY_KEYS as $name) {
            $val = $raw[$name] ?? null;
            if (is_string($val) && preg_match(self::UNIT_PATTERN, trim($val)) === 1) {
                $out[$name] = trim($val);
            } elseif (isset($defaults[$name])) {
                $out[$name] = (string) $defaults[$name];
            }
        }

        return $out;
    }
}
