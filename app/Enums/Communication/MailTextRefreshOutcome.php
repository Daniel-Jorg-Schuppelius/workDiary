<?php
/*
 * Created on   : Mon Oct 19 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : MailTextRefreshOutcome.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Enums\Communication;

/** Ergebnis des Erneuerns eines Mailtexts aus dem Postfach je Notiz. */
enum MailTextRefreshOutcome: string {
    /** Text neu geschrieben (oder im Probelauf: würde neu geschrieben). */
    case Refreshed = 'refreshed';
    /** Mail gefunden, Text ist schon gleich. */
    case Unchanged = 'unchanged';
    /** Kein Postfach hat die Mail mehr. */
    case NotFound = 'not_found';
    /** Keine eingegangene Mail hinter der Notiz oder keine aktive Verbindung. */
    case NotApplicable = 'not_applicable';
}
