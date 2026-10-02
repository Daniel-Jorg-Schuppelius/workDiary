<?php
/*
 * Created on   : Fri Oct 02 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : DatevOnlineException.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Plugins\DatevOnline\Exceptions;

use RuntimeException;

/** Übertragung an DATEV nicht möglich; `reason` ist der Textschlüssel unter `datev-online::datev.error`. */
final class DatevOnlineException extends RuntimeException {
    public function __construct(public readonly string $reason) {
        parent::__construct('DATEV-Online: ' . $reason);
    }
}
