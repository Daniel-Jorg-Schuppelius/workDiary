<?php
/*
 * Created on   : Tue May 26 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : MonthClosureWorkflowException.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Services\TimeApproval;

use RuntimeException;

/**
 * Wirft jeder MonthClosureService-Übergang, der die in
 * ../WorkDiary-Architecture/monatsfreigabe.md §4 definierte Statusmaschine verletzt
 * oder eine fachliche Vorbedingung nicht erfüllt
 * (z. B. offene Tage / ⛔ Warnungen vor Submit).
 */
class MonthClosureWorkflowException extends RuntimeException {
    /**
     * @param  array<string, mixed>  $context
     */
    public function __construct(
        public readonly string $reasonCode,
        string $message,
        public readonly array $context = [],
    ) {
        parent::__construct($message);
    }
}
