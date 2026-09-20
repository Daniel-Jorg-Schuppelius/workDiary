<?php
/*
 * Created on   : Sat Sep 20 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : DocumentTextUnavailableException.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Exceptions;

use App\Enums\Document\DocumentTextFailure;
use RuntimeException;
use Throwable;

/**
 * Eine Dokumentversion gibt keinen Text her (MVP-819). Der Grund steckt im
 * Enum, nicht in der Meldung — der Index legt ihn ab, der KI-Pfad übersetzt
 * ihn für die Person.
 */
class DocumentTextUnavailableException extends RuntimeException {
    public function __construct(
        public readonly DocumentTextFailure $reason,
        ?Throwable $previous = null,
    ) {
        parent::__construct('Dokumentversion liefert keinen Text: ' . $reason->value, 0, $previous);
    }
}
