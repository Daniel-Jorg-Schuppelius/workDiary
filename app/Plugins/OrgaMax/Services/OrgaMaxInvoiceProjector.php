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

use App\Models\{Customer, ExternalReference, OrgaMaxConnection, OrgaMaxInvoice};
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

            $projection = $this->projection($invoice);

            // Je Rechnung ein eigenes lokales Objekt (MVP-653-Fix): der
            // Unique-Index extref_unique erlaubt nur EINE Referenz je
            // Zielmodell — die frühere Ablage an der Verbindung brach ab der
            // zweiten Rechnung ab.
            $mirror = OrgaMaxInvoice::query()->updateOrCreate([
                'organization_id' => $connection->organization_id,
                'external_id' => $externalId,
            ], [
                'customer_id' => $this->resolveCustomerId($connection->organization_id, $projection['customer_id'] ?? null),
                'customer_external_id' => $this->nullIfBlank($projection['customer_id'] ?? null),
                'customer_name' => $this->nullIfBlank($projection['customer'] ?? null),
                'invoice_type' => $this->nullIfBlank($projection['type'] ?? null),
                'invoice_status' => $this->nullIfBlank($projection['status'] ?? null),
                'invoice_number' => $this->nullIfBlank($projection['number'] ?? null),
                'invoice_date' => $this->dateOrNull($projection['date'] ?? null),
                'due_on' => $this->dateOrNull($projection['due_on'] ?? null),
                'total_net' => $projection['total_net'] ?? null,
                'total_gross' => $projection['total_gross'] ?? null,
                'outstanding_amount' => $projection['outstanding_amount'] ?? null,
                'currency' => $projection['currency'] ?? 'EUR',
                'payload' => $projection,
                'synced_at' => now(),
            ]);

            ExternalReference::link(
                $connection->organization_id,
                OrgaMaxPlugin::ID,
                self::EXT_TYPE_INVOICE,
                $mirror,
                $externalId,
                $projection,
            );
            $updated++;
        }

        return ['read' => count($rows), 'updated' => $updated];
    }

    /**
     * Lokaler Kunde zur orgaMAX-Kundennummer — die Zuordnung entsteht beim
     * Kundenabgleich (Inbox-First), fehlt also solange, bis ein Datensatz
     * bestätigt wurde. Dann bleibt der Spiegel bei `null`.
     */
    private function resolveCustomerId(int $organizationId, mixed $externalCustomerId): ?int {
        $externalCustomerId = $this->nullIfBlank($externalCustomerId);
        if ($externalCustomerId === null) {
            return null;
        }

        $reference = ExternalReference::query()
            ->where('organization_id', $organizationId)
            ->where('plugin_id', OrgaMaxPlugin::ID)
            ->where('external_type', 'customer')
            ->where('external_id', $externalCustomerId)
            ->where('referenceable_type', (new Customer)->getMorphClass())
            ->first();

        return $reference?->referenceable_id;
    }

    private function nullIfBlank(mixed $value): ?string {
        $value = trim((string) ($value ?? ''));

        return $value === '' ? null : mb_substr($value, 0, 191);
    }

    private function dateOrNull(mixed $value): ?string {
        $value = trim((string) ($value ?? ''));
        if ($value === '') {
            return null;
        }

        try {
            return \Carbon\CarbonImmutable::parse($value)->toDateString();
        } catch (\Throwable) {
            return null;
        }
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
