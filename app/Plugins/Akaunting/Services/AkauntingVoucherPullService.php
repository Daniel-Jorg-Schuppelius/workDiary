<?php
/*
 * Created on   : Sat Oct 24 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : AkauntingVoucherPullService.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Plugins\Akaunting\Services;

use App\Enums\Billing\{DocumentDirection, DocumentKind};
use App\Enums\Finance\AccountingVoucherState;
use App\Models\Finance\AccountingVoucher;
use App\Plugins\Akaunting\{AkauntingConfig, AkauntingPlugin};
use App\Plugins\Akaunting\Api\AkauntingClientFactory;
use App\Services\Finance\Accounting\Vouchers\{MirroredVoucher, VoucherMirror, VoucherPuller};
use CommonToolkit\Enums\CurrencyCode;
use CommonToolkit\Helper\Data\StringHelper;
use CommonToolkit\ValueObjects\Money;

/**
 * Akaunting in den Belegspiegel (Feature 176, MVP-1332): Rechnungen als
 * Ausgangs-, Lieferantenrechnungen (`bill`) als Eingangsbelege, nur lesend.
 * Der Erstlauf liest die jüngsten `$pages` Seiten je Art, ältere holt
 * `--pages`; ein Folgelauf liest je Art alles seit der eigenen Marke in
 * Änderungsfolge. Die Marke ist Ortszeit der Instanz (so vergleicht der
 * Suchfilter) und wird nur hier gelesen; zwei Stunden Überlappung decken die
 * Rückstellung der Uhr ab. Der Spiegel bleibt über die Fremd-ID
 * (`invoice:<id>`) dublettenfrei.
 */
class AkauntingVoucherPullService implements VoucherPuller {
    /** Belegart → Richtung und Pfad in der Akaunting-Oberfläche. */
    private const TYPES = [
        'invoice' => ['direction' => DocumentDirection::Outgoing, 'path' => 'sales/invoices'],
        'bill' => ['direction' => DocumentDirection::Incoming, 'path' => 'purchases/bills'],
    ];

    public function __construct(
        private readonly AkauntingClientFactory $clients,
        private readonly VoucherMirror $mirror,
    ) {}

    public function pluginId(): string {
        return AkauntingPlugin::ID;
    }

    /** Eingeschaltet, Zugang hinterlegt und „Belege abrufen“ gesetzt — der allgemeine Abruf prüft den Plugin-Schalter nicht selbst. */
    public function isConfigured(int $organizationId): bool {
        $config = AkauntingConfig::resolve($organizationId);

        return $config['enabled'] && $config['pull_vouchers'] && $this->clients->isConfigured($organizationId);
    }

    /** @return array{read: int, created: int, updated: int, skipped: int} */
    public function pull(int $organizationId, int $pages = 2): array {
        $counters = VoucherMirror::counters();
        if (! $this->isConfigured($organizationId)) {
            return $counters;
        }
        $client = $this->clients->for($organizationId);

        foreach (self::TYPES as $type => $meta) {
            $since = $this->mirror->lastSourceChange($organizationId, $this->pluginId(), $type)?->subHours(2)->format('Y-m-d H:i:s');
            $maxPages = $since !== null ? max(1, $pages * 5) : max(1, $pages);
            for ($page = 1; $page <= $maxPages; $page++) {
                $result = $client->documents($type, $page, $since);
                foreach ($result['rows'] as $row) {
                    $counters['read']++;
                    $this->mirror->store($organizationId, $this->pluginId(), $this->map($type, $meta['direction'], $row), $counters);
                }
                if ($result['rows'] === [] || $page >= $result['last_page']) {
                    break;
                }
            }
        }

        return $counters;
    }

    /** Link zum Original in der Oberfläche (Belegfluss, {@see AkauntingVoucherLinks}). */
    public function voucherUrl(AccountingVoucher $voucher): ?string {
        [$type, $id] = array_pad(explode(':', (string) $voucher->external_id, 2), 2, '');
        $location = $this->clients->location((int) $voucher->organization_id);
        if (! isset(self::TYPES[$type]) || ! ctype_digit($id) || $location['base_url'] === null) {
            return null;
        }

        return $location['base_url'] . '/' . $location['company_id'] . '/' . self::TYPES[$type]['path'] . '/' . $id;
    }

