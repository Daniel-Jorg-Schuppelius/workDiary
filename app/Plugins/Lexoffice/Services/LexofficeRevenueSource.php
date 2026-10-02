<?php
/*
 * Created on   : Sun Aug 02 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : LexofficeRevenueSource.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Plugins\Lexoffice\Services;

use App\Models\Invoicing\Invoice;
use App\Plugins\Lexoffice\{LexofficeInvoiceService, LexofficePlugin};
use App\Plugins\Lexoffice\Models\{LexofficeVoucher, LexofficeVoucherLine};
use App\Plugins\Lexoffice\VoucherTypes;
use App\Services\Billing\Contracts\ExternalRevenueSource;
use App\Services\Billing\Dto\ExternalProductRevenue;
use App\Support\Query\{DateParts, DateRange};
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;

/**
 * Umsatz aus dem Lexoffice-Beleg-Spiegel (Phase-54-Nachtrag; seit MVP-1034
 * als {@see ExternalRevenueSource} im Plugin): Bei externer
 * Rechnungshoheit existieren die Rechnungen nur im Buchhaltungsprogramm —
 * `lexoffice_vouchers` liefert sie je Kunde in die Auswertungen nach
 * (Billing-Umsatz, Kundenwert-Fakturiert).
 *
 * Regeln: Rechnungen/Abschläge positiv, Gutschriften negativ; Entwürfe und
 * stornierte Belege zählen nicht. Belege, deren Nummer eine lokale Rechnung
 * trägt (`invoices.number`/`external_number` — von der App übergebene
 * Rechnungen übernehmen die Lexoffice-Nummer), werden übersprungen: keine
 * Doppelzählung mit der lokalen Fakturierung.
 */
class LexofficeRevenueSource implements ExternalRevenueSource {
    /** Umsatzbelege für den Kundentrend (Stand Kundenakte, MVP-1034 unverändert übernommen). */
    private const TREND_TYPES = ['invoice', 'salesinvoice', 'purchaseinvoice'];

    private const TREND_VOID_STATUSES = ['voided', 'cancelled'];

    /** Gespiegelte Belege mit Umsatzwirkung im Produktumsatz; Gutschriften mindern. */
    private const PRODUCT_VOUCHER_TYPES = ['invoice', 'creditnote'];

    private const PRODUCT_EXCLUDED_STATUSES = ['draft', 'voided'];

    public function key(): string {
        return LexofficePlugin::ID;
    }

    /**
     * Rechnungsähnliche Spiegelbelege für den Zahlungsverhaltens-Report
     * (Phase-54-Nachtrag): gleiche Zeilenform wie lokale Rechnungen.
     * `paid_date` stammt aus der Payments-Anreicherung des Belegsyncs —
     * bezahlte Belege OHNE nachgeladenes Datum fallen aus den
     * Zahldauer-Statistiken heraus (ehrlich, statt geraten) und gelten in
     * der DSO-Historie als geschlossen. Gutschriften bleiben hier außen
     * vor (keine Fälligkeits-/Zahlungssemantik).
     *
     * @param  list<int>  $excludedCustomerIds
     * @return list<array{id:?int, customerId:int, number:string, issuedOn:?string, dueOn:?string, paidOn:?string, total:float, paid:bool}>
     */
    public function invoiceRows(string $upTo, ?int $customerId = null, array $excludedCustomerIds = []): array {
        /** @var Collection<int, LexofficeVoucher> $vouchers */
        $vouchers = LexofficeVoucher::query()
            ->whereNotNull('customer_id')
            ->whereNotNull('voucher_date')
            ->where('voucher_date', '<=', $upTo)
            ->whereIn('voucher_type', VoucherTypes::REVENUE)
            ->whereNotIn('voucher_status', ['draft', 'voided'])
            ->when($customerId !== null, fn($q) => $q->where('customer_id', $customerId))
            ->when($excludedCustomerIds !== [], fn($q) => $q->whereNotIn('customer_id', $excludedCustomerIds))
            ->get(['customer_id', 'voucher_status', 'voucher_number', 'voucher_date', 'due_date', 'paid_date', 'total_amount']);

        if ($vouchers->isEmpty()) {
            return [];
        }

        $knownNumbers = $this->knownLocalNumbers();

        $rows = [];
        foreach ($vouchers as $voucher) {
            $number = (string) $voucher->voucher_number;
            if ($number !== '' && isset($knownNumbers[$number])) {
                continue;
            }
            $rows[] = [
                'id' => null,
                'customerId' => (int) $voucher->customer_id,
                'number' => $number,
                'issuedOn' => $voucher->voucher_date?->toDateString(),
                'dueOn' => $voucher->due_date?->toDateString(),
                'paidOn' => $voucher->paid_date?->toDateString(),
                'total' => $voucher->total_amount?->toFloat() ?? 0.0,
                'paid' => $voucher->voucher_status === 'paid',
            ];
        }

        return $rows;
    }

