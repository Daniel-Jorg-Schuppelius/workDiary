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

use CommonToolkit\Helper\Data\JsonHelper;
use CommonToolkit\Helper\FileSystem\{File, Files, Folder};
use Illuminate\Support\Arr;

/**
 * Gemeinsame Logik rund um die Übersetzungsdateien (JSON + PHP-Namespaces),
 * genutzt von `lang:check`, `lang:sync` und dem Paritäts-Test.
 *
 * Konventionen:
 *  - `de` ist die Quellsprache: JSON-Keys SIND die deutschen Quelltexte, die
 *    PHP-Namespace-Dateien unter lang/de/ sind die Referenzstruktur.
 *  - Übersetzungs-JSON existiert je Sprache außer `de` (lang/<code>.json).
 *  - `de.json` enthält nur Identitätseinträge für Pluralschlüssel: ohne sie
 *    wählt Translator::localeForChoice die Fallback-Sprache.
 */
class Translations {
    public static function langPath(string $rel = ''): string {
        return base_path('lang' . ($rel !== '' ? '/' . $rel : ''));
    }

    /**
     * Übersetzungskataloge: Kern (`''` → lang/) und je Plugin dessen
     * `Resources/lang` (Namespace = Plugin-ID), so wie der Translator sie lädt.
     *
     * @return array<string, string>
     */
    public static function catalogs(): array {
        $catalogs = ['' => self::langPath()];
        $plugins = app_path('Plugins') . DIRECTORY_SEPARATOR;
        $hints = app('translator')->getLoader()->namespaces();
        ksort($hints);
        foreach ($hints as $namespace => $path) {
            if (str_starts_with($path, $plugins)) {
                $catalogs[$namespace] = $path;
            }
        }

        return $catalogs;
    }

    /**
     * Sprachen mit flacher JSON-Datei = alle auswählbaren außer der Quellsprache `de`.
     *
     * @return list<string>
     */
    public static function jsonLocales(): array {
        return array_values(array_filter(Locales::enabledCodes(), static fn(string $c): bool => $c !== 'de'));
    }

    public static function jsonPath(string $code, string $namespace = ''): string {
        return self::catalogs()[$namespace] . '/' . $code . '.json';
    }

    /** @return array<string, string> */
    public static function loadJson(string $code, string $namespace = ''): array {
        $path = self::jsonPath($code, $namespace);
        if (! File::isFile($path)) {
            return [];
        }

        /** @var array<string, string> $data */
        $data = (array) json_decode(File::read($path), true);

        return $data;
    }

    /**
     * JSON-Texte einer Sprache über alle Kataloge — was der Translator zur
     * Laufzeit sieht (Grundlage der Quell-Scans).
     *
     * @return array<string, string>
     */
    public static function runtimeJson(string $code): array {
        $merged = [];
        foreach (array_keys(self::catalogs()) as $namespace) {
            $merged = [...$merged, ...self::loadJson($code, $namespace)];
        }

        return $merged;
    }

    /**
     * Kanonische JSON-Referenz eines Katalogs = en.json (fallback_locale). Jede
     * Sprache MUSS diese Keys abdecken (sonst zeigt die UI den rohen Schlüssel).
     * Zusätzliche, sprachspezifische Keys (z. B. noch nicht überall propagierte
     * Enum-Keys) sind erlaubt und werden separat nur informativ gemeldet.
     *
     * @return list<string>
     */
    public static function jsonReferenceKeys(string $namespace = ''): array {
        return array_keys(self::loadJson('en', $namespace));
    }

    /**
     * Namespace-Dateien aus den de-Referenzverzeichnissen: Kern als `user.php`,
     * Plugins als `lexoffice::lexware.php`.
     *
     * @return list<string>
     */
    public static function namespaceFiles(): array {
        $out = [];
        foreach (self::catalogs() as $namespace => $path) {
            $directory = $path . '/de';
            foreach (Folder::exists($directory) ? Folder::findByPattern($directory, '*.php') : [] as $file) {
                $out[] = ($namespace === '' ? '' : $namespace . '::') . basename($file);
            }
        }

        return $out;
    }

    /** Pfad einer Namespace-Datei (`user.php` bzw. `lexoffice::lexware.php`) in einer Sprache. */
    public static function phpPath(string $code, string $file): string {
        [$namespace, $name] = str_contains($file, '::') ? explode('::', $file, 2) : ['', $file];

        return self::catalogs()[$namespace] . '/' . $code . '/' . $name;
    }

