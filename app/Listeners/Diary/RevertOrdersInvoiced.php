<?php
/*
 * Created on   : Wed Nov 11 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : RevertOrdersInvoiced.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Listeners\Diary;

use App\Events\Finance\BillingTransferCancelled;
use App\Events\Invoicing\InvoiceCancelled;
use App\Services\Diary\OrderInvoicing;

/** Beleg storniert (MVP-1596): „Berechnet“ fällt zurück, wenn kein anderer Beleg den Auftrag abrechnet. */
final class RevertOrdersInvoiced {
    use BilledOrderSources;

    public function __construct(private readonly OrderInvoicing $invoicing) {}

    public function handle(InvoiceCancelled|BillingTransferCancelled $event): void {
        rescue(function () use ($event): void {
            $sources = $this->sourcesOf($event);
            if ($sources !== null) {
                $this->invoicing->release($event->organizationId, $sources['time_entry_ids'], $sources['reference'], $sources['material_usage_ids']);
            }
        });
    }
}
