<?php
/*
 * Created on   : Sat Oct 10 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ExternalInvoiceCancelled.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Events\Invoicing;

use Illuminate\Foundation\Events\Dispatchable;

/**
 * Gespiegelte Rechnung im Buchhaltungsprogramm storniert (MVP-1119).
 * Der Beleg ist über Morph-Alias und ID adressiert; synchron wie die lokalen
 * Zahlungsereignisse.
 */
final class ExternalInvoiceCancelled {
    use Dispatchable;

    public function __construct(
        public readonly string $documentType,
        public readonly int $documentId,
        public readonly int $organizationId,
    ) {}
}
