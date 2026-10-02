<?php
/*
 * Created on   : Fri Oct 02 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : OnlinePaymentCheckout.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Plugins\Support\Payments;

use Carbon\CarbonImmutable;

/** Angelegte Bezahlseite beim Anbieter. */
final readonly class OnlinePaymentCheckout {
    public function __construct(
        public string $providerReference,
        public string $checkoutUrl,
        public ?CarbonImmutable $expiresAt = null,
    ) {}
}
