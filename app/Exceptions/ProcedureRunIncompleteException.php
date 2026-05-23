<?php
/*
 * Created on   : Tue Jun 02 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ProcedureRunIncompleteException.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Exceptions;

use App\Models\ProcedureRun;
use RuntimeException;

/**
 * HTTP 409 mit error.code = "procedure.runIncomplete" und Liste
 * fehlender Schritt-IDs (MVP-026 §4).
 */
class ProcedureRunIncompleteException extends RuntimeException {
    public const CODE = 'procedure.runIncomplete';

    /**
     * @param  list<int>  $missingStepRunIds
     */
    public function __construct(
        public readonly ProcedureRun $run,
        public readonly array $missingStepRunIds,
    ) {
        parent::__construct(sprintf(
            'Procedure run #%d cannot be completed: %d required step(s) still open.',
            $run->id,
            count($missingStepRunIds),
        ));
    }
}
