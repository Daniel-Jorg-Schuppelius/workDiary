<?php
/*
 * Created on   : Sat Nov 07 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : EasybillExternalInvoiceProvider.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Plugins\Easybill\Services;

use App\Enums\Finance\{ExternalInvoiceFormat, ExternalInvoiceStatus, ProviderSendState};
use App\Models\Finance\{AccountingVoucher, BillingTransfer, ExternalInvoice};
use App\Models\Integration\ExternalReference;
use App\Plugins\Easybill\Api\EasybillClientFactory;
use App\Plugins\Easybill\EasybillPlugin;
use App\Services\Finance\Accounting\Vouchers\VoucherMirror;
use App\Services\Finance\Contracts\ExternalInvoiceProvider;
use App\Services\Finance\Dto\{ExternalInvoiceFile, ExternalInvoiceSnapshot};
use Carbon\CarbonImmutable;
use CommonToolkit\ValueObjects\Decimal;

/**
 * easybill als Fakturasystem mit Rechnungshoheit (Feature 196): Stand über
 * `GET /documents/{id}`, das Original über `/documents/{id}/download` (je
 * `file_format_config` XRechnung-XML oder ZUGFeRD-PDF), sonst `/pdf`. Beträge
 * sind Cent. Einen Versandvermerk liefert der Beleg nicht.
 */
final class EasybillExternalInvoiceProvider implements ExternalInvoiceProvider {
    public function __construct(private readonly EasybillClientFactory $clients) {}

    public function key(): string {
        return EasybillPlugin::ID;
    }

    public function label(): string {
        return 'easybill';
    }

    public function isConfigured(?int $organizationId): bool {
        return $organizationId !== null && $this->clients->isConfigured($organizationId);
    }

    public function invoiceIdFor(BillingTransfer $transfer): ?string {
        return ExternalReference::query()
            ->where('plugin_id', EasybillPlugin::ID)
            ->where('external_type', EasybillTarget::EXT_TYPE_INVOICE)
            ->where('referenceable_type', $transfer->getMorphClass())
            ->where('referenceable_id', $transfer->getKey())
            ->latest('id')
            ->value('external_id');
    }

    public function fetch(int $organizationId, string $externalId): ExternalInvoiceSnapshot {
        $row = $this->clients->for($organizationId)->document($externalId);
        $formats = array_map(static fn ($c): string => strtolower((string) (is_array($c) ? ($c['type'] ?? '') : $c)), (array) ($row['file_format_config'] ?? []));
        $cancel = trim((string) ($row['cancel_id'] ?? ''));

        return new ExternalInvoiceSnapshot(
            externalId: $externalId,
            status: match (true) {
                ($row['is_draft'] ?? true) !== false => ExternalInvoiceStatus::Draft,
                $cancel !== '' && $cancel !== '0' => ExternalInvoiceStatus::Voided,
                VoucherMirror::date($row['paid_at'] ?? null) !== null => ExternalInvoiceStatus::Paid,
                default => ExternalInvoiceStatus::Open,
            },
            rawStatus: isset($row['status']) ? (string) $row['status'] : null,
            number: trim((string) ($row['number'] ?? '')) ?: null,
            issuedOn: ($date = VoucherMirror::date($row['document_date'] ?? null)) !== null ? CarbonImmutable::parse($date) : null,
            kind: strtoupper((string) ($row['type'] ?? '')) === 'CREDIT' ? ExternalInvoice::KIND_CREDIT_NOTE : ExternalInvoice::KIND_INVOICE,
            currency: trim((string) ($row['currency'] ?? 'EUR')) ?: 'EUR',
            netAmount: VoucherMirror::fromCents($row['amount_net'] ?? null),
            grossAmount: VoucherMirror::fromCents($row['amount'] ?? null),
            lines: $this->lines((array) ($row['items'] ?? [])),
            sent: ProviderSendState::Unknown,
            formatHint: match (true) {
                array_filter($formats, static fn (string $f): bool => str_contains($f, 'xrechnung')) !== [] => ExternalInvoiceFormat::XRechnungUbl,
                array_filter($formats, static fn (string $f): bool => str_contains($f, 'zugferd')) !== [] => ExternalInvoiceFormat::Zugferd,
                default => ExternalInvoiceFormat::Pdf,
            },
        );
    }

    public function original(int $organizationId, ExternalInvoiceSnapshot $snapshot, bool $preferXml = false): ?ExternalInvoiceFile {
        $client = $this->clients->for($organizationId);
        if ($snapshot->formatHint !== null && $snapshot->formatHint->isElectronic()) {
            $file = $client->downloadFile($snapshot->externalId, $snapshot->formatHint === ExternalInvoiceFormat::XRechnungUbl ? 'application/xml' : null);

            return $file === null ? null : new ExternalInvoiceFile($file['content'], trim(explode(';', $file['mime'])[0]), '');
        }
        $pdf = $client->downloadPdf($snapshot->externalId);

        return $pdf === null ? null : new ExternalInvoiceFile($pdf, 'application/pdf', '');
    }

    public function candidates(int $organizationId, int $customerId): array {
        return array_values(AccountingVoucher::query()->withoutGlobalScopes()
            ->where('organization_id', $organizationId)
            ->where('plugin_id', EasybillPlugin::ID)
            ->where('customer_id', $customerId)
            ->where('document_kind', 'invoice')
            ->whereNotIn('voucher_state', ['draft', 'cancelled'])
            ->orderByDesc('voucher_date')
            ->limit(50)
            ->get(['external_id', 'voucher_number', 'voucher_date'])
            ->map(static fn (AccountingVoucher $v): array => ['external_id' => (string) $v->external_id, 'number' => $v->voucher_number, 'date' => $v->voucher_date?->toDateString()])
            ->all());
    }

    /**
     * @param  array<int, mixed>  $items
     * @return list<array{name: string, quantity: string, unit_price: string|null, net: string}>
     */
    private function lines(array $items): array {
        $lines = [];
        foreach ($items as $item) {
            if (! is_array($item) || strtoupper((string) ($item['type'] ?? 'POSITION')) === 'TEXT') {
                continue;
            }
            $description = trim((string) ($item['description'] ?? ''));
            $lines[] = [
                'name' => \CommonToolkit\Helper\Data\StringHelper::lines($description, true)[0] ?? '',
                'quantity' => Decimal::ofFloat(is_numeric($item['quantity'] ?? null) ? (float) $item['quantity'] : 1.0, 3)->getValue(),
                'unit_price' => VoucherMirror::fromCents($item['single_price_net'] ?? null),
                'net' => VoucherMirror::fromCents($item['total_price_net'] ?? null) ?? '0.00',
            ];
        }

        return $lines;
    }
}
