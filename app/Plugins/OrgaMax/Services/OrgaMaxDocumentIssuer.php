<?php
/*
 * Created on   : Tue Oct 13 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : OrgaMaxDocumentIssuer.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Plugins\OrgaMax\Services;

use APIToolkit\Entities\ID;
use App\Enums\Numbering\NumberScope;
use App\Models\Contracts\DocumentLine;
use App\Models\Integration\ExternalReference;
use App\Models\Invoicing\Invoice;
use App\Models\Sales\{OrderConfirmation, Quote};
use App\Plugins\OrgaMax\Api\OrgaMaxClientFactory;
use App\Plugins\OrgaMax\Models\OrgaMaxConnection;
use App\Plugins\OrgaMax\OrgaMaxPlugin;
use App\Services\Numbering\Contracts\DocumentIssuer;
use App\Services\Numbering\Dto\IssuedDocument;
use CommonToolkit\ValueObjects\Percentage;
use Illuminate\Database\Eloquent\Model;
use InvalidArgumentException;
use Orgamax\API\Client;
use Orgamax\API\Endpoints\{InvoicesEndpoint, OffersEndpoint, OrdersEndpoint};
use Orgamax\Entities\Offers\Offer;
use Orgamax\Entities\Orders\Order;
use RuntimeException;

/**
 * orgaMAX nummeriert Angebot, Auftragsbestätigung und Rechnung (Phase 145,
 * MVP-1161/1164/1166). Das Angebot bekommt seine Nummer beim Anlegen, die AB
 * entsteht als Auftrag (`POST /order/`, ohne Angebotsbezug). Eine Rechnung
 * legt orgaMAX nur aus einem Auftrag an: Auftrag aus den Positionen der
 * Rechnung, Umwandlung, Sperren — die Nummer vergibt orgaMAX beim Sperren.
 * Gutschrift, Storno und Lieferschein kann orgaMAX per API nicht anlegen.
 */
final class OrgaMaxDocumentIssuer implements DocumentIssuer {
    public const EXT_TYPE_OFFER = 'orgamax_offer';

    public const EXT_TYPE_ORDER_CONFIRMATION = 'orgamax_order_confirmation';

    /** Eigener Typ: `orgamax_invoice` gehört dem Rechnungsspiegel (OrgaMaxInvoiceProjector). */
    public const EXT_TYPE_INVOICE = 'orgamax_issued_invoice';

    public function pluginId(): string {
        return OrgaMaxPlugin::ID;
    }

    public function scopes(): array {
        return [NumberScope::Quote, NumberScope::OrderConfirmation, NumberScope::Invoice];
    }

    public function issue(Model $document, NumberScope $scope): IssuedDocument {
        $extType = match (true) {
            $document instanceof Quote && $scope === NumberScope::Quote => self::EXT_TYPE_OFFER,
            $document instanceof OrderConfirmation && $scope === NumberScope::OrderConfirmation => self::EXT_TYPE_ORDER_CONFIRMATION,
            $document instanceof Invoice && $scope === NumberScope::Invoice => self::EXT_TYPE_INVOICE,
            default => throw new InvalidArgumentException('orgaMAX issues no ' . $scope->value . ' for ' . $document::class),
        };
        $organizationId = (int) $document->getAttribute('organization_id');
        $reference = ExternalReference::query()->forPlugin($organizationId, OrgaMaxPlugin::ID, $extType)->forReferenceable($document)->first();
        if ($reference instanceof ExternalReference && (string) $reference->external_number !== '') {
            return new IssuedDocument((string) $reference->external_id, (string) $reference->external_number);
        }

        $connection = OrgaMaxConnection::query()->withoutGlobalScopes()->where('organization_id', $organizationId)->first();
        if (! $connection instanceof OrgaMaxConnection || ! $connection->isActive()) {
            throw new RuntimeException((string) __('orgamax::orgamax.error.not_connected'));
        }
        $client = app(OrgaMaxClientFactory::class)->for($connection);

        // Erst die Verknüpfung, dann die Nummer: ein Abbruch dazwischen legt beim nächsten Versuch keinen zweiten Beleg an.
        $reference ??= ExternalReference::create([
            'organization_id' => $organizationId,
            'plugin_id' => OrgaMaxPlugin::ID,
            'external_type' => $extType,
            'referenceable_type' => $document->getMorphClass(),
            'referenceable_id' => $document->getKey(),
            'external_id' => match (true) {
                $document instanceof Quote => $this->createOffer($document, $client),
                default => $this->createOrder($document, $client),
            },
            'payload' => [],
            'synced_at' => now(),
        ]);

        $number = match (true) {
            $document instanceof Quote => (string) ((new OffersEndpoint($client))->get(new ID((string) $reference->external_id))?->getNumber() ?? ''),
            $document instanceof OrderConfirmation => (string) ((new OrdersEndpoint($client))->get(new ID((string) $reference->external_id))?->getNumber() ?? ''),
            default => $this->lockInvoice($reference, $client),
        };
        if (trim($number) === '') {
            throw new RuntimeException((string) __('numbering.error.no_number', ['program' => 'orgaMAX']));
        }
        $reference->forceFill(['external_number' => trim($number), 'synced_at' => now()])->save();

        return new IssuedDocument((string) $reference->external_id, trim($number));
    }

