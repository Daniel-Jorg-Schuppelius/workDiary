<?php
/*
 * Created on   : Wed Nov 11 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : MarkOrdersInvoiced.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Listeners\Diary;

use App\Events\Finance\BillingTransferCompleted;
use App\Events\Invoicing\InvoiceIssued;
use App\Services\Diary\OrderInvoicing;

/** Rechnung ausgestellt oder Übergabe vollzogen (MVP-1596, Material MVP-1624): abgenommene Aufträge gelten als berechnet. */
final class MarkOrdersInvoiced {
    use BilledOrderSources;

    public function __construct(private readonly OrderInvoicing $invoicing) {}

    public function handle(InvoiceIssued|BillingTransferCompleted $event): void {
        // Der Auftragsstatus darf keinen Beleg aufhalten.
        rescue(function () use ($event): void {
            $sources = $this->sourcesOf($event);
            if ($sources !== null) {
                $this->invoicing->markBilled($event->organizationId, $sources['time_entry_ids'], $sources['reference'], $sources['origin'], $sources['material_usage_ids']);
            }
        });
    }
}
