<?php
/*
 * Created on   : Mon May 18 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : LicenseSeal.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 *
 * Schmaler Reader für die durch `php artisan license:seal` erzeugte
 * Seal-Datendatei. Da die Klasse selbst keine sealing-spezifischen
 * Konstanten mehr trägt, verändert sich ihr Hash beim Sealing nicht
 * und sie darf Teil der versiegelten Dateien sein.
 *
 * Im unversiegelten Zustand existiert die Datendatei nicht oder ist
 * leer; die App fällt dann auf die env-Konfiguration zurück.
 */

namespace App\Services\Licensing;

use CommonToolkit\Helper\FileSystem\File as ToolkitFile;

final class LicenseSeal {
    /** @var array{public_key: string, files: array<string, string>, sealed_at: string}|null */
    private static ?array $cache = null;

    public static function path(): string {
        return storage_path('app/' . (string) config('license.seal_path', 'private/license-seal.php'));
    }

    public static function publicKey(): string {
        return self::data()['public_key'];
    }

    /**
     * @return array<string, string> relativer Pfad => sha256-hex
     */
    public static function files(): array {
        return self::data()['files'];
    }

    public static function sealedAt(): string {
        return self::data()['sealed_at'];
    }

    public static function isSealed(): bool {
        return self::publicKey() !== '';
    }

    public static function flushCache(): void {
        self::$cache = null;
    }

    /**
     * @return array{public_key: string, files: array<string, string>, sealed_at: string}
     */
    private static function data(): array {
        if (self::$cache !== null) {
            return self::$cache;
        }

        $path = self::path();
        // Existenz genügt nicht: unlesbare Datei (falsche Serverrechte) ließe `require` mit ErrorException
        // die App per 500 lahmlegen → bewusst auf den unversiegelten Zustand (env) zurückfallen.
        if (ToolkitFile::exists($path) && is_readable($path)) {
            /** @var mixed $loaded */
            $loaded = self::requireSeal($path);
            if (
                is_array($loaded)
                && isset($loaded['public_key'], $loaded['files'], $loaded['sealed_at'])
                && is_string($loaded['public_key'])
                && is_array($loaded['files'])
                && is_string($loaded['sealed_at'])
            ) {
                /** @var array<string, string> $files */
                $files = array_filter(
                    $loaded['files'],
                    static fn($value, $key): bool => is_string($key) && is_string($value),
                    ARRAY_FILTER_USE_BOTH,
                );

                return self::$cache = [
                    'public_key' => $loaded['public_key'],
                    'files' => $files,
                    'sealed_at' => $loaded['sealed_at'],
                ];
            }
        }

        return self::$cache = ['public_key' => '', 'files' => [], 'sealed_at' => ''];
    }

    /**
     * Lädt die Seal-Datei und fängt Lese-/Parse-Fehler ab, damit ein
     * beschädigtes oder nicht lesbares File nicht den Request abbricht.
     */
    private static function requireSeal(string $path): mixed {
        try {
            return require $path;
        } catch (\Throwable) {
            return null;
        }
    }
}
