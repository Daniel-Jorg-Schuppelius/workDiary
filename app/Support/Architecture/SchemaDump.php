<?php
/*
 * Created on   : Wed Sep 23 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : SchemaDump.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Support\Architecture;

use CommonToolkit\Helper\FileSystem\File;

/**
 * Tabellennamen aus dem eingecheckten MySQL-Schema-Dump — die eine Liste, die
 * `modules:check` und die Architektur-Gates gegen die Manifeste halten.
 */
final class SchemaDump {
    /** @return list<string> alphabetisch */
    public static function tableNames(?string $path = null): array {
        $path ??= base_path('database/schema/mysql-schema.sql');
        if (! File::isFile($path)) {
            return [];
        }
        preg_match_all('/^CREATE TABLE `(\w+)`/m', File::read($path), $m);
        $tables = array_values(array_unique($m[1]));
        sort($tables);

        return $tables;
    }
}
