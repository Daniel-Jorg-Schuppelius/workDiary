<?php
/*
 * Created on   : Sat Jul 18 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : BhbOutboxDispatcher.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Plugins\BuchhaltungsButler\Services;

use App\Contracts\Integration\IntegrationOutboxDispatcher;
use App\Models\{ExternalReference, IntegrationOutboxEntry, Invoice};
use App\Plugins\BuchhaltungsButler\Api\BhbClientFactory;
use App\Plugins\BuchhaltungsButler\{BhbConfig, BuchhaltungsButlerPlugin};
use App\Services\Invoicing\InvoicePdfRenderer;
use RuntimeException;

/**
 * Beleg-Push nach BuchhaltungsButler (MVP-432): überträgt eine ausgestellte
 * lokale Rechnung als Beleg (PDF + Kernmetadaten) über die generische
 * integration_outbox. Idempotenz zweistufig: Unique-Idempotency-Key je
 * Rechnung (Outbox) + ExternalReference-Check vor dem Upload (Dispatcher).
 * Kein Rück-Sync — BuchhaltungsButler bucht, workDiary fakturiert.
 *
 * Metadaten-Feldnamen (number/date/amount) sind dokumentierte Annahmen —
 * Verifikation am Pilot-Konto (Feature 093, W2.0); das PDF selbst ist der
 * fachlich tragende Inhalt.
 */
class BhbOutboxDispatcher implements IntegrationOutboxDispatcher {
    public const OP_RECEIPT_PUSH = 'receipt.push';

    public const EXT_TYPE_RECEIPT = 'receipt';

    public function pluginId(): string {
        return BuchhaltungsButlerPlugin::ID;
    }

    public function dispatch(IntegrationOutboxEntry $entry): bool {
        return match ($entry->operation) {
            self::OP_RECEIPT_PUSH => $this->pushReceipt($entry),
            default => throw new RuntimeException('Unbekannte BuchhaltungsButler-Outbox-Operation: ' . $entry->operation),
        };
    }

    private function pushReceipt(IntegrationOutboxEntry $entry): bool {
        $invoice = Invoice::query()->withoutGlobalScopes()
            ->where('organization_id', $entry->organization_id)
            ->find((int) ($entry->payload['invoice_id'] ?? 0));
        if (! $invoice instanceof Invoice) {
            return true; // Rechnung existiert nicht mehr → nichts zu tun
        }

        // Referenz-Idempotenz: verspätete Wiederholung lädt nie doppelt hoch.
        $existing = ExternalReference::query()->withoutGlobalScopes()
            ->where('organization_id', $entry->organization_id)
            ->where('plugin_id', BuchhaltungsButlerPlugin::ID)
            ->where('external_type', self::EXT_TYPE_RECEIPT)
            ->where('referenceable_type', $invoice->getMorphClass())
            ->where('referenceable_id', $invoice->getKey())
            ->exists();
        if ($existing) {
            return true;
        }

        $config = BhbConfig::resolve((int) $entry->organization_id);
        if (! $config['enabled'] || ! $config['push_enabled']) {
            return true; // Push (mehr) nicht konfiguriert → No-Op
        }

        $invoice->loadMissing(['items', 'customer']);
        $pdf = app(InvoicePdfRenderer::class)->output($invoice);
        $number = trim((string) $invoice->number);
        $filename = 'rechnung-' . ($number !== '' ? $number : (string) $invoice->getKey()) . '.pdf';

        $body = app(BhbClientFactory::class)
            ->for((int) $entry->organization_id)
            ->uploadReceipt($pdf, $filename, array_filter([
                'number' => $number !== '' ? $number : null,
                'date' => $invoice->issued_on?->format('Y-m-d'),
                'amount' => (string) $invoice->total,
            ], static fn($value): bool => $value !== null));

        // Antwort-ID tolerant lesen; ohne ID trägt der Inhalts-Hash die
        // Idempotenz (Pilot verifiziert den Feldnamen).
        $sha256 = hash('sha256', $pdf);
        $externalId = (string) ($body['id'] ?? data_get($body, 'data.0.id') ?? ('uploaded:' . $sha256));

        ExternalReference::create([
            'organization_id' => $entry->organization_id,
            'plugin_id' => BuchhaltungsButlerPlugin::ID,
            'external_type' => self::EXT_TYPE_RECEIPT,
            'referenceable_type' => $invoice->getMorphClass(),
            'referenceable_id' => $invoice->getKey(),
            'external_id' => $externalId,
            'payload' => [
                'source' => 'buchhaltungsbutler',
                'document_sha256' => $sha256,
                'filename' => $filename,
                'response' => $body,
            ],
            'synced_at' => now(),
        ]);

        return true;
    }
}
