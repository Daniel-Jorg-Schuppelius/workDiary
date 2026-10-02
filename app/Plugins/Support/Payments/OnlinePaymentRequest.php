<?php
/*
 * Created on   : Fri Oct 02 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : OnlinePaymentRequest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Plugins\Support\Payments;

use CommonToolkit\ValueObjects\Money;

/** Auftrag an den Zahlungsanbieter: genau dieser Betrag für genau diese Rechnung. */
final readonly class OnlinePaymentRequest {
    public function __construct(
        /** Eigene Kennung der Zahlung; der Anbieter führt sie als Referenz/Metadatum. */
        public string $reference,
        public Money $amount,
        public string $description,
        public string $returnUrl,
        public string $webhookUrl,
        public string $locale,
        public ?string $customerEmail = null,
    ) {}
}
