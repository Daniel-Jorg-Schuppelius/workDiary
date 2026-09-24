<?php
/*
 * Created on   : Thu Sep 24 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : DeliveryInvoiced.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Events\Invoicing;

use App\Models\Inventory\StockDelivery;
use App\Models\Invoicing\Invoice;
use Illuminate\Foundation\Events\Dispatchable;

/**
 * Rechnung ausgestellt, die eine Auslieferung abrechnet (Feature 160): Die
 * Fertigung setzt den Fakturastatus. Synchron in der Transaktion — der Status
 * gehört zur Ausstellung und darf nicht ohne sie gelten.
 */
final class DeliveryInvoiced {
    use Dispatchable;

    public function __construct(public readonly StockDelivery $delivery, public readonly Invoice $invoice) {}
}
