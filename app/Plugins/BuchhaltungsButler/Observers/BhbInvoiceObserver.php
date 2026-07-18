<?php
/*
 * Created on   : Sat Jul 18 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : BhbInvoiceObserver.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Plugins\BuchhaltungsButler\Observers;

use App\Models\Invoice;
use App\Plugins\BuchhaltungsButler\{BhbConfig, BuchhaltungsButlerPlugin};
use App\Plugins\BuchhaltungsButler\Services\BhbOutboxDispatcher;
use App\Services\Finance\BillingModeResolver;
use App\Services\Integration\IntegrationOutboxService;

/**
 * Schlanker Push-Trigger (MVP-432, Muster ZammadTaskObserver): wird eine
 * lokale Rechnung AUSGESTELLT, wird NUR ein Outbox-Eintrag enqueued — keine
 * BHB-Logik in Model-Events; die Übertragung läuft asynchron über den
 * {@see BhbOutboxDispatcher}. Der Idempotency-Key ist je Rechnung fix
 * (`bhb:invoice:<id>`) — genau EIN Beleg-Push, egal wie oft der Status
 * später wechselt.
 */
class BhbInvoiceObserver {
    public function saved(Invoice $invoice): void {
        // Nur beim Eintritt in „ausgestellt" (Erstellung oder Statuswechsel).
        if ($invoice->status !== Invoice::STATUS_ISSUED) {
            return;
        }
        if (! $invoice->wasRecentlyCreated && ! array_key_exists('status', $invoice->getChanges())) {
            return;
        }

        $config = BhbConfig::resolve((int) $invoice->organization_id);
        if (! $config['enabled'] || ! $config['push_enabled']
            || empty($config['api_client']) || empty($config['api_secret']) || empty($config['api_key'])) {
            return;
        }

        // Beleg-Push gilt nur für lokal geführte Rechnungen — bei externer
        // Rechnungshoheit entsteht der Beleg im führenden System.
        $invoice->loadMissing('customer');
        if (app(BillingModeResolver::class)->effectiveFor($invoice->customer)->isExternal()) {
            return;
        }

        app(IntegrationOutboxService::class)->enqueue(
            (int) $invoice->organization_id,
            BuchhaltungsButlerPlugin::ID,
            BhbOutboxDispatcher::OP_RECEIPT_PUSH,
            ['invoice_id' => $invoice->id],
            'bhb:invoice:' . $invoice->id,
            $invoice,
        );
    }
}
