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
use App\Plugins\OrgaMax\OrgaMaxPlugin;
use Orgamax\API\Client;
use Orgamax\API\Endpoints\InvoicesEndpoint;
use Orgamax\Entities\Invoices\Invoice;

/**
 * Rechnungs-/Zahlungsstatus-Projektion (Feature 077, MVP-311): orgaMAX bleibt
 * führend — WorkDiary spiegelt externe Rechnungsnummer, Zustand, Summen,
 * Kunde, Fälligkeit und letzten Sync als ExternalReference-Payload, ohne den
 * Beleg lokal neu zu erfinden. Der offene Restbetrag stammt direkt aus dem
 * Rechnungsdatensatz (`outstandingAmount`).
 */
class OrgaMaxInvoiceProjector {
    public const EXT_TYPE_INVOICE = 'orgamax_invoice';

    /** @return array{read: int, updated: int} */
    public function project(OrgaMaxConnection $connection, Client $client, int $offset, int $limit): array {
        $rows = (new InvoicesEndpoint($client))->search(['offset' => $offset, 'limit' => $limit])?->getValues() ?? [];
        $updated = 0;

        foreach ($rows as $invoice) {
            $externalId = $invoice->getId() !== null ? (string) $invoice->getId() : '';
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
                    'payload' => $this->projection($invoice),
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
     * @return array<string, mixed>
     */
    private function projection(Invoice $invoice): array {
        $customer = $invoice->getCustomerData();

        return [
            'source' => 'orgamax',
            'number' => (string) ($invoice->getNumber() ?? ''),
            'status' => $invoice->getState()->value ?? '',
            'type' => $invoice->getType()->value ?? '',
            'date' => (string) ($invoice->getDate() ?? ''),
            'total_net' => $invoice->getTotalNet(),
            'total_gross' => $invoice->getTotalGross(),
            // orgaMAX rechnet ausschließlich in Euro — die API führt kein Währungsfeld.
            'currency' => 'EUR',
            'customer' => (string) ($customer?->getName() ?? ''),
            'customer_id' => $invoice->getCustomerId() !== null ? (string) $invoice->getCustomerId() : '',
            'due_on' => (string) ($invoice->getDueToDate() ?? ''),
            'outstanding_amount' => $invoice->getOutstandingAmount(),
        ];
    }
}