    /**
     * @param  list<int>  $excludedCustomerIds
     * @return array<int, array{count:int, total:float}> customerId → Aggregat
     */
    public function perCustomer(string $from, string $to, ?int $customerId = null, array $excludedCustomerIds = []): array {
        /** @var Collection<int, LexofficeVoucher> $vouchers */
        $vouchers = LexofficeVoucher::query()
            ->whereNotNull('customer_id')
            ->whereBetween('voucher_date', [$from, $to])
            ->whereIn('voucher_type', VoucherTypes::REVENUE_WITH_CREDITS)
            ->whereNotIn('voucher_status', ['draft', 'voided'])
            ->when($customerId !== null, fn($q) => $q->where('customer_id', $customerId))
            ->when($excludedCustomerIds !== [], fn($q) => $q->whereNotIn('customer_id', $excludedCustomerIds))
            ->get(['customer_id', 'voucher_type', 'voucher_number', 'total_amount']);

        if ($vouchers->isEmpty()) {
            return [];
        }

        $knownNumbers = $this->knownLocalNumbers();

        /** @var array<int, array{count:int, total:float}> $agg */
        $agg = [];
        foreach ($vouchers as $voucher) {
            $number = (string) $voucher->voucher_number;
            if ($number !== '' && isset($knownNumbers[$number])) {
                continue;
            }
            $cid = (int) $voucher->customer_id;
            $sign = (float) VoucherTypes::sign($voucher->voucher_type);
            $agg[$cid] ??= ['count' => 0, 'total' => 0.0];
            $agg[$cid]['count']++;
            $agg[$cid]['total'] += $sign * ($voucher->total_amount?->toFloat() ?? 0.0);
        }

        return $agg;
    }

    /**
     * Dedup-Anker: alle lokalen Rechnungsnummern der Org (number +
     * external_number) — von der App an Lexoffice übergebene Rechnungen
     * übernehmen die Lexoffice-Belegnummer und dürfen nicht doppelt zählen.
     *
     * @return array<string, true>
     */
    private function knownLocalNumbers(): array {
        $knownNumbers = [];
        Invoice::query()
            ->get(['number', 'external_number'])
            ->each(function (Invoice $inv) use (&$knownNumbers): void {
                foreach ([(string) $inv->number, (string) $inv->external_number] as $number) {
                    if ($number !== '') {
                        $knownNumbers[$number] = true;
                    }
                }
            });

        return $knownNumbers;
    }

    public function monthlyRevenue(int $customerId, CarbonInterface $from, CarbonInterface $to): array {
        [$year, $month] = DateParts::yearMonth('voucher_date');
        /** @var iterable<int, object{y: int|string, m: int|string, amount: float|int|string}> $rows */
        $rows = LexofficeVoucher::query()
            ->where('customer_id', $customerId)
            ->where('archived', false)
            ->whereIn('voucher_type', self::TREND_TYPES)
            ->whereNotIn('voucher_status', self::TREND_VOID_STATUSES)
            ->whereBetween('voucher_date', [$from, $to])
            ->toBase()
            ->selectRaw("{$year} as y, {$month} as m, COALESCE(SUM(total_amount), 0) as amount")
            ->groupBy('y', 'm')
            ->get();

        $months = [];
        foreach ($rows as $row) {
            $months[sprintf('%04d-%02d', (int) $row->y, (int) $row->m)] = (float) $row->amount;
        }

        return $months;
    }

    public function overdueCount(int $organizationId, string $today): int {
        return LexofficeVoucher::query()
            ->where('organization_id', $organizationId)
            ->where('archived', false)
            ->whereIn('voucher_type', VoucherTypes::REVENUE)
            ->whereNotIn('voucher_status', ['draft', 'voided', 'paid', 'paidoff'])
            ->whereNotNull('due_date')
            ->where('due_date', '<', $today)
            ->count();
    }

