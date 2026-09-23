<?php
/*
 * Created on   : Wed Sep 23 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ModelScanner.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Support\Architecture;

use CommonToolkit\Helper\FileSystem\{File, Files, Folder};
use Illuminate\Database\Eloquent\Model;

/**
 * Findet alle konkreten Eloquent-Modelle des Projekts (app/Models,
 * app/Legacy/Models, app/Plugins/<Name>/Models). Eine Stelle für Generator
 * (`morph-map:generate`) und Architektur-Gates, damit beide dieselbe Menge
 * sehen (MVP-860).
 */
final class ModelScanner {
    /** @return list<class-string<Model>> alphabetisch */
    public static function classes(?string $root = null): array {
        $root = rtrim($root ?? base_path(), DIRECTORY_SEPARATOR);
        $files = [
            ...self::phpFiles($root . '/app/Models'),
            ...self::phpFiles($root . '/app/Legacy/Models'),
            ...array_filter(self::phpFiles($root . '/app/Plugins'), static fn (string $file): bool => str_contains($file, DIRECTORY_SEPARATOR . 'Models' . DIRECTORY_SEPARATOR)),
        ];

        $classes = [];
        foreach ($files as $file) {
            $source = File::read($file);
            if (preg_match('/^(?:abstract\s+)?class\s+\w+\s+extends\s+/m', $source) !== 1 || str_contains($source, 'abstract class ')) {
                continue;
            }
            $relative = substr($file, strlen($root) + 1, -strlen('.php'));
            $class = 'App\\' . str_replace('/', '\\', substr(str_replace(DIRECTORY_SEPARATOR, '/', $relative), strlen('app/')));
            if (class_exists($class) && is_subclass_of($class, Model::class)) {
                /** @var class-string<Model> $class */
                $classes[] = $class;
            }
        }
        sort($classes);

        return $classes;
    }

    /** @return list<string> absolute Pfade, sortiert */
    private static function phpFiles(string $directory): array {
        if (! Folder::isDirectory($directory)) {
            return [];
        }
        $files = Files::get($directory, recursive: true, fileTypes: ['php']);
        sort($files);

        return $files;
    }
}
