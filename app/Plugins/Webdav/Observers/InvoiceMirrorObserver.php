<?php
/*
 * Created on   : Mon Jul 07 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : InvoiceMirrorObserver.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Plugins\Webdav\Observers;

use App\Models\{Invoice, WebdavConnection};
use App\Plugins\Webdav\Services\{DocumentMirrorService, WebdavOutboxDispatcher};
use App\Services\Integration\IntegrationOutboxService;

/**
 * Reiht eine finalisierte (gestellte) Rechnung in die Integrations-Outbox ein,
 * damit ihr PDF in die WebDAV-Ablage gespiegelt wird (Feature 058, MVP-127,
 * Rang 19) — nur wenn die Anbindung die Quelle `invoice_pdf` aktiviert hat. Der
 * Idempotenzschlüssel bindet die Rechnung im Zustand „gestellt"; unveränderte
 * Rechnungen (gleicher SHA im Dispatcher) lösen keinen erneuten Upload aus.
 */
class InvoiceMirrorObserver {
    public function __construct(private readonly IntegrationOutboxService $outbox) {}

    public function saved(Invoice $invoice): void {
        if ($invoice->status !== Invoice::STATUS_ISSUED) {
            return;
        }

        $connection = WebdavConnection::query()
            ->where('organization_id', $invoice->organization_id)
            ->where('active', true)
            ->first();
        if (! $connection instanceof WebdavConnection || ! $connection->mirrorsSource('invoice_pdf')) {
            return;
        }

        $this->outbox->enqueue(
            (int) $invoice->organization_id,
            DocumentMirrorService::PLUGIN_ID,
            WebdavOutboxDispatcher::OP_MIRROR_INVOICE,
            ['invoice_id' => $invoice->getKey()],
            'mirror:invoice-' . $invoice->getKey() . ':issued',
            $invoice,
        );
    }
}
