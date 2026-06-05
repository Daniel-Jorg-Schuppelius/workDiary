<?php
/*
 * Created on   : Thu Jun 05 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : Translations.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Support;

use Illuminate\Support\Arr;

/**
 * Gemeinsame Logik rund um die Übersetzungsdateien (JSON + PHP-Namespaces),
 * genutzt von `lang:check`, `lang:sync` und dem Paritäts-Test.
 *
 * Konventionen:
 *  - `de` ist die Quellsprache: JSON-Keys SIND die deutschen Quelltexte, die
 *    PHP-Namespace-Dateien unter lang/de/ sind die Referenzstruktur.
 *  - JSON-Dateien existieren je Sprache außer `de` (lang/<code>.json).
 */
class Translations {
    public static function langPath(string $rel = ''): string {
        return base_path('lang' . ($rel !== '' ? '/' . $rel : ''));
    }

    /**
     * Sprachen mit flacher JSON-Datei = alle auswählbaren außer der Quellsprache `de`.
     *
     * @return list<string>
     */
    public static function jsonLocales(): array {
        return array_values(array_filter(Locales::enabledCodes(), static fn(string $c): bool => $c !== 'de'));
    }

    public static function jsonPath(string $code): string {
        return self::langPath($code . '.json');
    }

    /** @return array<string, string> */
    public static function loadJson(string $code): array {
        $path = self::jsonPath($code);
        if (! is_file($path)) {
            return [];
        }

        /** @var array<string, string> $data */
        $data = (array) json_decode((string) file_get_contents($path), true);

        return $data;
    }

    /**
     * Kanonische JSON-Referenz = en.json (fallback_locale). Jede Sprache MUSS
     * diese Keys abdecken (sonst zeigt die UI den rohen Schlüssel). Zusätzliche,
     * sprachspezifische Keys (z. B. noch nicht überall propagierte Enum-Keys)
     * sind erlaubt und werden separat nur informativ gemeldet.
     *
     * @return list<string>
     */
    public static function jsonReferenceKeys(): array {
        return array_keys(self::loadJson('en'));
    }

    /**
     * Namespace-Dateinamen aus dem de-Referenzverzeichnis (z. B. "user.php").
     *
     * @return list<string>
     */
    public static function namespaceFiles(): array {
        $files = glob(self::langPath('de') . '/*.php') ?: [];

        return array_map('basename', $files);
    }

    /** @return array<string, mixed> */
    public static function loadPhp(string $code, string $file): array {
        $path = self::langPath($code) . '/' . $file;
        if (! is_file($path)) {
            return [];
        }
        $data = require $path;

        return is_array($data) ? $data : [];
    }

    /**
     * Dotted-Keys eines Namespace (für Parität/Diff).
     *
     * @return list<string>
     */
    public static function phpKeys(string $code, string $file): array {
        return array_keys(Arr::dot(self::loadPhp($code, $file)));
    }

    /**
     * Schreibt eine JSON-Sprachdatei in Referenz-Reihenfolge (4-Space, rohes UTF-8).
     *
     * @param  array<string, string>  $data
     */
    public static function writeJson(string $code, array $data): void {
        $json = json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);
        file_put_contents(self::jsonPath($code), $json . "\n");
    }

    /**
     * Schreibt eine PHP-Namespace-Datei (short-array, 4-Space).
     *
     * @param  array<string, mixed>  $data
     */
    public static function writePhp(string $code, string $file, array $data): void {
        $dir = self::langPath($code);
        if (! is_dir($dir)) {
            mkdir($dir, 0775, true);
        }
        $header = "<?php\n/*\n * Übersetzungen ($code) — gepflegt via `php artisan lang:sync`.\n * Referenzstruktur: lang/de/$file\n */\n\n";
        $body = $header . 'return ' . self::exportArray($data, 1) . ";\n";
        file_put_contents($dir . '/' . $file, $body);
    }

    /**
     * Schreibt einen Namespace-Fallback-Stub, der auf die englische Datei
     * verweist (Projekt-Konvention für noch nicht übersetzte Namespaces, vgl.
     * lang/fr, lang/it). Für echte Übersetzungen wird das require durch ein
     * Array ersetzt.
     */
    public static function writeRequireStub(string $code, string $file): void {
        $dir = self::langPath($code);
        if (! is_dir($dir)) {
            mkdir($dir, 0775, true);
        }
        $body = "<?php\n/*\n * Übersetzungen ($code) — Fallback auf Englisch, bis übersetzt.\n"
            . " * Für echte Übersetzungen dieses require durch ein Array ersetzen.\n */\n\n"
            . "return require __DIR__ . '/../en/$file';\n";
        file_put_contents($dir . '/' . $file, $body);
    }

    /** Rekursiver PHP-Array-Export im Projektstil (short syntax, 4-Space). */
    public static function exportArray(array $arr, int $depth): string {
        $pad = str_repeat('    ', $depth);
        $padEnd = str_repeat('    ', $depth - 1);
        $lines = [];
        foreach ($arr as $key => $value) {
            $k = is_int($key) ? (string) $key : "'" . addcslashes((string) $key, "\\'") . "'";
            if (is_array($value)) {
                $lines[] = $pad . $k . ' => ' . self::exportArray($value, $depth + 1) . ',';
            } else {
                $v = "'" . addcslashes((string) $value, "\\'") . "'";
                $lines[] = $pad . $k . ' => ' . $v . ',';
            }
        }

        return "[\n" . implode("\n", $lines) . "\n" . $padEnd . ']';
    }
}
