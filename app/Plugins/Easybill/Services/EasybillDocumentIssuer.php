<?php
/*
 * Created on   : Tue Oct 13 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : EasybillDocumentIssuer.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Plugins\Easybill\Services;

use App\Enums\Numbering\NumberScope;
use App\Enums\Sales\OrderConfirmationStatus;
use App\Models\Billing\DeliveryNote;
use App\Models\Customer\Customer;
use App\Models\Integration\ExternalReference;
use App\Models\Invoicing\Invoice;
use App\Models\Sales\{OrderConfirmation, Quote};
use App\Plugins\Easybill\Api\{EasybillClient, EasybillClientFactory};
use App\Plugins\Easybill\EasybillPlugin;
use App\Services\Numbering\Contracts\DocumentIssuer;
use App\Services\Numbering\Dto\IssuedDocument;
use Illuminate\Database\Eloquent\Model;
use InvalidArgumentException;
use RuntimeException;

/**
 * easybill nummeriert Belege beim Fertigstellen (Phase 145, F1): Der Beleg
 * entsteht als Entwurf (`POST /documents`) und bekommt mit
 * `PUT /documents/{id}/done` seine Nummer. Den Vorgänger nennt `ref_id` statt
 * der Umwandlung `POST /documents/{id}/{type}`, die alle Positionen der
 * Vorlage übernähme (Lieferschein: nur die gelieferten Mengen, ohne Preise).
 * Ein Storno entsteht über `POST /documents/{id}/cancel` der übertragenen
 * Rechnung. Live-Test offen: Die Doku nennt den Zeitpunkt der Nummernvergabe
 * nicht.
 */
final class EasybillDocumentIssuer implements DocumentIssuer {
    public const EXT_TYPE_OFFER = 'easybill_offer';

    public const EXT_TYPE_ORDER = 'easybill_order';

    public const EXT_TYPE_DELIVERY = 'easybill_delivery';

    public function pluginId(): string {
        return EasybillPlugin::ID;
    }

    public function scopes(): array {
        return [NumberScope::Quote, NumberScope::OrderConfirmation, NumberScope::DeliveryNote, NumberScope::Invoice, NumberScope::CreditNote, NumberScope::Cancellation];
    }

    public function issue(Model $document, NumberScope $scope): IssuedDocument {
        [$extType, $type, $predecessor] = match (true) {
            $document instanceof Quote && $scope === NumberScope::Quote => [self::EXT_TYPE_OFFER, 'OFFER', null],
            $document instanceof OrderConfirmation && $scope === NumberScope::OrderConfirmation => [self::EXT_TYPE_ORDER, 'ORDER', $this->predecessor($document->quote, self::EXT_TYPE_OFFER)],
            $document instanceof DeliveryNote && $scope === NumberScope::DeliveryNote => [self::EXT_TYPE_DELIVERY, 'DELIVERY', $this->predecessor($document->orderConfirmation, self::EXT_TYPE_ORDER)],
            $document instanceof Invoice && $scope === NumberScope::Invoice => [EasybillTarget::EXT_TYPE_INVOICE, 'INVOICE', $this->invoicePredecessor($document)],
            $document instanceof Invoice && $scope === NumberScope::CreditNote => [EasybillTarget::EXT_TYPE_INVOICE, 'CREDIT', $this->predecessor($document->parent, EasybillTarget::EXT_TYPE_INVOICE)],
            $document instanceof Invoice && $scope === NumberScope::Cancellation => [EasybillTarget::EXT_TYPE_INVOICE, 'STORNO', $this->predecessor($document->parent, EasybillTarget::EXT_TYPE_INVOICE)],
            default => throw new InvalidArgumentException('easybill issues no ' . $scope->value . ' for ' . $document::class),
        };
        $organizationId = (int) $document->getAttribute('organization_id');

        $reference = ExternalReference::query()
            ->forPlugin($organizationId, EasybillPlugin::ID, $extType)
            ->forReferenceable($document)
            ->first();
        if ($reference instanceof ExternalReference && (string) $reference->external_number !== '') {
            return new IssuedDocument((string) $reference->external_id, (string) $reference->external_number);
        }

        $client = app(EasybillClientFactory::class)->for($organizationId);
        if (! $reference instanceof ExternalReference) {
            $created = $type === 'STORNO'
                ? $this->cancel($document, $client, $predecessor)
                : $client->createDocument($this->payload($document, $type, $predecessor));
            $externalId = (string) ($created['id'] ?? '');
            if ($externalId === '') {
                throw new RuntimeException('easybill POST /documents returned no id.');
            }
            // Erst die Verknüpfung, dann das Fertigstellen: ein Abbruch dazwischen legt beim nächsten Versuch keinen zweiten Beleg an.
            $reference = ExternalReference::create([
                'organization_id' => $organizationId,
                'plugin_id' => EasybillPlugin::ID,
                'external_type' => $extType,
                'referenceable_type' => $document->getMorphClass(),
                'referenceable_id' => $document->getKey(),
                'external_id' => $externalId,
                'payload' => ['document' => $created],
                'synced_at' => now(),
            ]);
        }

        // Das Storno legt easybill fertig an; alle anderen Belege stellt `done` fertig.
        $stored = (array) ($reference->payload['document'] ?? []);
        $done = $type === 'STORNO' && trim((string) ($stored['number'] ?? '')) !== ''
            ? $stored
            : $client->finishDocument((string) $reference->external_id);
        $number = trim((string) ($done['number'] ?? ''));
        if ($number === '') {
            throw new RuntimeException((string) __('numbering.error.no_number', ['program' => 'easybill']));
        }
        $reference->forceFill(['external_number' => $number, 'payload' => ['document' => $done], 'synced_at' => now()])->save();

        return new IssuedDocument((string) $reference->external_id, $number);
    }

