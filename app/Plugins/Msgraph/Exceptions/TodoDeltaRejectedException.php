<?php
/*
 * Created on   : Sun Oct 18 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : TodoDeltaRejectedException.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Plugins\Msgraph\Exceptions;

use RuntimeException;

/**
 * Graph weist die Delta-Abfrage einer To-Do-Liste mit HTTP 400 ab (Befund
 * 2026-10-18: „Delta query is not supported by this resource"). Ohne
 * Checkpoint heißt das: die Liste kann kein Delta — der Import weicht auf die
 * volle Sicht aus; mit Checkpoint ist der Checkpoint unbrauchbar und die
 * Kette wird neu aufgebaut.
 */
class TodoDeltaRejectedException extends RuntimeException {
    public function __construct(
        public readonly string $listId,
        public readonly string $graphCode,
        public readonly string $graphMessage,
    ) {
        parent::__construct(sprintf(
            'Graph todo tasks/delta abgewiesen (HTTP 400, %s: %s).',
            $graphCode !== '' ? $graphCode : 'BadRequest',
            $graphMessage,
        ));
    }
}
