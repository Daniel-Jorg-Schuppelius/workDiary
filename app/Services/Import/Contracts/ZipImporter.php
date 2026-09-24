<?php
/*
 * Created on   : Thu Sep 24 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ZipImporter.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Import\Contracts;

use App\Models\Integration\ImportRun;
use App\Models\Platform\{Organization, User};

/**
 * ZIP-Import einer Entität (Manifest-CSV + Dateien), z. B. Dokumente
 * (MVP-863). Den Importer liefert das Spec der Entität über
 * {@see ProvidesZipImport}; der Import-Rahmen kennt kein Fachmodul.
 */
interface ZipImporter {
    public const MANIFEST = 'manifest.csv';

    public const MAX_ZIP_KB = 51_200;

    /** @return array{delimiter: string, prefix: string, entries: array<string, string>, headerIssues: list<\App\Services\Import\ValidationIssue>, rows: list<array{number: int, data: array<string, string>}>} */
    public function open(string $zipBinary): array;

    public function preflight(ImportRun $run, string $zipBinary, Organization $organization): void;

    /** @return array{0: int, 1: int, 2: int, 3: int} created, updated, skipped, failed */
    public function import(ImportRun $run, string $zipBinary, Organization $organization, User $actor): array;

    /** @return array<string, mixed> */
    public function importBinary(string $zipBinary, Organization $organization, User $actor): array;
}