    /**
     * Positionen gespiegelter Rechnungen und Gutschriften (MVP-760), über die
     * Artikel-Zuordnung auf den eigenen Artikelstamm gelegt. Aus einer lokalen
     * Rechnung übergebene Belege zählen bereits lokal.
     */
    public function productRevenue(CarbonInterface $from, CarbonInterface $to): array {
        $sign = "CASE WHEN lexoffice_vouchers.voucher_type = 'creditnote' THEN -1 ELSE 1 END";
        $rows = LexofficeVoucherLine::query()
            ->join('lexoffice_vouchers', 'lexoffice_vouchers.id', '=', 'lexoffice_voucher_lines.voucher_id')
            ->leftJoin('external_article_mappings', function ($join): void {
                $join->on('external_article_mappings.external_id', '=', 'lexoffice_voucher_lines.external_article_id')
                    ->on('external_article_mappings.organization_id', '=', 'lexoffice_voucher_lines.organization_id')
                    ->where('external_article_mappings.plugin_id', '=', LexofficePlugin::ID);
            })
            ->leftJoin('articles', 'articles.id', '=', 'external_article_mappings.article_id')
            ->leftJoin('lexoffice_articles', 'lexoffice_articles.id', '=', 'lexoffice_voucher_lines.lexoffice_article_id')
            ->whereIn('lexoffice_vouchers.voucher_type', self::PRODUCT_VOUCHER_TYPES)
            ->whereBetween('lexoffice_vouchers.voucher_date', DateRange::days($from, $to))
            ->where(static fn ($q) => $q->whereNull('lexoffice_vouchers.voucher_status')->orWhereNotIn('lexoffice_vouchers.voucher_status', self::PRODUCT_EXCLUDED_STATUSES))
            ->where(static fn ($q) => $q->whereNull('lexoffice_voucher_lines.type')->orWhere('lexoffice_voucher_lines.type', '<>', 'text'))
            ->whereNotExists(static function ($query): void {
                $query->selectRaw('1')
                    ->from('external_references')
                    ->where('external_references.plugin_id', LexofficePlugin::ID)
                    ->where('external_references.external_type', LexofficeInvoiceService::EXT_TYPE_INVOICE)
                    ->where('external_references.referenceable_type', (new Invoice)->getMorphClass())
                    ->whereColumn('external_references.external_id', 'lexoffice_vouchers.external_id');
            })
            ->groupBy(
                'lexoffice_voucher_lines.external_article_id', 'external_article_mappings.article_id',
                'articles.number', 'articles.name', 'articles.base_unit', 'articles.category',
                'lexoffice_articles.name', 'lexoffice_articles.article_number', 'lexoffice_articles.unit_name',
            )
            ->selectRaw(
                'lexoffice_voucher_lines.external_article_id AS external_article_id, external_article_mappings.article_id AS article_id,'
                . ' articles.number AS article_number, articles.name AS article_name, articles.base_unit AS article_unit, articles.category AS article_category,'
                . ' lexoffice_articles.name AS lexoffice_name, lexoffice_articles.article_number AS lexoffice_number, lexoffice_articles.unit_name AS lexoffice_unit,'
                . ' MIN(lexoffice_voucher_lines.name) AS line_name,'
                . " SUM(lexoffice_voucher_lines.quantity * {$sign}) AS qty, SUM(lexoffice_voucher_lines.total_net) AS net,"
                . ' COUNT(DISTINCT lexoffice_voucher_lines.voucher_id) AS document_count'
            )
            ->get();

        $result = [];
        foreach ($rows as $row) {
            $articleId = $row->getAttribute('article_id') !== null ? (int) $row->getAttribute('article_id') : null;
            $externalId = (string) $row->getAttribute('external_article_id');
            $mapped = $articleId !== null;
            $result[] = new ExternalProductRevenue(
                articleId: $articleId,
                externalKey: ! $mapped && $externalId !== '' ? $externalId : null,
                number: ((string) $row->getAttribute($mapped ? 'article_number' : 'lexoffice_number')) ?: null,
                name: $mapped
                    ? (string) $row->getAttribute('article_name')
                    : (string) ($row->getAttribute('lexoffice_name') ?: $row->getAttribute('line_name')),
                category: $mapped ? (((string) $row->getAttribute('article_category')) ?: null) : null,
                unit: ((string) $row->getAttribute($mapped ? 'article_unit' : 'lexoffice_unit')) ?: null,
                quantity: (float) $row->getAttribute('qty'),
                net: (float) $row->getAttribute('net'),
                documents: (int) $row->getAttribute('document_count'),
            );
        }

        return $result;
    }
}
