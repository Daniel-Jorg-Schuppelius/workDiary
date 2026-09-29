<?php
/*
 * Created on   : Tue Sep 29 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : OrganizationFileTables.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Org;

/**
 * Dateitabellen der Plugins, deren Dateien die endgültige Löschung einer
 * Organisation mitnimmt (MVP-1044); die Kerntabellen stehen im
 * {@see OrganizationLifecycleService}.
 */
final class OrganizationFileTables {
    /** @var array<string, array{path: string, disk?: string, default_disk?: string, dir?: bool}> */
    private array $tables = [];

    /** @param  array{path: string, disk?: string, default_disk?: string, dir?: bool}  $spec */
    public function register(string $table, array $spec): void {
        $this->tables[$table] = $spec;
    }

    /** @return array<string, array{path: string, disk?: string, default_disk?: string, dir?: bool}> */
    public function all(): array {
        return $this->tables;
    }
}
