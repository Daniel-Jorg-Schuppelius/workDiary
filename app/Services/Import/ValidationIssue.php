<?php
/*
 * Created on   : Tue May 26 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ValidationIssue.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Import;

use App\Enums\Import\ImportErrorCode;

/**
 * Einzelner Validierungsbefund einer CSV-Zeile.
 *
 * Wird vom {@see EntitySpec::validateRow()} zurückgegeben und vom
 * {@see CsvPreflightAnalyzer} bzw. {@see ProcessCsvImportJob}
 * in `import_run_errors` persistiert.
 */
final readonly class ValidationIssue {
    public function __construct(
        public ImportErrorCode $code,
        public ?string $field,
        public string $message,
    ) {}
}
