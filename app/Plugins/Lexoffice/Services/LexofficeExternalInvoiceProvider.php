<?php
/*
 * Created on   : Sat Nov 07 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : LexofficeExternalInvoiceProvider.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Plugins\Lexoffice\Services;

use APIToolkit\Entities\ID;
use App\Enums\Finance\{ExternalInvoiceFormat, ExternalInvoiceStatus, ProviderSendState};
use App\Models\Finance\{BillingTransfer, ExternalInvoice};
use App\Models\Integration\ExternalReference;
use App\Plugins\Lexoffice\Api\LexofficeClientFactory;
use App\Plugins\Lexoffice\LexofficePlugin;
use App\Plugins\Lexoffice\Models\LexofficeVoucher;
use App\Services\Finance\Contracts\ExternalInvoiceProvider;
use App\Services\Finance\Dto\{ExternalInvoiceFile, ExternalInvoiceSnapshot};
use Carbon\CarbonImmutable;
use CommonToolkit\ValueObjects\Decimal;
use Lexoffice\API\Endpoints\Documents\InvoicesEndpoint;

/**
 * Lexware Office als Fakturasystem mit Rechnungshoheit (Feature 196): Stand über
 * `GET /v1/invoices/{id}`, das Original über `GET /v1/invoices/{id}/file`. Das
 * E-Rechnungsprofil der Rechnung entscheidet über die Fassung: XRechnung als
 * XML (das PDF ist dort nur Vorschau), EN16931 als PDF mit eingebetteter XML.
 * Die API meldet nicht, ob Lexware Office die Rechnung selbst versandt hat.
 */
final class LexofficeExternalInvoiceProvider implements ExternalInvoiceProvider {
    private const PROFILE_XRECHNUNG = 'XRechnung';

    public function key(): string {
        return LexofficePlugin::ID;
    }

    public function label(): string {
        return 'Lexware Office';
    }

    public function isConfigured(?int $organizationId): bool {
        return app(LexofficeClientFactory::class)->isConfigured($organizationId);
    }

    public function invoiceIdFor(BillingTransfer $transfer): ?string {
        $reference = $transfer->externalReference;
        if ($reference === null || $reference->plugin_id !== LexofficePlugin::ID || $reference->external_type !== LexofficeTarget::EXT_TYPE_INVOICE) {
            $reference = ExternalReference::query()
                ->where('plugin_id', LexofficePlugin::ID)
                ->where('external_type', LexofficeTarget::EXT_TYPE_INVOICE)
                ->where('referenceable_type', $transfer->getMorphClass())
                ->where('referenceable_id', $transfer->getKey())
                ->latest('id')
                ->first();
        }

        return $reference?->external_id;
    }

    public function fetch(int $organizationId, string $externalId): ExternalInvoiceSnapshot {
        $client = app(LexofficeClientFactory::class)->for($organizationId);
        $data = (new InvoicesEndpoint($client))->get(new ID($externalId))->toArray();
        $total = (array) ($data['totalPrice'] ?? []);
        $profile = (string) ($data['electronicDocumentProfile'] ?? '');

        return new ExternalInvoiceSnapshot(
            externalId: $externalId,
            status: $this->status((string) ($data['voucherStatus'] ?? '')),
            rawStatus: isset($data['voucherStatus']) ? (string) $data['voucherStatus'] : null,
            number: isset($data['voucherNumber']) && $data['voucherNumber'] !== '' ? (string) $data['voucherNumber'] : null,
            issuedOn: isset($data['voucherDate']) ? CarbonImmutable::parse((string) $data['voucherDate']) : null,
            kind: ExternalInvoice::KIND_INVOICE,
            currency: (string) ($total['currency'] ?? 'EUR'),
            netAmount: isset($total['totalNetAmount']) ? Decimal::ofFloat((float) $total['totalNetAmount'], 2)->getValue() : null,
            grossAmount: isset($total['totalGrossAmount']) ? Decimal::ofFloat((float) $total['totalGrossAmount'], 2)->getValue() : null,
            lines: $this->lines((array) ($data['lineItems'] ?? [])),
            sent: ProviderSendState::Unknown,
            formatHint: $profile === self::PROFILE_XRECHNUNG ? ExternalInvoiceFormat::XRechnungUbl : ($profile === 'EN16931' ? ExternalInvoiceFormat::Zugferd : ExternalInvoiceFormat::Pdf),
            extra: ['profile' => $profile],
        );
    }

    public function original(int $organizationId, ExternalInvoiceSnapshot $snapshot, bool $preferXml = false): ?ExternalInvoiceFile {
        $accept = ($snapshot->extra['profile'] ?? null) === self::PROFILE_XRECHNUNG ? 'application/xml' : 'application/pdf';
        $file = (new LexofficeVoucherFileService($organizationId))->invoiceFile($snapshot->externalId, $accept);
        if ($file === null) {
            return null;
        }
        $mime = trim(explode(';', (string) ($file->getContentType() ?? $accept))[0]);

        return new ExternalInvoiceFile($file->getContent(), $mime !== '' ? $mime : $accept, (string) ($file->getFileName() ?? ''));
    }

    public function candidates(int $organizationId, int $customerId): array {
        return array_values(LexofficeVoucher::query()->withoutGlobalScopes()
            ->where('organization_id', $organizationId)
            ->where('customer_id', $customerId)
            ->whereIn('voucher_type', ['invoice', 'salesinvoice'])
            ->whereNotIn('voucher_status', ['draft', 'voided'])
            ->orderByDesc('voucher_date')
            ->limit(50)
            ->get(['external_id', 'voucher_number', 'voucher_date'])
            ->map(static fn (LexofficeVoucher $v): array => [
                'external_id' => (string) $v->external_id,
                'number' => $v->voucher_number,
                'date' => $v->voucher_date?->toDateString(),
            ])
            ->all());
    }

    private function status(string $status): ExternalInvoiceStatus {
        return match ($status) {
            'draft' => ExternalInvoiceStatus::Draft,
            'open', 'overdue', 'sepadebit', 'transferred' => ExternalInvoiceStatus::Open,
            'paid', 'paidoff' => ExternalInvoiceStatus::Paid,
            'voided' => ExternalInvoiceStatus::Voided,
            default => ExternalInvoiceStatus::Unknown,
        };
    }

    /**
     * Textzeilen tragen keinen Betrag und zählen nicht als Position.
     *
     * @param  array<int, mixed>  $items
     * @return list<array{name: string, quantity: string, unit_price: string|null, net: string}>
     */
    private function lines(array $items): array {
        $lines = [];
        foreach ($items as $item) {
            $item = (array) $item;
            if (($item['type'] ?? null) === 'text') {
                continue;
            }
            $quantity = Decimal::ofFloat((float) ($item['quantity'] ?? 0), 3);
            $unit = (array) ($item['unitPrice'] ?? []);
            $unitNet = isset($unit['netAmount']) ? Decimal::ofFloat((float) $unit['netAmount'], 4) : null;
            $discount = (float) ($item['discountPercentage'] ?? 0);
            $net = $unitNet === null ? Decimal::zero(2) : $quantity->times($unitNet, 2);
            if ($discount > 0) {
                $net = $net->times(Decimal::ofFloat(1 - $discount / 100, 6), 2);
            }
            $lines[] = [
                'name' => (string) ($item['name'] ?? ''),
                'quantity' => $quantity->getValue(),
                'unit_price' => $unitNet?->getValue(),
                'net' => $net->getValue(),
            ];
        }

        return $lines;
    }
}