    /** @param array<string, mixed> $row */
    private function map(string $type, DocumentDirection $direction, array $row): MirroredVoucher {
        $id = is_scalar($row['id'] ?? null) ? (string) $row['id'] : '';
        $status = StringHelper::trimToNull($row['status'] ?? null);
        $contactId = is_scalar($row['contact_id'] ?? null) ? (string) $row['contact_id'] : '';
        $currency = StringHelper::trimToNull($row['currency_code'] ?? null) ?? 'EUR';
        $currencyCode = CurrencyCode::tryFrom($currency) ?? CurrencyCode::Euro;
        $total = VoucherMirror::decimal($row['amount'] ?? null);

        return new MirroredVoucher(
            externalId: ctype_digit($id) ? $type . ':' . $id : '',
            direction: $direction,
            kind: DocumentKind::Invoice,
            rawType: $type,
            rawStatus: $status,
            state: match ($status) {
                'draft' => AccountingVoucherState::Draft,
                'paid' => AccountingVoucherState::Paid,
                'cancelled' => AccountingVoucherState::Cancelled,
                default => AccountingVoucherState::Open,
            },
            number: StringHelper::trimToNull($row['document_number'] ?? null),
            date: VoucherMirror::date($row['issued_at'] ?? null),
            dueDate: VoucherMirror::date($row['due_at'] ?? null),
            totalAmount: $total,
            netAmount: self::netAmount($total, $row, $currencyCode),
            openAmount: match ($status) {
                'paid' => '0.00',
                'draft', 'cancelled' => null,
                default => self::openAmount($total, $row, $currency, $type),
            },
            currency: $currency,
            contactExternalId: ctype_digit($contactId) ? 'contact:' . $contactId : null,
            supplierName: $direction === DocumentDirection::Incoming ? StringHelper::trimToNull($row['contact_name'] ?? null) : null,
            customerNumber: $direction === DocumentDirection::Outgoing ? StringHelper::trimToNull(data_get($row, 'contact.reference')) : null,
            sourceChangedAt: VoucherMirror::timestamp($row['updated_at'] ?? $row['issued_at'] ?? null),
            payload: $row,
        );
    }

    /**
     * Netto = Summe abzüglich der Steuerzeilen (`totals`, Code `tax`); null ohne Summenzeilen.
     *
     * @param  array<string, mixed>  $row
     */
    private static function netAmount(?string $total, array $row, CurrencyCode $currency): ?string {
        $totals = data_get($row, 'totals.data');
        if ($total === null || ! is_array($totals) || $totals === []) {
            return null;
        }
        $taxes = array_map(
            static fn (mixed $line): Money => Money::of(VoucherMirror::decimal(data_get($line, 'amount')) ?? '0', $currency),
            array_filter($totals, static fn (mixed $line): bool => data_get($line, 'code') === 'tax'),
        );

        return Money::of($total, $currency)->minus(Money::sum($taxes, $currency))->getAmount();
    }

    /**
     * Offener Betrag = Summe abzüglich der Zahlungen. Unbekannt (null), wenn
     * eine Zahlung in anderer Währung oder von unerwarteter Art ist —
     * Akaunting rechnet sie über Kurse um, die hier fehlen.
     *
     * @param  array<string, mixed>  $row
     */
    private static function openAmount(?string $total, array $row, string $currency, string $type): ?string {
        $transactions = data_get($row, 'transactions.data');
        if ($total === null || ! is_array($transactions)) {
            return null;
        }
        $currencyCode = CurrencyCode::tryFrom($currency) ?? CurrencyCode::Euro;
        $expected = $type === 'invoice' ? 'income' : 'expense';
        $paid = [];
        foreach ($transactions as $transaction) {
            if (data_get($transaction, 'currency_code') !== $currency || ! str_starts_with((string) data_get($transaction, 'type'), $expected)) {
                return null;
            }
            $paid[] = Money::of(VoucherMirror::decimal(data_get($transaction, 'amount')) ?? '0', $currencyCode);
        }

        return Money::of($total, $currencyCode)->minus(Money::sum($paid, $currencyCode))->getAmount();
    }
}
