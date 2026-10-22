<?php
/*
 * Created on   : Wed Oct 21 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : TimeApprovalWorkflowException.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\TimeApproval;

use RuntimeException;

/**
 * Gemeinsame Basis der Zeitfreigabe-Workflows (Tagesabschluss,
 * Monatsabschluss, Korrekturantrag): Grundcode für die Oberfläche und
 * Kontext für Protokoll und Tests.
 */
abstract class TimeApprovalWorkflowException extends RuntimeException {
    /**
     * @param  array<string, mixed>  $context
     */
    final public function __construct(
        public readonly string $reasonCode,
        string $message,
        public readonly array $context = [],
    ) {
        parent::__construct($message);
    }
}