    /** @return array<string, mixed> */
    private function payload(Model $document, string $type, ?string $predecessorId): array {
        $document->loadMissing(['items', 'customer']);
        $customer = $document->getRelationValue('customer');
        if (! $customer instanceof Customer) {
            throw new RuntimeException((string) __('easybill::finance.error.easybill_no_customer'));
        }
        $items = $document->getRelationValue('items')->sortBy('position')->values();

        $payload = [
            'type' => $type,
            'customer_id' => (int) app(EasybillPlugin::class)->pushContact($customer),
            'ref_id' => $predecessorId !== null ? (int) $predecessorId : null,
            'document_date' => now()->format('Y-m-d'),
            'text' => (string) (match (true) {
                $document instanceof Quote => $document->terms,
                $document instanceof Invoice => $document->notes,
                default => $document->getAttribute('note'),
            } ?? ''),
            'items' => match (true) {
                $document instanceof DeliveryNote => EasybillLineItems::quantities($items),
                // Gutschrift: easybill erwartet positive Mengen, die Art kehrt das Vorzeichen.
                $document instanceof Invoice => EasybillLineItems::from($items, $document->tax_rate, absolute: $type === 'CREDIT'),
                default => EasybillLineItems::from($items),
            },
        ];
        if ($document instanceof Invoice) {
            $payload['currency'] = $document->currency->value;
            $payload['due_in_days'] = $type === 'INVOICE' ? $document->effectivePaymentTermsDays() : null;
            $payload += EasybillLineItems::documentDiscount($document);
        } elseif ($document instanceof Quote || $document instanceof OrderConfirmation) {
            $payload['currency'] = $document->documentCurrency()->value;
        }

        return array_filter($payload, static fn ($value): bool => $value !== null);
    }

    /** @return array<string, mixed> */
    private function cancel(Model $document, EasybillClient $client, ?string $originalId): array {
        if ($originalId === null) {
            $parent = $document instanceof Invoice ? $document->parent : null;
            throw new RuntimeException((string) __('easybill::finance.error.easybill_cancel_not_transferred', ['number' => $parent->number ?? '—']));
        }

        return $client->cancelDocument($originalId);
    }

    /** Rechnung mit Vorgänger: die AB des Angebots, sonst das Angebot selbst. */
    private function invoicePredecessor(Invoice $invoice): ?string {
        $quote = $invoice->quote_id !== null ? Quote::query()->find($invoice->quote_id) : null;
        if (! $quote instanceof Quote) {
            return null;
        }
        $confirmation = OrderConfirmation::query()->where('quote_id', $quote->id)->where('status', OrderConfirmationStatus::Confirmed)->latest('id')->first();

        return $this->predecessor($confirmation, self::EXT_TYPE_ORDER) ?? $this->predecessor($quote, self::EXT_TYPE_OFFER);
    }

    private function predecessor(?Model $model, string $extType): ?string {
        if ($model === null) {
            return null;
        }
        $id = ExternalReference::query()->forPlugin((int) $model->getAttribute('organization_id'), EasybillPlugin::ID, $extType)->forReferenceable($model)->value('external_id');

        return $id !== null && (string) $id !== '' ? (string) $id : null;
    }
}
