<?php
/*
 * Created on   : Sat Nov 07 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : SevDeskExternalInvoiceProvider.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Plugins\SevDesk\Services;

use App\Enums\Finance\{ExternalInvoiceStatus, ProviderSendState};
use App\Models\Finance\{AccountingVoucher, BillingTransfer, ExternalInvoice};
use App\Models\Integration\ExternalReference;
use App\Plugins\SevDesk\Api\SevDeskClientFactory;
use App\Plugins\SevDesk\SevDeskPlugin;
use App\Services\Finance\Accounting\Vouchers\VoucherMirror;
use App\Services\Finance\Contracts\ExternalInvoiceProvider;
use App\Services\Finance\Dto\{ExternalInvoiceFile, ExternalInvoiceSnapshot};
use Carbon\CarbonImmutable;
use CommonToolkit\ValueObjects\Decimal;

/**
 * sevDesk als Fakturasystem mit Rechnungshoheit (Feature 196), belegt an der
 * OpenAPI (api.sevdesk.de, 07.11.2026): Stand `GET /Invoice/{id}` (Status 100
 * Entwurf, 200 offen, 750 teilbezahlt, 1000 bezahlt; `sendDate`/`sendType`
 * melden einen Versand), Positionen `getPositions`, Original `getXml`
 * (XRechnung) oder `getPdf` mit `preventSendBy=true` (bei E-Rechnungen ZUGFeRD).
 */
final class SevDeskExternalInvoiceProvider implements ExternalInvoiceProvider {
    public function __construct(private readonly SevDeskClientFactory $clients) {}

    public function key(): string {
        return SevDeskPlugin::ID;
    }

    public function label(): string {
        return 'sevDesk';
    }

    public function isConfigured(?int $organizationId): bool {
        return $organizationId !== null && $this->clients->isConfigured($organizationId);
    }

    public function invoiceIdFor(BillingTransfer $transfer): ?string {
        return ExternalReference::query()
            ->where('plugin_id', SevDeskPlugin::ID)
            ->where('external_type', SevDeskTarget::EXT_TYPE_INVOICE)
            ->where('referenceable_type', $transfer->getMorphClass())
            ->where('referenceable_id', $transfer->getKey())
            ->latest('id')
            ->value('external_id');
    }

    public function fetch(int $organizationId, string $externalId): ExternalInvoiceSnapshot {
        $client = $this->clients->for($organizationId);
        $row = $client->invoice($externalId);
        $status = trim((string) ($row['status'] ?? ''));
        $sentAt = ($row['sendDate'] ?? null) ? CarbonImmutable::parse((string) $row['sendDate']) : null;
        $date = VoucherMirror::date($row['invoiceDate'] ?? null);

        return new ExternalInvoiceSnapshot(
            externalId: $externalId,
            status: match ($status) {
                '100' => ExternalInvoiceStatus::Draft,
                '200', '750' => ExternalInvoiceStatus::Open,
                '1000' => ExternalInvoiceStatus::Paid,
                default => ExternalInvoiceStatus::Unknown,
            },
            rawStatus: $status !== '' ? $status : null,
            number: trim((string) ($row['invoiceNumber'] ?? '')) ?: null,
            issuedOn: $date !== null ? CarbonImmutable::parse($date) : null,
            kind: ExternalInvoice::KIND_INVOICE,
            currency: trim((string) ($row['currency'] ?? 'EUR')) ?: 'EUR',
            netAmount: VoucherMirror::decimal($row['sumNet'] ?? null),
            grossAmount: VoucherMirror::decimal($row['sumGross'] ?? null),
            lines: $status === '100' ? [] : $this->lines($client->invoicePositions($externalId)),
            sent: $sentAt !== null ? ProviderSendState::Sent : ProviderSendState::NotSent,
            sentAt: $sentAt,
            extra: ['send_type' => $row['sendType'] ?? null],
        );
    }

    public function original(int $organizationId, ExternalInvoiceSnapshot $snapshot, bool $preferXml = false): ?ExternalInvoiceFile {
        $client = $this->clients->for($organizationId);
        if ($preferXml) {
            $xml = $client->invoiceXml($snapshot->externalId);
            if ($xml !== null) {
                return new ExternalInvoiceFile($xml, 'application/xml', '');
            }
        }
        $pdf = $client->invoicePdf($snapshot->externalId);

        return $pdf === null ? null : new ExternalInvoiceFile($pdf['content'], $pdf['mime'], $pdf['filename']);
    }

    public function candidates(int $organizationId, int $customerId): array {
        $prefix = SevDeskVoucherPullService::INVOICE_PREFIX;

        return array_values(AccountingVoucher::query()->withoutGlobalScopes()
            ->where('organization_id', $organizationId)
            ->where('plugin_id', SevDeskPlugin::ID)
            ->where('customer_id', $customerId)
            ->whereLikeEscaped('external_id', $prefix, 'prefix')
            ->whereNotIn('voucher_state', ['draft', 'cancelled'])
            ->orderByDesc('voucher_date')
            ->limit(50)
            ->get(['external_id', 'voucher_number', 'voucher_date'])
            ->map(static fn (AccountingVoucher $v): array => ['external_id' => substr((string) $v->external_id, strlen($prefix)), 'number' => $v->voucher_number, 'date' => $v->voucher_date?->toDateString()])
            ->all());
    }

    /**
     * @param  list<array<string, mixed>>  $positions
     * @return list<array{name: string, quantity: string, unit_price: string|null, net: string}>
     */
    private function lines(array $positions): array {
        return array_map(static fn (array $p): array => [
            'name' => trim((string) ($p['name'] ?? '')),
            'quantity' => Decimal::ofFloat(is_numeric($p['quantity'] ?? null) ? (float) $p['quantity'] : 1.0, 3)->getValue(),
            'unit_price' => VoucherMirror::decimal($p['priceNet'] ?? null, 4),
            'net' => VoucherMirror::decimal($p['sumNetAccounting'] ?? ($p['sumNet'] ?? null)) ?? '0.00',
        ], $positions);
    }
}