    /** @return array<string, mixed> */
    public static function loadPhp(string $code, string $file): array {
        $path = self::phpPath($code, $file);
        if (! File::isFile($path)) {
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
    public static function writeJson(string $code, array $data, string $namespace = ''): void {
        $json = JsonHelper::encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);
        File::write(self::jsonPath($code, $namespace), $json . "\n");
    }

    /**
     * Schreibt eine PHP-Namespace-Datei (short-array, 4-Space).
     *
     * @param  array<string, mixed>  $data
     */
    public static function writePhp(string $code, string $file, array $data): void {
        $path = self::phpPath($code, $file);
        Folder::create(dirname($path), 0775, true);
        $header = "<?php\n/*\n * Übersetzungen ($code) — gepflegt via `php artisan lang:sync`.\n * Referenzstruktur: de/" . basename($path) . "\n */\n\n";
        $body = $header . 'return ' . self::exportArray($data, 1) . ";\n";
        File::write($path, $body);
    }

    /**
     * Schreibt einen Namespace-Fallback-Stub, der auf die englische Datei
     * verweist (Projekt-Konvention für noch nicht übersetzte Namespaces, vgl.
     * lang/fr, lang/it). Für echte Übersetzungen wird das require durch ein
     * Array ersetzt.
     */
    public static function writeRequireStub(string $code, string $file): void {
        $path = self::phpPath($code, $file);
        Folder::create(dirname($path), 0775, true);
        $body = "<?php\n/*\n * Übersetzungen ($code) — Fallback auf Englisch, bis übersetzt.\n"
            . " * Für echte Übersetzungen dieses require durch ein Array ersetzen.\n */\n\n"
            . "return require __DIR__ . '/../en/" . basename($path) . "';\n";
        File::write($path, $body);
    }

    /**
     * Alle im Quellcode (Blade-Views + app/) verwendeten JSON-Stil-Keys —
     * deutsche Quelltexte in __()/trans(). Namespace-Keys (auflösbar über
     * lang/de/<datei>.php bzw. dotted-lowercase) werden ausgefiltert, ebenso
     * dynamische Aufrufe (Interpolation/Konkatenation).
     *
     * Grundlage des Quell-Scans in lang:check/TranslationParityTest: ein Key,
     * der hier auftaucht, aber in en.json fehlt, fällt in ALLEN Sprachen auf
     * den deutschen Quelltext zurück — genau die Lücke, die reine
     * Katalog-Paritätsprüfungen (en ↔ fr/it/es) nicht sehen können.
     *
     * @return list<string>
     */
    public static function sourceJsonKeys(): array {
        $namespaces = array_fill_keys(
            array_map(static fn(string $f): string => substr($f, 0, -4), array_filter(self::namespaceFiles(), static fn(string $f): bool => ! str_contains($f, '::'))),
            true,
        );

        $keys = [];
        foreach (self::sourceFiles() as $path) {
            $src = File::read($path);
            if (! preg_match_all('~(?<![A-Za-z0-9_])(?:__|trans)\(\s*([\'"])((?:\\\\.|(?!\1).)*)\1\s*[,)]~s', $src, $m)) {
                continue;
            }
            foreach ($m[2] as $raw) {
                $k = stripcslashes($raw);
                if ($k === '' || str_contains($k, '$') || str_contains($k, '{')) {
                    continue; // dynamisch/interpoliert
                }
                if (preg_match('/^[a-z0-9_-]+::/', $k) === 1) {
                    continue; // Plugin-Namespace, siehe sourceNamespacedKeys()
                }
                if (! str_contains($k, ' ')) {
                    if (str_contains($k, '.') && isset($namespaces[explode('.', $k, 2)[0]])) {
                        continue; // Namespace-Key (lang/de/<datei>.php)
                    }
                    if (preg_match('/^[a-z0-9_]+(\.[a-z0-9_:-]+)+$/', $k) === 1) {
                        continue; // dotted-lowercase = Namespace-Konvention
                    }
                    if (str_ends_with($k, '.') || str_ends_with($k, ':') || str_ends_with($k, '_')) {
                        continue; // Konkatenations-Präfix
                    }
                }
                $keys[$k] = true;
            }
        }

        $out = array_keys($keys);
        sort($out);

        return $out;
    }

    /**
     * Literale Pluralschlüssel (trans_choice mit „|“) aus Views/app. Fehlt ein
     * solcher Schlüssel in de.json, zeigt die deutsche UI die Fallback-Sprache
     * (z. B. „16 articles“); fehlt er in en.json, bleiben alle anderen deutsch.
     *
     * @return list<string>
     */
    public static function sourcePluralKeys(): array {
        $keys = [];
        foreach (self::sourceFiles() as $path) {
            $src = File::read($path);
            if (! preg_match_all('~(?<![A-Za-z0-9_])trans_choice\(\s*([\'"])((?:\\\\.|(?!\1).)*)\1\s*,~s', $src, $m)) {
                continue;
            }
            foreach ($m[2] as $raw) {
                $k = stripcslashes($raw);
                if (str_contains($k, '|') && ! str_contains($k, '$')) {
                    $keys[$k] = true;
                }
            }
        }

        $out = array_keys($keys);
        sort($out);

        return $out;
    }

    /**
     * Vollständige Plugin-Schlüssel (`lexoffice::lexware.title`) aus Views/app;
     * Konkatenations-Präfixe und interpolierte Schlüssel bleiben außen vor.
     *
     * @return list<string>
     */
    public static function sourceNamespacedKeys(): array {
        $keys = [];
        foreach (self::sourceFiles() as $path) {
            if (preg_match_all('~(?<![A-Za-z0-9_])(?:__|trans|trans_choice|@lang)\(\s*([\'"])([a-z0-9_-]+::[A-Za-z0-9_.-]+)\1\s*[,)]~', File::read($path), $m)) {
                foreach ($m[2] as $k) {
                    if (! str_ends_with($k, '.')) {
                        $keys[$k] = true;
                    }
                }
            }
        }

        $out = array_keys($keys);
        sort($out);

        return $out;
    }

    /** @return list<string> */
    private static function sourceFiles(): array {
        return [...Files::get(base_path('resources/views'), true, ['php']), ...Files::get(base_path('app'), true, ['php'])];
    }

    /**
     * Rekursiver PHP-Array-Export im Projektstil (short syntax, 4-Space).
     *
     * @param  array<int|string, mixed>  $arr
     */
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
