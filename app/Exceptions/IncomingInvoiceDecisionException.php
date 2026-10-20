<?php
/*
 * Created on   : Tue Oct 20 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : IncomingInvoiceDecisionException.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Exceptions;

use App\Enums\Invoicing\IncomingEInvoiceStatus;
use RuntimeException;

/** Fachliche Entscheidung im Rechnungseingang (MVP-1110/1249): Richtung oder Statusübergang lassen sie nicht zu. */
final class IncomingInvoiceDecisionException extends RuntimeException {
    public static function outgoingNotPayable(): self {
        return new self((string) __('Ausgangsbelege werden nicht zur Zahlung freigegeben.'));
    }

    public static function transitionNotAllowed(IncomingEInvoiceStatus $from, IncomingEInvoiceStatus $to): self {
        return new self((string) __('Übergang :from → :to ist nicht zulässig.', ['from' => $from->value, 'to' => $to->value]));
    }
}
