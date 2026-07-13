<?php
/*
 * Created on   : Sun Jul 12 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : MirrorInvoiceObserver.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Plugins\Support\Mirror\Observers;

use App\Models\Invoice;
use App\Plugins\Support\Mirror\{MirrorOutboxDispatcher, MirrorTargetRegistry};
use App\Services\Integration\IntegrationOutboxService;

/**
 * Reiht eine finalisierte (gestellte) Rechnung je Ablage-Ziel in die
 * Integrations-Outbox ein, damit ihr PDF gespiegelt wird (MVP-330, Bauturbo
 * A10 — gehoben aus dem WebDAV-Plugin, Feature 058/MVP-127, Rang 19) — nur
 * wenn die Anbindung die Quelle `invoice_pdf` aktiviert hat. Der
 * Idempotenzschlüssel bindet die Rechnung im Zustand „gestellt"; unveränderte
 * Rechnungen (gleicher SHA im Dispatcher) lösen keinen erneuten Upload aus.
 */
class MirrorInvoiceObserver {
    public function __construct(
        private readonly IntegrationOutboxService $outbox,
        private readonly MirrorTargetRegistry $targets,
    ) {}

    public function saved(Invoice $invoice): void {
        if ($invoice->status !== Invoice::STATUS_ISSUED) {
            return;
        }

        foreach ($this->targets->all() as $target) {
            $connection = $target->activeConnection((int) $invoice->organization_id);
            if ($connection === null || ! $connection->mirrorsSource('invoice_pdf')) {
                continue;
            }

            $this->outbox->enqueue(
                (int) $invoice->organization_id,
                $target->pluginId(),
                MirrorOutboxDispatcher::OP_MIRROR_INVOICE,
                ['invoice_id' => $invoice->getKey()],
                $target->idempotencyKey('invoice-' . $invoice->getKey() . ':issued'),
                $invoice,
            );
        }
    }
}
