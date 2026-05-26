<?php
/*
 * Created on   : Wed Jul 01 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : TimeExportException.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Services\TimeExport;

use RuntimeException;

/**
 * Fachliche Fehler im TimeExport-Workflow (MVP-019).
 *
 * - reasonCode: maschinenlesbarer Code (z. B. "monthNotApproved", "profileUnknown")
 * - context:    optionaler Strukturkontext für UI/Logging
 */
class TimeExportException extends RuntimeException {
    /** @param  array<string,mixed>  $context */
    public function __construct(
        public readonly string $reasonCode,
        string $message,
        public readonly array $context = [],
    ) {
        parent::__construct($message);
    }
}
