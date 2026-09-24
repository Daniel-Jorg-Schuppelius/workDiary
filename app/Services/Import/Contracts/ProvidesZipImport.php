<?php
/*
 * Created on   : Thu Sep 24 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ProvidesZipImport.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Import\Contracts;

/** Import-Spec einer Entität, die ZIP-Pakete annimmt ({@see \App\Enums\Import\ImportEntity::acceptsZip()}). */
interface ProvidesZipImport {
    public function zipImporter(): ZipImporter;
}
