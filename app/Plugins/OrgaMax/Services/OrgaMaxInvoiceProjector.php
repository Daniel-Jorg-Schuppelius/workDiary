<?php
/*
 * Created on   : Fri Jul 11 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : OrgaMaxInvoiceProjector.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Plugins\OrgaMax\Services;

use App\Models\{ExternalReference, OrgaMaxConnection};
use App\Plugins\OrgaMax\Api\OrgaMaxClient;
use App\Plugins\OrgaMax\OrgaMaxPlugin;

/**
 * Rechnungs-/Zahlungs-/Mahnstatus-Projektion (Feature 077, MVP-311):
 * orgaMAX bleibt führend — WorkDiary spiegelt externe Rechnungsnummer,
 * Status, Summen, Kunde, Fälligkeit und letzten Sync als ExternalReference-
 * Payload, ohne den Beleg lokal neu zu erfinden. Teilzahlungsdetails werden
 * aus dem Rechnungsdatensatz abgeleitet (Pilot-Verifikation, MVP-305).
 */
class OrgaMaxInvoiceProjector {
    public const EXT_TYPE_INVOICE = 'orgamax_invoice';

    /** Dokumentierte Filterzustände der Rechnungsliste. */
    public const STATUSES = ['draft', 'locked', 'paid', 'dunned', 'cancelled'];

    /** @return array{read: int, updated: int} */
    public function project(OrgaMaxConnection $connection, OrgaMaxClient $client, int $offset, int $limit): array {
        $rows = $client->invoices($offset, $limit);
        $updated = 0;

        foreach ($rows as $row) {
            if (! is_array($row)) {
                continue;
            }
            $externalId = (string) ($row['id'] ?? $row['uuid'] ?? '');
            if ($externalId === '') {
                continue;
            }

            ExternalReference::query()->withoutGlobalScopes()->updateOrCreate(
                [
                    'organization_id' => $connection->organization_id,
                    'plugin_id' => OrgaMaxPlugin::ID,
                    'external_type' => self::EXT_TYPE_INVOICE,
                    'external_id' => $externalId,
                ],
                [
                    'referenceable_type' => $connection->getMorphClass(),
                    'referenceable_id' => $connection->getKey(),
                    'payload' => $this->projection($row),
                    'synced_at' => now(),
                ],
            );
            $updated++;
        }

        return ['read' => count($rows), 'updated' => $updated];
    }

    /**
     * Sichtbare Projektion (Herkunft: orgaMAX) — nur fachliche Felder, keine
     * personenbezogenen Vollpayloads in Supportdiagnosen.
     *
     * @param array<string, mixed> $row
     * @return array<string, mixed>
     */
    private function projection(array $row): array {
        return [
            'source' => 'orgamax',
            'number' => (string) ($row['invoiceNumber'] ?? $row['number'] ?? ''),
            'status' => (string) ($row['status'] ?? ''),
            'total_net' => $row['totalNet'] ?? $row['netAmount'] ?? null,
            'total_gross' => $row['totalGross'] ?? $row['grossAmount'] ?? null,
            'currency' => (string) ($row['currency'] ?? 'EUR'),
            'customer' => (string) ($row['customerName'] ?? $row['customer'] ?? ''),
            'customer_id' => (string) ($row['customerId'] ?? ''),
            'order_id' => (string) ($row['orderId'] ?? ''),
            'due_on' => (string) ($row['dueDate'] ?? ''),
            'paid_amount' => $row['paidAmount'] ?? null,
            'dunning_level' => $row['dunningLevel'] ?? null,
        ];
    }
}
