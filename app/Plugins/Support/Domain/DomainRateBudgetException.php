<?php
/*
 * Created on   : Thu Jul 16 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : DomainRateBudgetException.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Plugins\Support\Domain;

use RuntimeException;

/**
 * Das organisationsbezogene Laufbudget der Verfügbarkeitsprüfung ist
 * erschöpft (Feature 083, MVP-388). Schützt vor dem Strafpunktmodell der
 * Bulk-`CheckDomains`-Abfrage.
 */
class DomainRateBudgetException extends RuntimeException {
    public function __construct() {
        parent::__construct('Das Laufbudget der Domain-Verfügbarkeitsprüfung ist erschöpft.');
    }
}
