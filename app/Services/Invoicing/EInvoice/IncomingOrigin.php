<?php
/*
 * Created on   : Sat Oct 10 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : IncomingOrigin.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Invoicing\EInvoice;

/** Herkunft eines Rechnungseingangs aus dem Postfach (MVP-1107): Absender und Message-ID. */
final class IncomingOrigin {
    public function __construct(
        public readonly ?string $senderEmail = null,
        public readonly ?string $reference = null,
    ) {}
}
