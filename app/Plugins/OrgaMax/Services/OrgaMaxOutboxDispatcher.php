<?php
/*
 * Created on   : Fri Jul 11 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : OrgaMaxOutboxDispatcher.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Plugins\OrgaMax\Services;

use App\Contracts\Integration\IntegrationOutboxDispatcher;
use App\Models\{ExternalReference, IntegrationOutboxEntry, OrgaMaxConnection};
use App\Plugins\OrgaMax\Api\{OrgaMaxApiException, OrgaMaxClientFactory};
use App\Plugins\OrgaMax\OrgaMaxPlugin;
use RuntimeException;

/**
 * Zustellung der orgaMAX-Schreibbefehle über die Integrations-Outbox
 * (Feature 077, MVP-313): idempotent gegenüber dem `idempotency_key`.
 *
 * Operationen:
 * - `invoice.convert`  POST /order/{id}/invoice — nur nach ausdrücklicher
 *   Bestätigung enqueued; idempotent über die Invoice-Referenz des Auftrags.
 * - `invoice.send`     POST /invoice/{id}/send — Empfängervorschau bestätigt.
 * - `payment.push`     POST /invoice/{id}/payment — nur bei konfiguriertem
 *   WorkDiary-geführtem Zahlungseingang, mit Dublettenprüfung.
 * - `expense.push`     bleibt bis zum bestätigten Receipt-Pilot BLOCKIERT
 *   (MVP-312) — sichtbarer Fehler statt undokumentiertem Call.
 *
 * `PUT /invoice/{id}/lock` läuft bewusst NIE über die Outbox: irreversibel,
 * nur als direkte, bestätigte Nutzeraktion (MVP-310).
 */
class OrgaMaxOutboxDispatcher implements IntegrationOutboxDispatcher {
    public function __construct(private readonly OrgaMaxClientFactory $clients) {}

    public function pluginId(): string {
        return OrgaMaxPlugin::ID;
    }

    public function dispatch(IntegrationOutboxEntry $entry): bool {
        $connection = OrgaMaxConnection::query()
            ->withoutGlobalScopes()
            ->where('organization_id', $entry->organization_id)
            ->first();
        if (! $connection instanceof OrgaMaxConnection || ! $connection->isActive()) {
            throw new RuntimeException('orgaMAX-Verbindung nicht aktiv.');
        }

        $client = $this->clients->for($connection);
        $payload = (array) $entry->payload;

        return match ((string) $entry->operation) {
            'invoice.convert' => $this->convert($connection, $client, $entry, $payload),
            'invoice.send' => $this->send($client, $payload),
            'payment.push' => $this->pushPayment($connection, $client, $entry, $payload),
            'expense.push' => throw new RuntimeException('Expense-Receipt-Vertrag unbestätigt — Übergabe blockiert (MVP-312).'),
            default => throw new RuntimeException('Unbekannte orgaMAX-Operation: ' . (string) $entry->operation),
        };
    }

    /** @param array<string, mixed> $payload */
    private function convert(OrgaMaxConnection $connection, \App\Plugins\OrgaMax\Api\OrgaMaxClient $client, IntegrationOutboxEntry $entry, array $payload): bool {
        $orderId = (string) ($payload['order_id'] ?? '');
        if ($orderId === '') {
            throw new RuntimeException('invoice.convert ohne order_id.');
        }

        // Idempotenz: existiert zur Order bereits eine Rechnungs-Referenz
        // (früherer Lauf mit unklarem Ausgang), wird sie übernommen.
        $existing = ExternalReference::query()
            ->withoutGlobalScopes()
            ->where('organization_id', $entry->organization_id)
            ->where('plugin_id', OrgaMaxPlugin::ID)
            ->where('external_type', 'orgamax_converted_invoice')
            ->where('external_id', 'order:' . $orderId)
            ->first();
        if ($existing instanceof ExternalReference) {
            return true;
        }

        $body = $client->orderToInvoice($orderId);
        $invoiceId = (string) ($body['id'] ?? $body['invoiceId'] ?? '');

        ExternalReference::create([
            'organization_id' => $entry->organization_id,
            'plugin_id' => OrgaMaxPlugin::ID,
            'external_type' => 'orgamax_converted_invoice',
            'referenceable_type' => $connection->getMorphClass(),
            'referenceable_id' => $connection->getKey(),
            'external_id' => 'order:' . $orderId,
            'payload' => ['source' => 'orgamax', 'invoice_id' => $invoiceId, 'order_id' => $orderId] + $body,
            'synced_at' => now(),
        ]);

        return true;
    }

    /** @param array<string, mixed> $payload */
    private function send(\App\Plugins\OrgaMax\Api\OrgaMaxClient $client, array $payload): bool {
        $invoiceId = (string) ($payload['invoice_id'] ?? '');
        if ($invoiceId === '') {
            throw new RuntimeException('invoice.send ohne invoice_id.');
        }

        try {
            $client->sendInvoice($invoiceId, (array) ($payload['message'] ?? []));
        } catch (OrgaMaxApiException $e) {
            // Bereits versendete Rechnungen gelten als bestätigt (idempotent).
            if ($e->status === 409) {
                return true;
            }

            throw $e;
        }

        return true;
    }

    /** @param array<string, mixed> $payload */
    private function pushPayment(OrgaMaxConnection $connection, \App\Plugins\OrgaMax\Api\OrgaMaxClient $client, IntegrationOutboxEntry $entry, array $payload): bool {
        if ($connection->capabilityLeader('payments') !== 'workdiary') {
            throw new RuntimeException('Zahlungen führt orgaMAX — payment.push ist nur bei WorkDiary-geführtem Zahlungseingang zulässig.');
        }
        $invoiceId = (string) ($payload['invoice_id'] ?? '');
        if ($invoiceId === '') {
            throw new RuntimeException('payment.push ohne invoice_id.');
        }

        // Dublettenprüfung: gleicher Betrag + Datum bereits gemeldet?
        $marker = 'payment:' . $invoiceId . ':' . (string) ($payload['amount'] ?? '') . ':' . (string) ($payload['date'] ?? '');
        $existing = ExternalReference::query()
            ->withoutGlobalScopes()
            ->where('organization_id', $entry->organization_id)
            ->where('plugin_id', OrgaMaxPlugin::ID)
            ->where('external_type', 'orgamax_payment')
            ->where('external_id', $marker)
            ->first();
        if ($existing instanceof ExternalReference) {
            return true;
        }

        $client->addPayment($invoiceId, array_intersect_key($payload, array_flip(['amount', 'date', 'note'])));

        ExternalReference::create([
            'organization_id' => $entry->organization_id,
            'plugin_id' => OrgaMaxPlugin::ID,
            'external_type' => 'orgamax_payment',
            'referenceable_type' => $connection->getMorphClass(),
            'referenceable_id' => $connection->getKey(),
            'external_id' => $marker,
            'payload' => ['source' => 'workdiary'] + $payload,
            'synced_at' => now(),
        ]);

        return true;
    }
}
