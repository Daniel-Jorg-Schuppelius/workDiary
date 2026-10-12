<?php
/*
 * Created on   : Mon Oct 12 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ContactNumberOutcome.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Enums\Numbering;

/** Ergebnis der Übernahme einer Programmnummer (MVP-1132). */
enum ContactNumberOutcome: string {
    /** Nummer übernommen. */
    case Adopted = 'adopted';
    /** Kontakt trug die Nummer schon. */
    case Unchanged = 'unchanged';
    /** Ein anderer Kontakt trägt die Nummer; Fall in der Zuordnungs-Inbox. */
    case Taken = 'taken';
    /** Das Programm vergibt die Nummer dieses Kontakts nicht, oder es kam keine. */
    case Skipped = 'skipped';
}