    private function createOffer(Quote $quote, Client $client): string {
        $quote->loadMissing(['items', 'customer']);
        $offer = new Offer([
            'customerId' => (int) app(OrgaMaxPlugin::class)->pushContact($quote->customer ?? throw new RuntimeException((string) __('orgamax::orgamax.error.no_customer'))),
            'date' => now()->format('Y-m-d'),
            'notes' => filled($quote->terms) ? (string) $quote->terms : null,
            'positions' => self::positions($quote->items->sortBy('position')->values(), null),
        ]);
        $created = (new OffersEndpoint($client))->create($offer);
        $id = (string) ($created?->getId() ?? '');
        if ($id === '') {
            throw new RuntimeException('orgaMAX offer create returned no id.');
        }

        return $id;
    }

    /** Auftrag für AB und Rechnung — orgaMAX kennt keinen Angebotsbezug beim Anlegen. */
    private function createOrder(OrderConfirmation|Invoice $document, Client $client): string {
        $document->loadMissing(['items', 'customer']);
        $order = new Order([
            'customerId' => (int) app(OrgaMaxPlugin::class)->pushContact($document->customer ?? throw new RuntimeException((string) __('orgamax::orgamax.error.no_customer'))),
            'date' => now()->format('Y-m-d'),
            'notes' => (string) ($document instanceof Invoice ? $document->notes : $document->note) ?: null,
            'positions' => self::positions($document->items->sortBy('position')->values(), $document instanceof Invoice ? $document->tax_rate : null),
        ]);
        $id = (string) ((new OrdersEndpoint($client))->create($order)->getData()?->getId() ?? '');
        if ($id === '') {
            throw new RuntimeException('orgaMAX order create returned no id.');
        }

        return $id;
    }

    /** Auftrag → Rechnung → Sperren; die Zwischenstände stehen in der Referenz, damit ein erneuter Versuch weitermacht. */
    private function lockInvoice(ExternalReference $reference, Client $client): string {
        $payload = (array) $reference->payload;
        $invoiceId = (string) ($payload['invoice_id'] ?? '');
        if ($invoiceId === '') {
            $invoiceId = (string) ((new OrdersEndpoint($client))->createInvoice(new ID((string) $reference->external_id))->getData()?->getInvoice()?->getId() ?? '');
            if ($invoiceId === '') {
                throw new RuntimeException('orgaMAX order to invoice returned no invoice id.');
            }
            $reference->forceFill(['payload' => ['order_id' => (string) $reference->external_id, 'invoice_id' => $invoiceId]])->save();
        }

        return (string) ((new InvoicesEndpoint($client))->lock(new ID($invoiceId))?->getNumber() ?? '');
    }

    /**
     * Preisbehaftete Positionen; Rabatte stecken im Einzelpreis, optionale
     * Angebotspositionen und Gliederungszeilen kennt orgaMAX nicht.
     *
     * @param  iterable<DocumentLine&Model>  $lines
     * @return list<array<string, mixed>>
     */
    private static function positions(iterable $lines, ?Percentage $fallbackRate): array {
        $positions = [];
        foreach ($lines as $line) {
            $quantity = (float) $line->lineQuantity();
            if (! $line->lineKind()->isPriced() || (bool) $line->getAttribute('optional') || $quantity === 0.0) {
                continue;
            }
            $positions[] = array_filter([
                'title' => mb_substr(trim((string) $line->getAttribute('description')) ?: (string) __('Position'), 0, 255),
                'amount' => round($quantity, 4),
                'unit' => $line->lineUnit() ?: (string) __('invoicing.unit_piece'),
                'priceNet' => round($line->netAmount()->toFloat() / $quantity, 4),
                'vatPercent' => (float) (($line->taxRate() ?? $fallbackRate)?->getNumericValue() ?? 0),
                'metaData' => ['type' => 'custom'],
            ], static fn ($value): bool => $value !== '');
        }
        if ($positions === []) {
            throw new RuntimeException((string) __('invoicing.free.error.empty'));
        }

        return $positions;
    }
}
