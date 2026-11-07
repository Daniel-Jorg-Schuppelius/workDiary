<?php
/*
 * Created on   : Sat Nov 07 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : OrgaMaxExternalInvoiceProvider.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Plugins\OrgaMax\Services;

use APIToolkit\Entities\ID;
use App\Enums\Finance\{ExternalInvoiceFormat, ExternalInvoiceStatus, ProviderSendState};
use App\Models\Finance\{BillingTransfer, ExternalInvoice};
use App\Models\Integration\ExternalReference;
use App\Plugins\OrgaMax\Api\OrgaMaxClientFactory;
use App\Plugins\OrgaMax\Models\OrgaMaxConnection;
use App\Plugins\OrgaMax\OrgaMaxPlugin;
use App\Services\Finance\Contracts\ExternalInvoiceProvider;
use App\Services\Finance\Dto\{ExternalInvoiceFile, ExternalInvoiceSnapshot};
use Carbon\CarbonImmutable;
use CommonToolkit\ValueObjects\Decimal;
use Orgamax\API\Client;
use Orgamax\API\Endpoints\InvoicesEndpoint;
use RuntimeException;

/**
 * orgaMAX als Fakturasystem mit Rechnungshoheit (Feature 196), belegt an der
 * OpenAPI des SDK: Stand `GET /invoice/{id}`, Original nur als PDF über
 * `GET /invoice/document/{id}`; eine XML-Route gibt es nicht, ein Versandvermerk
 * fehlt. Die Rechnung entsteht aus dem übergebenen Auftrag
 * (Referenz `orgamax_converted_invoice`) oder wird direkt zugeordnet.
 */
final class OrgaMaxExternalInvoiceProvider implements ExternalInvoiceProvider {
    public function __construct(private readonly OrgaMaxClientFactory $clients) {}

    public function key(): string {
        return OrgaMaxPlugin::ID;
    }

    public function label(): string {
        return 'orgaMAX';
    }

    public function isConfigured(?int $organizationId): bool {
        return $organizationId !== null && $this->connection($organizationId) !== null;
    }

    public function invoiceIdFor(BillingTransfer $transfer): ?string {
        $orderId = ExternalReference::query()
            ->where('plugin_id', OrgaMaxPlugin::ID)
            ->where('external_type', OrgaMaxTarget::EXT_TYPE_ORDER)
            ->where('referenceable_type', $transfer->getMorphClass())
            ->where('referenceable_id', $transfer->getKey())
            ->latest('id')
            ->value('external_id');
        if ($orderId === null || $orderId === '') {
            return null;
        }
        $converted = ExternalReference::query()
            ->forPlugin((int) $transfer->organization_id, OrgaMaxPlugin::ID, 'orgamax_converted_invoice')
            ->forExternalId('order:' . $orderId)
            ->first();
        $invoiceId = (string) data_get($converted?->payload, 'invoice_id', '');

        return $invoiceId !== '' ? $invoiceId : null;
    }

    public function fetch(int $organizationId, string $externalId): ExternalInvoiceSnapshot {
        $invoice = (new InvoicesEndpoint($this->client($organizationId)))->get(new ID($externalId))
            ?? throw new RuntimeException('orgaMAX invoice ' . $externalId . ' not found.');
        $state = $invoice->getState()?->value;
        $lines = [];
        foreach ($invoice->getPositions()?->getValues() ?? [] as $position) {
            $data = $position->toArray();
            $lines[] = [
                'name' => trim((string) ($data['title'] ?? '')),
                'quantity' => Decimal::ofFloat((float) ($data['amount'] ?? 1), 3)->getValue(),
                'unit_price' => isset($data['priceNet']) ? Decimal::ofFloat((float) $data['priceNet'], 4)->getValue() : null,
                'net' => Decimal::ofFloat((float) ($data['totalNet'] ?? 0), 2)->getValue(),
            ];
        }

        return new ExternalInvoiceSnapshot(
            externalId: $externalId,
            status: match ($state) {
                'draft' => ExternalInvoiceStatus::Draft,
                'locked', 'partiallyPaid' => ExternalInvoiceStatus::Open,
                'paid' => ExternalInvoiceStatus::Paid,
                'cancelled' => ExternalInvoiceStatus::Voided,
                default => ExternalInvoiceStatus::Unknown,
            },
            rawStatus: $state,
            number: $invoice->getNumber() ?: null,
            issuedOn: $invoice->getDate() ? CarbonImmutable::parse($invoice->getDate()) : null,
            kind: ExternalInvoice::KIND_INVOICE,
            currency: 'EUR',
            netAmount: $invoice->getTotalNet() !== null ? Decimal::ofFloat($invoice->getTotalNet(), 2)->getValue() : null,
            grossAmount: $invoice->getTotalGross() !== null ? Decimal::ofFloat($invoice->getTotalGross(), 2)->getValue() : null,
            lines: $lines,
            sent: ProviderSendState::Unknown,
            formatHint: ExternalInvoiceFormat::Pdf,
        );
    }

    public function original(int $organizationId, ExternalInvoiceSnapshot $snapshot, bool $preferXml = false): ?ExternalInvoiceFile {
        $pdf = (new InvoicesEndpoint($this->client($organizationId)))->document(new ID($snapshot->externalId), 'attachment');

        return $pdf === '' ? null : new ExternalInvoiceFile($pdf, 'application/pdf', '');
    }

    public function candidates(int $organizationId, int $customerId): array {
        return [];
    }

    private function connection(int $organizationId): ?OrgaMaxConnection {
        return OrgaMaxConnection::query()->withoutGlobalScopes()->where('organization_id', $organizationId)->first();
    }

    private function client(int $organizationId): Client {
        $connection = $this->connection($organizationId) ?? throw new RuntimeException('orgaMAX connection is not configured.');

        return $this->clients->for($connection);
    }
}
