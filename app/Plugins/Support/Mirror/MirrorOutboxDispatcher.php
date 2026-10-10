<?php
/*
 * Created on   : Sun Jul 12 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : MirrorOutboxDispatcher.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Plugins\Support\Mirror;

use App\Contracts\Integration\IntegrationOutboxDispatcher;
use App\Enums\Invoicing\InvoiceStatus;
use App\Enums\Protocol\ProtocolStatus;
use App\Models\Document\Document;
use App\Models\Integration\IntegrationOutboxEntry;
use App\Models\Invoicing\Invoice;
use App\Models\Protocol\Protocol;

/**
 * Gemeinsamer Outbox-Dispatcher der Dokumentspiegelung (MVP-330, Bauturbo A10 —
 * gehoben aus dem WebDAV-Plugin, Feature 058/MVP-127): bindet ein
 * {@see MirrorTarget} an die generische Integrations-Outbox (Feature 055).
 * Idempotenz über den `idempotency_key`, Retry mit Backoff über die Queue,
 * Konflikt-in-Inbox bei terminalem Fehlschlag. Terminale Ergebnisse
 * (mirrored/unchanged/conflict/skipped) bestätigen den Eintrag; transiente
 * Zustellfehler wirft der {@see DocumentMirrorService} → Queue-Wiederholung.
 */
class MirrorOutboxDispatcher implements IntegrationOutboxDispatcher {
    public const OP_MIRROR = 'mirror_document';

    public const OP_MIRROR_INVOICE = 'mirror_invoice';

    public const OP_MIRROR_PROTOCOL = 'mirror_protocol';

    public function __construct(private readonly MirrorTarget $target) {}

    public function pluginId(): string {
        return $this->target->pluginId();
    }

    public function dispatch(IntegrationOutboxEntry $entry): bool {
        // Verbindungs-Gesundheit (MVP-178): Fehler zählen (Aufgabe via
        // ExpiryScanner), Erfolg setzt zurück — die Outbox-Retry-/
        // Kompensations-Semantik bleibt durch das Rethrow unangetastet.
        $connection = $this->target->activeConnection($entry->organization_id);

        try {
            $result = match ($entry->operation) {
                self::OP_MIRROR => $this->mirrorDocument($entry),
                self::OP_MIRROR_INVOICE => $this->mirrorInvoice($entry),
                self::OP_MIRROR_PROTOCOL => $this->mirrorProtocol($entry),
                default => true, // fremde Operation → nichts zu tun
            };
            $connection?->recordConnectionSuccess();

            return $result;
        } catch (\Throwable $e) {
            $connection?->recordConnectionFailure($e->getMessage());

            throw $e;
        }
    }

    private function mirrorDocument(IntegrationOutboxEntry $entry): bool {
        if ($entry->subject_id === null || $entry->subject_type !== (new Document)->getMorphClass()) {
            return true;
        }

        $document = Document::query()->withoutGlobalScopes()->find($entry->subject_id);
        if (! $document instanceof Document) {
            return true; // Dokument gelöscht → nichts zu spiegeln
        }

        $connection = $this->target->activeConnection($entry->organization_id);
        if ($connection === null || ! $connection->mirrorsSource('document')) {
            return true;
        }

        app(DocumentMirrorService::class)->mirror($this->target, $document, $connection, $this->target->gatewayFor($connection));

        return true;
    }

    private function mirrorInvoice(IntegrationOutboxEntry $entry): bool {
        if ($entry->subject_id === null || $entry->subject_type !== (new Invoice)->getMorphClass()) {
            return true;
        }

        $invoice = Invoice::query()->withoutGlobalScopes()->find($entry->subject_id);
        // Nur finalisierte (gestellte) Rechnungen spiegeln — Status nochmals prüfen.
        if (! $invoice instanceof Invoice || $invoice->status !== InvoiceStatus::Issued) {
            return true;
        }

        $connection = $this->target->activeConnection($entry->organization_id);
        if ($connection === null || ! $connection->mirrorsSource(DocumentMirrorService::EXTERNAL_TYPE_INVOICE)) {
            return true;
        }

        app(DocumentMirrorService::class)->mirrorInvoice($this->target, $invoice, $connection, $this->target->gatewayFor($connection));

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

        $connection = $this->target->activeConnection($entry->organization_id);
        if ($connection === null || ! $connection->mirrorsSource(DocumentMirrorService::EXTERNAL_TYPE_PROTOCOL)) {
            return true;
        }

        app(DocumentMirrorService::class)->mirrorProtocol($this->target, $protocol, $connection, $this->target->gatewayFor($connection));

        return true;
    }
}
