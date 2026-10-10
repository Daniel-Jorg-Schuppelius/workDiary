<?php
/*
 * Created on   : Sun Aug 23 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : InvoiceIssueException.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Services\Invoicing;

use RuntimeException;

/** Ausstellung abgelehnt — Grund als Code, damit Aufrufer gezielt reagieren (z. B. Validierungsbericht öffnen). */
final class InvoiceIssueException extends RuntimeException {
    public const REASON_PROFORMA = 'proforma';

    public const REASON_APPROVAL_MISSING = 'approval_missing';

    public const REASON_EINVOICE_INVALID = 'einvoice_invalid';

    /** Entwurf ohne Positionen (Feature 160). */
    public const REASON_EMPTY = 'empty';

    /** Der Sammelkunde (MVP-1109) erhält keine eigene Rechnung — er hat keine Anschrift. */
    public const REASON_COLLECTIVE_CUSTOMER = 'collective_customer';

    public function __construct(public readonly string $reason, string $message) {
        parent::__construct($message);
    }
}
