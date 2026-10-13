<?php
/*
 * Created on   : Tue Oct 13 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : SevDeskDocumentIssuer.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Plugins\SevDesk\Services;

use App\Enums\Numbering\NumberScope;
use App\Models\Integration\ExternalReference;
use App\Models\Invoicing\Invoice;
use App\Plugins\SevDesk\Api\{SevDeskClient, SevDeskClientFactory};
use App\Plugins\SevDesk\{SevDeskConfig, SevDeskPlugin};
use App\Services\Numbering\Contracts\DocumentIssuer;
use App\Services\Numbering\Dto\IssuedDocument;
use CommonToolkit\Helper\Data\NumberHelper;
use Illuminate\Database\Eloquent\Model;
use InvalidArgumentException;
use RuntimeException;

/**
 * sevDesk nummeriert Rechnung und Storno beim Ausstellen (Phase 145, MVP-1166/1167).
 * Rechnung: nächste Nummer über `SevSequence`, `saveInvoice` als Entwurf (100),
 * dann `sendBy` — sevDesk führt die Rechnung danach als offen. Storno:
 * `cancelInvoice` der übertragenen Ursprungsrechnung. Gutschriften legt sevDesk
 * nur aus der vollen Rechnung an (`createFromInvoice`), deshalb vergibt es den
 * Gutschriftkreis nicht.
 */
final class SevDeskDocumentIssuer implements DocumentIssuer {
    public function pluginId(): string {
        return SevDeskPlugin::ID;
    }

    public function scopes(): array {
        return [NumberScope::Invoice, NumberScope::Cancellation];
    }

    public function issue(Model $document, NumberScope $scope): IssuedDocument {
        if (! $document instanceof Invoice || ! in_array($scope, $this->scopes(), true)) {
            throw new InvalidArgumentException('sevDesk issues no ' . $scope->value . ' for ' . $document::class);
        }
        $organizationId = (int) $document->organization_id;
        $reference = ExternalReference::query()
            ->forPlugin($organizationId, SevDeskPlugin::ID, SevDeskTarget::EXT_TYPE_INVOICE)
            ->forReferenceable($document)
            ->first();
        if ($reference instanceof ExternalReference && (string) $reference->external_number !== '') {
            return new IssuedDocument((string) $reference->external_id, (string) $reference->external_number);
        }

        $client = app(SevDeskClientFactory::class)->for($organizationId);
        if (! $reference instanceof ExternalReference) {
            $created = $scope === NumberScope::Cancellation
                ? $this->cancellation($document, $client)
                : $this->invoice($document, $client);
            $reference = ExternalReference::create([
                'organization_id' => $organizationId,
                'plugin_id' => SevDeskPlugin::ID,
                'external_type' => SevDeskTarget::EXT_TYPE_INVOICE,
                'referenceable_type' => $document->getMorphClass(),
                'referenceable_id' => $document->getKey(),
                'external_id' => (string) $created['id'],
                'payload' => ['invoice' => $created],
                'synced_at' => now(),
            ]);
        }

        // Erst die Verknüpfung, dann der Versandstatus: ein Abbruch dazwischen legt keine zweite Rechnung an.
        $remote = $client->invoice((string) $reference->external_id);
        $number = trim((string) ($remote['invoiceNumber'] ?? ''));
        if ($number === '') {
            throw new RuntimeException((string) __('numbering.error.no_number', ['program' => 'sevDesk']));
        }
        if ($scope === NumberScope::Invoice && (int) ($remote['status'] ?? 100) === 100) {
            $client->markInvoiceSent((string) $reference->external_id);
        }
        $reference->forceFill(['external_number' => $number, 'synced_at' => now()])->save();

        return new IssuedDocument((string) $reference->external_id, $number);
    }

    /** @return array<string, mixed> */
    private function invoice(Invoice $invoice, SevDeskClient $client): array {
        $invoice->loadMissing(['items', 'customer']);
        $customer = $invoice->customer;
        $config = SevDeskConfig::resolve((int) $invoice->organization_id);
        $sevUserId = (string) ($client->firstSevUser()['id'] ?? '');
        if ($sevUserId === '') {
            throw new RuntimeException('sevDesk /SevUser returned no user for contactPerson.');
        }
        $positions = SevDeskInvoicePositions::from($invoice, $config);
        if ($positions === []) {
            throw new RuntimeException((string) __('invoicing.free.error.empty'));
        }

        $payload = array_filter([
            'objectName' => 'Invoice',
            'mapAll' => true,
            'invoiceType' => 'RE',
            'invoiceNumber' => $client->nextInvoiceNumber(),
            // Entwurf (100); `sendBy` stellt sie danach offen. Nie `enshrine` — sevDesk führt.
            'status' => 100,
            'invoiceDate' => ($invoice->issued_on ?? now())->format('Y-m-d'),
            'deliveryDate' => ($invoice->serviceDateTo() ?? $invoice->issued_on ?? now())->format('Y-m-d'),
            'timeToPay' => $invoice->effectivePaymentTermsDays(),
            'currency' => $invoice->currency->value,
            'discount' => 0,
            'contact' => ['id' => (int) app(SevDeskPlugin::class)->pushContact($customer), 'objectName' => 'Contact'],
            'contactPerson' => ['id' => (int) $sevUserId, 'objectName' => 'SevUser'],
            'header' => (string) __('Rechnung'),
            'footText' => filled($invoice->notes) ? (string) $invoice->notes : '',
        ], static fn ($value): bool => $value !== null);
        if ($client->bookkeepingVersion() === '2.0') {
            $payload['taxRule'] = ['id' => (int) $config['tax_rule_id'], 'objectName' => 'TaxRule'];
        } else {
            $rate = (float) ($invoice->tax_rate?->getNumericValue() ?? $config['default_vat_rate']);
            $payload['taxType'] = 'default';
            $payload['taxRate'] = $rate;
            $payload['taxText'] = (string) __('sevdesk::finance.sevdesk.tax_text', ['rate' => NumberHelper::toUSFormat($rate, 2, trimTrailingZeros: true)]);
        }

        $created = $client->saveInvoice(['invoice' => $payload, 'invoicePosSave' => $positions, 'invoicePosDelete' => null]);
        $invoiceObject = is_array($created['invoice'] ?? null) ? $created['invoice'] : $created;
        if ((string) ($invoiceObject['id'] ?? '') === '') {
            throw new RuntimeException('sevDesk saveInvoice returned no id.');
        }

        return $invoiceObject;
    }

    /** @return array<string, mixed> */
    private function cancellation(Invoice $cancellation, SevDeskClient $client): array {
        $original = $cancellation->parent_invoice_id !== null ? Invoice::query()->find($cancellation->parent_invoice_id) : null;
        $originalId = $original instanceof Invoice
            ? ExternalReference::query()->forPlugin((int) $cancellation->organization_id, SevDeskPlugin::ID, SevDeskTarget::EXT_TYPE_INVOICE)->forReferenceable($original)->value('external_id')
            : null;
        if ($originalId === null) {
            throw new RuntimeException((string) __('sevdesk::finance.error.sevdesk_cancel_not_transferred', ['number' => $original->number ?? '—']));
        }

        $created = $client->cancelInvoice((string) $originalId);
        if ((string) ($created['id'] ?? '') === '') {
            throw new RuntimeException('sevDesk cancelInvoice returned no id.');
        }

        return $created;
    }
}
