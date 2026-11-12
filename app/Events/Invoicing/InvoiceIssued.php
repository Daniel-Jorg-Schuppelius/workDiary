<?php
/*
 * Created on   : Wed Nov 11 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : InvoiceIssued.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Events\Invoicing;

use Illuminate\Foundation\Events\Dispatchable;

/** Rechnung ausgestellt (MVP-1596) — Folgeprozesse wie „Berechnet“ am Auftrag. */
final class InvoiceIssued {
    use Dispatchable;

    public function __construct(
        public readonly int $organizationId,
        public readonly int $invoiceId,
    ) {}
}
