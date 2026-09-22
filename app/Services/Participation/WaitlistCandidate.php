<?php
/*
 * Created on   : Tue Sep 22 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : WaitlistCandidate.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Participation;

use Carbon\CarbonInterface;
use Closure;

/**
 * Nächster Wartelisten-Eintrag einer Teilnehmerart (MVP-843): Wartezeitpunkt
 * für die Reihenfolge über alle Arten hinweg, das Subjekt für die
 * Benachrichtigung des Aufrufers und die Nachrück-Aktion selbst.
 */
final class WaitlistCandidate {
    /** @param Closure(): object $promote */
    public function __construct(
        public readonly CarbonInterface $waitingSince,
        public readonly object $subject,
        private readonly Closure $promote,
    ) {}

    /** Rückt nach und liefert das aktualisierte Subjekt. */
    public function promote(): object {
        return ($this->promote)();
    }
}
