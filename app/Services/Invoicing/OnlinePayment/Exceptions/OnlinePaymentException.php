<?php
/*
 * Created on   : Fri Oct 02 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : OnlinePaymentException.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Invoicing\OnlinePayment\Exceptions;

use RuntimeException;

/** Rechnung ist gerade nicht online zahlbar; `reason` steuert die Seite für den Kunden. */
final class OnlinePaymentException extends RuntimeException {
    public const PAID = 'paid';

    public const NOT_PAYABLE = 'not_payable';

    public const UNAVAILABLE = 'unavailable';

    public function __construct(public readonly string $reason) {
        parent::__construct('Online-Zahlung nicht möglich: ' . $reason);
    }
}
