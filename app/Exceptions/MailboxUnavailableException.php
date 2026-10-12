<?php
/*
 * Created on   : Mon Oct 12 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : MailboxUnavailableException.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Exceptions;

use RuntimeException;

/**
 * Der Transport eines Postfachs kann gerade nicht abrufen, etwa weil die
 * Microsoft-365-Verbindung fehlt oder ein Recht nicht erteilt ist (MVP-1141).
 * Die Meldung steht danach am Postfach.
 */
final class MailboxUnavailableException extends RuntimeException {}
