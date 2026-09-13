<?php
/*
 * Created on   : Sun Sep 13 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : StopwatchAlreadyRunningException.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Timesheet;

use RuntimeException;

/**
 * start() trifft auf einen bereits laufenden Eintrag des Nutzers (Doppelklick,
 * zweiter Tab). Eigene Klasse, damit der Aufrufer den Fall an der Klasse
 * erkennt und in eine Flash-Meldung übersetzt — andere Zustände (signierter
 * Stundenzettel) bleiben eine RuntimeException und damit hart.
 */
class StopwatchAlreadyRunningException extends RuntimeException {
    public function __construct(string $message = 'A running entry already exists.') {
        parent::__construct($message);
    }
}
