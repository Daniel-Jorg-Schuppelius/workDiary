<?php
/*
 * Created on   : Sun Jul 12 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : MirrorBackfillCommand.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Plugins\Support\Mirror\Console;

use App\Console\Concerns\IteratesOrganizations;
use App\Enums\Document\DocumentStatus;
use App\Enums\Invoicing\InvoiceStatus;
use App\Enums\Protocol\ProtocolStatus;
use App\Models\Document\Document;
use App\Models\Invoicing\Invoice;
use App\Models\Platform\Organization;
use App\Models\Protocol\Protocol;
use App\Plugins\Support\Mirror\{DocumentMirrorService, MirrorOutboxDispatcher, MirrorTarget};
use App\Services\Integration\IntegrationOutboxService;
use Illuminate\Console\Command;

/**
 * Gemeinsamer Voll-Spiegellauf der Ablage-Ziele (MVP-330, Bauturbo A10 —
 * gehoben aus `webdav:mirror`, Feature 058/MVP-127): reiht je Organisation
 * alles idempotent in die Integrations-Outbox ein, was die Anbindung spiegelt —
 * freigegebene Dokumente, gestellte Rechnungen und signierte Protokolle, je
 * nach gewählten Quellen. Aufholpfad neben den ereignisgetriebenen Observern;
 * läuft manuell aus der Admin-UI (bewusst KEIN Scheduler-Registry-Eintrag).
 * Dedupe über dieselben Idempotenzschlüssel wie die Observer.
 */
abstract class MirrorBackfillCommand extends Command {
    use IteratesOrganizations;

    /** Das Ablage-Ziel dieses Commands (WebDAV bzw. SharePoint). */
    abstract protected function target(): MirrorTarget;

    public function handle(IntegrationOutboxService $outbox): int {
        $target = $this->target();

        foreach ($this->organizationsToProcess() as $org) {
            // Kein Throwable-Fang je Org — Fehler sollen wie bisher durchschlagen.
            $this->withOrganizationContext($org, function (Organization $org) use ($outbox, $target): void {
                $connection = $target->activeConnection((int) $org->id);
                if ($connection === null) {
                    return;
                }

                $queued = 0;
                if ($connection->mirrorsSource('document')) {
                    $documents = Document::query()
                        ->where('organization_id', $org->id)
                        ->where('status', DocumentStatus::Active->value)
                        ->whereNotNull('current_version_id')
                        ->get();
                    foreach ($documents as $document) {
                        $outbox->enqueue(
                            (int) $org->id,
                            $target->pluginId(),
                            MirrorOutboxDispatcher::OP_MIRROR,
                            ['document_id' => $document->getKey(), 'version_id' => $document->current_version_id, 'document_type' => $document->document_type->value],
                            $target->idempotencyKey('doc-' . $document->getKey() . ':v' . $document->current_version_id),
                            $document,
                        );
                        $queued++;
                    }
                }

                if ($connection->mirrorsSource(DocumentMirrorService::EXTERNAL_TYPE_INVOICE)) {
                    $invoices = Invoice::query()->where('organization_id', $org->id)->where('status', InvoiceStatus::Issued->value)->get();
                    foreach ($invoices as $invoice) {
                        $outbox->enqueue((int) $org->id, $target->pluginId(), MirrorOutboxDispatcher::OP_MIRROR_INVOICE, ['invoice_id' => $invoice->getKey()], $target->idempotencyKey('invoice-' . $invoice->getKey() . ':issued'), $invoice);
                        $queued++;
                    }
                }

                if ($connection->mirrorsSource(DocumentMirrorService::EXTERNAL_TYPE_PROTOCOL)) {
                    $protocols = Protocol::query()->where('organization_id', $org->id)->where('status', ProtocolStatus::Signed->value)->get();
                    foreach ($protocols as $protocol) {
                        $outbox->enqueue((int) $org->id, $target->pluginId(), MirrorOutboxDispatcher::OP_MIRROR_PROTOCOL, ['protocol_id' => $protocol->getKey(), 'revision' => $protocol->revision], $target->idempotencyKey('protocol-' . $protocol->getKey() . ':r' . $protocol->revision), $protocol);
                        $queued++;
                    }
                }

                $this->info(sprintf('Organisation #%d (%s): %d Objekte eingereiht.', $org->id, $org->name, $queued));
            });
        }

        return self::SUCCESS;
    }
}
