<?php
/*
 * Created on   : Wed Jul 08 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : AgileFlowException.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Agile;

use RuntimeException;

/**
 * Flussregel verletzt (Feature 064, P3): WIP-Limit erreicht oder
 * unerfüllte Kriterien/DoD beim Zug nach done — ohne Override-Recht +
 * Begründung wird der Zug abgelehnt (HTTP 422 im Controller).
 */
class AgileFlowException extends RuntimeException {
    public function __construct(
        public readonly string $reason, // wip|criteria|dod
        string $message,
    ) {
        parent::__construct($message);
    }
}
