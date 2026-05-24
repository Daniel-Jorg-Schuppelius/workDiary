<?php
/*
 * Created on   : Sat May 16 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : FileSystemFacade.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Support\Toolkit;

use CommonToolkit\Helper\FileSystem\{File, Files, Folder};

/**
 * Wrapper um die Toolkit-FileSystem-Helper (File, Files, Folder).
 *
 * Im App-Code IMMER bevorzugt gegenüber nativen PHP-Aufrufen oder Storage-Facade,
 * wenn es um lokale Dateioperationen geht (Archive, Import/Export, Temp-Files).
 */
final class FileSystemFacade {
    public static function fileExists(string $path): bool {
        return File::exists($path);
    }

    public static function readFile(string $path): string {
        return File::read($path);
    }

    public static function readFileAsUtf8(string $path, ?string $sourceEncoding = null): string {
        return File::readAsUtf8($path, $sourceEncoding);
    }

    public static function mimeType(string $path): string|false {
        return File::mimeType($path);
    }

    public static function folderExists(string $path): bool {
        return Folder::exists($path);
    }

    public static function ensureFolder(string $path, int $permissions = 0755): void {
        if (! Folder::exists($path)) {
            Folder::create($path, $permissions, true);
        }
    }

    public static function deleteFolder(string $path, bool $recursive = true): void {
        if (Folder::exists($path)) {
            Folder::delete($path, $recursive);
        }
    }

    public static function folderSize(string $path, bool $recursive = true): int {
        return Folder::size($path, $recursive);
    }

    /**
     * @param  array<int, string>  $extensions
     */
    public static function fileCount(string $path, bool $recursive = false, array $extensions = []): int {
        return Folder::fileCount($path, $recursive, $extensions);
    }

    /**
     * @param  array<int, string>  $fileTypes
     * @return array<int, string>
     */
    public static function listFiles(string $path, bool $recursive = false, array $fileTypes = []): array {
        return Files::get($path, $recursive, $fileTypes);
    }

    /**
     * @param  array<int, string>  $files
     */
    public static function deleteFiles(array $files): void {
        Files::delete($files);
    }
}
