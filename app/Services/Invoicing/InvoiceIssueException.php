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
    public const string REASON_PROFORMA = 'proforma';

    public const string REASON_APPROVAL_MISSING = 'approval_missing';

    public const string REASON_EINVOICE_INVALID = 'einvoice_invalid';

    /** Entwurf ohne Positionen (Feature 160). */
    public const string REASON_EMPTY = 'empty';

    /** Der Sammelkunde (MVP-1109) erhält keine eigene Rechnung — er hat keine Anschrift. */
    public const string REASON_COLLECTIVE_CUSTOMER = 'collective_customer';

    /** Entwurfsnummer: Die Nummer vergibt ein Buchhaltungsprogramm beim Übertragen (MVP-1153). */
    public const string REASON_DRAFT_NUMBER = 'draft_number';

    /** Das vergebende Programm hat den Beleg nicht übernommen (MVP-1166). */
    public const string REASON_PROGRAM = 'program';

    public function __construct(public readonly string $reason, string $message, ?\Throwable $previous = null) {
        parent::__construct($message, 0, $previous);
    }
}
