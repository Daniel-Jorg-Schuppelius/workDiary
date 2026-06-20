<?php
/*
 * Created on   : Sat Jun 20 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : Color.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Support;

/**
 * Kleine, zustandslose Farb-Helfer. Geteilt von BrandingService (Primär-/
 * Akzentfarbe) und dem Theming-System (ThemeDefinition/ThemeService).
 *
 * Bewusst eng gehalten: nur 6-stellige HEX-Werte. Das schützt zugleich vor
 * CSS-Injection, weil normalizeHex() ausschließlich `#` + 6 Hex-Zeichen
 * durchlässt — jeder andere Input ergibt null.
 */
final class Color {
    /**
     * Normalisiert einen 6-stelligen HEX-Wert auf `#rrggbb` (kleingeschrieben)
     * oder gibt null zurück, wenn der Wert kein gültiges 6-stelliges HEX ist.
     */
    public static function normalizeHex(?string $value): ?string {
        if ($value === null) {
            return null;
        }
        $value = trim($value);
        if ($value === '') {
            return null;
        }
        if (! preg_match('/^#?[0-9a-fA-F]{6}$/', $value)) {
            return null;
        }

        return str_starts_with($value, '#') ? strtolower($value) : '#' . strtolower($value);
    }

    /**
     * Wählt eine gut lesbare Vordergrundfarbe (dunkles Anthrazit oder Weiß)
     * für einen gegebenen Hintergrund. Wird genutzt, um fehlende
     * `*-content`-Tokens eines Custom-Themes serverseitig abzuleiten.
     *
     * @param string $hex 6-stelliges HEX (mit oder ohne führendes `#`)
     */
    public static function contrastContent(string $hex): string {
        return self::relativeLuminance($hex) > 0.55 ? '#1f2937' : '#ffffff';
    }

    /**
     * WCAG-Kontrastverhältnis zweier HEX-Farben (1.0 … 21.0). Genutzt, um den
     * Mindestkontrast neutral ↔ neutral-content eines Custom-Themes zu prüfen
     * (sonst werden die aus neutral abgeleiteten .wd-badge-Flächen unlesbar).
     */
    public static function contrastRatio(string $a, string $b): float {
        $la = self::relativeLuminance($a);
        $lb = self::relativeLuminance($b);
        $light = max($la, $lb);
        $dark = min($la, $lb);

        return ($light + 0.05) / ($dark + 0.05);
    }

    /** Relative Luminanz (sRGB, 0.0 … 1.0) nach WCAG. */
    private static function relativeLuminance(string $hex): float {
        $hex = ltrim($hex, '#');
        if (strlen($hex) !== 6) {
            return 0.0;
        }
        $channels = [];
        foreach ([0, 2, 4] as $offset) {
            $c = hexdec(substr($hex, $offset, 2)) / 255;
            $channels[] = $c <= 0.03928 ? $c / 12.92 : (($c + 0.055) / 1.055) ** 2.4;
        }

        return 0.2126 * $channels[0] + 0.7152 * $channels[1] + 0.0722 * $channels[2];
    }
}
