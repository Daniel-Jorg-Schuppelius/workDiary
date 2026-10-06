<?php
/*
 * Created on   : Sun Oct 04 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : BranchProfileFiles.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Support;

use CommonToolkit\Helper\FileSystem\File;

/**
 * Einzige Stelle, die aus einem Branchenprofil-Code einen `require`-Pfad
 * bildet. Der Code kommt auch aus importierten JSON-Profilen und aus den
 * Organisationseinstellungen (Sicherheitsaudit 2026-10-04, authz-b-1).
 */
final class BranchProfileFiles {
    public const CODE_PATTERN = '/^[a-z0-9-]+$/';

    public static function isValidCode(string $code): bool {
        return preg_match(self::CODE_PATTERN, $code) === 1;
    }

    /** @return array<string, mixed>|null Mitgeliefertes Profil; null bei unbekanntem oder ungültigem Code. */
    public static function profile(string $code): ?array {
        return self::load('data/branchprofiles/', $code);
    }

    /** @return array<string, array<string, array<string, string>>> Übersetzungs-Beilage `i18n/<code>.php`; leer, wenn keine mitgeliefert ist. */
    public static function sidecar(string $code): array {
        return self::load('data/branchprofiles/i18n/', $code) ?? [];
    }

    /** @return array<string, mixed>|null */
    private static function load(string $directory, string $code): ?array {
        if (! self::isValidCode($code)) {
            return null;
        }
        $path = database_path($directory . $code . '.php');

        return File::isFile($path) ? (array) require $path : null;
    }
}
