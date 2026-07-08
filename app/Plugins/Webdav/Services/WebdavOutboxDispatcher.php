<?php
/*
 * Created on   : Sun Jul 05 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : WebdavOutboxDispatcher.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Plugins\Webdav\Services;

use App\Contracts\Integration\IntegrationOutboxDispatcher;
use App\Enums\Protocol\ProtocolStatus;
use App\Models\{Document, IntegrationOutboxEntry, Invoice, Protocol, WebdavConnection};
use App\Plugins\Webdav\Contracts\WebdavGatewayFactory;
use App\Services\Invoicing\InvoicePdfRenderer;
use App\Services\Protocol\ProtocolPdfRenderer;
use Illuminate\Support\Facades\Storage;

/**
 * Outbox-Dispatcher der WebDAV-Dokumentspiegelung (Feature 058, MVP-127).
 * Bindet die Spiegelung an die generische Integrations-Outbox (Feature 055):
 * Idempotenz über den `idempotency_key`, Retry mit Backoff über die Queue,
 * Konflikt-in-Inbox bei terminalem Fehlschlag. Terminale Ergebnisse
 * (mirrored/unchanged/conflict/skipped) bestätigen den Eintrag; transiente
 * Zustellfehler wirft der {@see DocumentMirrorService} → Queue-Wiederholung.
 */
class WebdavOutboxDispatcher implements IntegrationOutboxDispatcher {
    public const OP_MIRROR = 'mirror_document';

    public const OP_MIRROR_INVOICE = 'mirror_invoice';

    public const OP_MIRROR_PROTOCOL = 'mirror_protocol';

    public function pluginId(): string {
        return DocumentMirrorService::PLUGIN_ID;
    }

    public function dispatch(IntegrationOutboxEntry $entry): bool {
        return match ($entry->operation) {
            self::OP_MIRROR => $this->mirrorDocument($entry),
            self::OP_MIRROR_INVOICE => $this->mirrorInvoice($entry),
            self::OP_MIRROR_PROTOCOL => $this->mirrorProtocol($entry),
            default => true, // fremde Operation → nichts zu tun
        };
    }

    private function mirrorDocument(IntegrationOutboxEntry $entry): bool {
        if ($entry->subject_id === null || $entry->subject_type !== (new Document)->getMorphClass()) {
            return true;
        }

        $document = Document::query()->withoutGlobalScopes()->find($entry->subject_id);
        if (! $document instanceof Document) {
            return true; // Dokument gelöscht → nichts zu spiegeln
        }

        $connection = $this->activeConnection($entry->organization_id);
        if ($connection === null) {
            return true;
        }

        $gateway = app(WebdavGatewayFactory::class)->for($connection);
        app(DocumentMirrorService::class)->mirror($document, $connection, $gateway);

        return true;
    }

    private function mirrorInvoice(IntegrationOutboxEntry $entry): bool {
        if ($entry->subject_id === null || $entry->subject_type !== (new Invoice)->getMorphClass()) {
            return true;
        }

        $invoice = Invoice::query()->withoutGlobalScopes()->find($entry->subject_id);
        // Nur finalisierte (gestellte) Rechnungen spiegeln — Status nochmals prüfen.
        if (! $invoice instanceof Invoice || $invoice->status !== Invoice::STATUS_ISSUED) {
            return true;
        }

        $connection = $this->activeConnection($entry->organization_id);
        if ($connection === null || ! $connection->mirrorsSource('invoice_pdf')) {
            return true;
        }

        $gateway = app(WebdavGatewayFactory::class)->for($connection);
        $bytes = app(InvoicePdfRenderer::class)->output($invoice);
        $path = $this->invoicePath($invoice);

        app(DocumentMirrorService::class)->mirrorBytes($invoice, 'invoice_pdf', $path, $bytes, 'application/pdf', (string) $invoice->number, $connection, $gateway);

        return true;
    }

    private function mirrorProtocol(IntegrationOutboxEntry $entry): bool {
        if ($entry->subject_id === null || $entry->subject_type !== (new Protocol)->getMorphClass()) {
            return true;
        }

        $protocol = Protocol::query()->withoutGlobalScopes()->find($entry->subject_id);
        // Nur signierte (abgeschlossene) Protokolle spiegeln.
        if (! $protocol instanceof Protocol || $protocol->status !== ProtocolStatus::Signed) {
            return true;
        }

        $connection = $this->activeConnection($entry->organization_id);
        if ($connection === null || ! $connection->mirrorsSource('protocol_pdf')) {
            return true;
        }

        $gateway = app(WebdavGatewayFactory::class)->for($connection);
        // Idempotenter Renderer schreibt das PDF auf 'local' und liefert den Pfad.
        $relativePath = app(ProtocolPdfRenderer::class)->render($protocol);
        $bytes = (string) Storage::disk(ProtocolPdfRenderer::DISK)->get($relativePath);
        $path = $this->protocolPath($protocol);

        app(DocumentMirrorService::class)->mirrorBytes($protocol, 'protocol_pdf', $path, $bytes, 'application/pdf', (string) $protocol->title, $connection, $gateway);

        return true;
    }

    private function activeConnection(int $organizationId): ?WebdavConnection {
        $connection = WebdavConnection::query()->withoutGlobalScopes()
            ->where('organization_id', $organizationId)
            ->where('active', true)
            ->first();

        return $connection instanceof WebdavConnection && $connection->isActive() ? $connection : null;
    }

    /** Deterministischer Remote-Pfad `invoices/<jahr>/<nummer>.pdf` (pfadsicher). */
    private function invoicePath(Invoice $invoice): string {
        $year = $invoice->issued_on?->format('Y') ?? now()->format('Y');
        $number = (string) preg_replace('/[^A-Za-z0-9._-]/', '_', (string) $invoice->number);
        if ($number === '') {
            $number = 'invoice-' . $invoice->getKey();
        }

        return 'invoices/' . $year . '/' . $number . '.pdf';
    }

    /** Deterministischer Remote-Pfad `protocols/<jahr>/protocol-<id>.pdf`. */
    private function protocolPath(Protocol $protocol): string {
        $year = ($protocol->occurred_at ?? now())->format('Y');

        return 'protocols/' . $year . '/protocol-' . $protocol->getKey() . '.pdf';
    }
}
