<?php
/*
 * Created on   : Tue Aug 25 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : LogbookViolationException.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Exceptions;

use RuntimeException;

/**
 * Fahrtenbuch-Regelverstoß (Feature 137): Pflichtfelder, km-Kette oder
 * Plausibilität im Logbook-Modus — feldbezogen, damit das Formular die
 * Meldung am richtigen Eingabefeld zeigt.
 */
class LogbookViolationException extends RuntimeException {
    /** @param array<string, string> $errors Feld → Meldung */
    public function __construct(public readonly array $errors) {
        parent::__construct(implode(' ', $errors));
    }
}
