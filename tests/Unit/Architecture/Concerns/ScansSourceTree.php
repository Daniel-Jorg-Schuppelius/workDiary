<?php
/*
 * Created on   : Sun Aug 23 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ScansSourceTree.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace Tests\Unit\Architecture\Concerns;

use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use SplFileInfo;

/**
 * Gemeinsame Datei-Scans der Architektur-Gates (Vollscan 2026-08-23, Welle 1):
 * Quelltext-/View-Inventar, repo-relative Pfade, Zeilenermittlung und eine
 * Allow-List-Prüfung nach Pfad-Präfix — damit jedes neue Gate nur noch seine
 * Regel formuliert.
 */
trait ScansSourceTree {
    protected function repoRoot(): string {
        return (string) realpath(__DIR__ . '/../../../..');
    }

    /** @return list<string> absolute Pfade */
    protected function filesUnder(string $directory, string $extensionPattern): array {
        $root = $this->repoRoot() . DIRECTORY_SEPARATOR . $directory;
        if (! is_dir($root)) {
            return [];
        }

        $files = [];
        /** @var SplFileInfo $file */
        foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root, RecursiveDirectoryIterator::SKIP_DOTS)) as $file) {
            if ($file->isFile() && preg_match($extensionPattern, $file->getFilename()) === 1) {
                $files[] = $file->getPathname();
            }
        }
        sort($files);

        return $files;
    }

    /** @return list<string> */
    protected function phpFiles(string $directory = 'app'): array {
        return $this->filesUnder($directory, '/\.php$/');
    }

    /** @return list<string> */
    protected function bladeFiles(string $directory = 'resources/views'): array {
        return $this->filesUnder($directory, '/\.blade\.php$/');
    }

    protected function relativePath(string $absolute): string {
        return str_replace([$this->repoRoot() . DIRECTORY_SEPARATOR, DIRECTORY_SEPARATOR], ['', '/'], $absolute);
    }

    protected function lineOf(string $source, int $offset): int {
        return substr_count(substr($source, 0, $offset), "\n") + 1;
    }

    /** Entfernt //- und #-Zeilenkommentare sowie /* … *\/-Blöcke (grob, zeilenbasiert). */
    protected function stripComments(string $source): string {
        $source = (string) preg_replace('~/\*.*?\*/~s', '', $source);

        return (string) preg_replace('~^\s*(//|#(?!\[)).*$~m', '', $source);
    }

    /** Entfernt Blade-Kommentare {{-- … --}}. */
    protected function stripBladeComments(string $source): string {
        return (string) preg_replace('~\{\{--.*?--\}\}~s', '', $source);
    }

    /**
     * @param  array<string, string>  $allowList  Pfad-Präfix (repo-relativ) → Begründung
     */
    protected function isAllowListed(string $relative, array $allowList): bool {
        foreach (array_keys($allowList) as $prefix) {
            if (str_starts_with($relative, $prefix)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Tabellen des MySQL-Schema-Dumps: name → ['columns' => [name => definition],
     * 'foreign' => [column => ['references' => table, 'on_delete' => 'CASCADE'|'SET NULL'|'RESTRICT'|'']],
     * 'unique' => [indexName => [columns]]].
     *
     * @return array<string, array{columns: array<string, string>, foreign: array<string, array{references: string, on_delete: string}>, unique: array<string, list<string>>}>
     */
    protected function schemaTables(): array {
        $dump = (string) file_get_contents($this->repoRoot() . '/database/schema/mysql-schema.sql');
        $tables = [];
        if (preg_match_all('/CREATE TABLE `(\w+)` \((.*?)\n\)[^\n]*;/s', $dump, $matches, PREG_SET_ORDER) === 0) {
            return $tables;
        }

        foreach ($matches as [$_, $table, $body]) {
            $columns = [];
            $foreign = [];
            $unique = [];
            foreach (preg_split('/\r?\n/', $body) ?: [] as $line) {
                $line = trim($line);
                if (preg_match('/^`(\w+)` (.+?),?$/', $line, $c) === 1) {
                    $columns[$c[1]] = $c[2];
                } elseif (preg_match('/^CONSTRAINT `\w+` FOREIGN KEY \(`(\w+)`\) REFERENCES `(\w+)` \(`\w+`\)(.*?),?$/', $line, $f) === 1) {
                    $onDelete = preg_match('/ON DELETE (CASCADE|SET NULL|RESTRICT|NO ACTION)/', $f[3], $d) === 1 ? $d[1] : '';
                    $foreign[$f[1]] = ['references' => $f[2], 'on_delete' => $onDelete];
                } elseif (preg_match('/^UNIQUE KEY `(\w+)` \((.+)\),?$/', $line, $u) === 1) {
                    $unique[$u[1]] = array_map(fn (string $col): string => trim($col, '` '), explode(',', $u[2]));
                }
            }
            $tables[$table] = ['columns' => $columns, 'foreign' => $foreign, 'unique' => $unique];
        }

        return $tables;
    }

    /** Tabellenname eines Eloquent-Modells (protected $table oder Laravel-Konvention). */
    protected function tableOfModel(string $class): string {
        if (! class_exists($class)) {
            return '';
        }
        $model = new $class();

        return $model instanceof \Illuminate\Database\Eloquent\Model ? $model->getTable() : '';
    }

    /** @return list<class-string> Alle Modellklassen unter app/Models (und Plugin-Models). */
    protected function modelClasses(): array {
        $classes = [];
        foreach (array_merge($this->phpFiles('app/Models'), $this->filesUnder('app/Plugins', '/\.php$/')) as $file) {
            $relative = $this->relativePath($file);
            if (str_starts_with($relative, 'app/Plugins') && ! str_contains($relative, '/Models/')) {
                continue;
            }
            $source = (string) file_get_contents($file);
            if (preg_match('/^(?:abstract\s+)?class\s+(\w+)\s+extends\s+/m', $source) !== 1 || str_contains($source, 'abstract class ')) {
                continue;
            }
            $class = 'App\\' . str_replace('/', '\\', substr($relative, strlen('app/'), -strlen('.php')));
            if (class_exists($class) && is_subclass_of($class, \Illuminate\Database\Eloquent\Model::class)) {
                $classes[] = $class;
            }
        }
        sort($classes);

        return $classes;
    }
}
