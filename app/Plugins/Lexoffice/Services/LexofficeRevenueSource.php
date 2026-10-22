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

use App\Enums\Invoicing\InvoiceStatus;
use App\Models\Integration\ExternalReference;
use App\Models\Invoicing\Invoice;
use App\Plugins\Lexoffice\LexofficePlugin;
use App\Plugins\Lexoffice\Models\{LexofficeVoucher, LexofficeVoucherLine};
use App\Services\Billing\Contracts\ExternalRevenueSource;
use App\Services\Billing\Dto\{ExternalInvoice, ExternalInvoiceFile, ExternalInvoiceQuery, ExternalProductRevenue};
use App\Support\Query\DateRange;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\{Builder, Collection};
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;

/**
 * Umsatz aus dem Lexoffice-Beleg-Spiegel (Phase-54-Nachtrag; seit MVP-1034
 * als {@see ExternalRevenueSource} im Plugin): Bei externer
 * Rechnungshoheit existieren die Rechnungen nur im Buchhaltungsprogramm —
 * `lexoffice_vouchers` liefert sie in die Auswertungen nach.
 *
 * Eine Regel für alle Auswertungen ({@see revenueVouchers()}, MVP-1114):
 * Rechnungen und Abschläge positiv, Gutschriften negativ; Entwürfe und
 * stornierte Belege zählen nicht, archivierte schon (Lexware: nur ein
 * Anzeigekennzeichen). Übergebene lokale Rechnungen zählen nur lokal —
 * erkannt an der Übergabe-Referenz oder an der Nummer einer lokalen Rechnung
 * (`number`/`external_number`).
 */
class LexofficeRevenueSource implements ExternalRevenueSource {
    /** Gespiegelte Belege mit Positionen; nur Lexoffice-eigene Belege tragen welche. */
    private const PRODUCT_VOUCHER_TYPES = ['invoice', 'creditnote'];

    /** `paidoff` = Gutschrift vollständig zurückgezahlt (Lexware-Doku). */
    private const PAID_STATUSES = ['paid', 'paidoff'];

    public function key(): string {
        return LexofficePlugin::ID;
    }

    public function invoices(ExternalInvoiceQuery $query): array {
        $vouchers = $this->selectVouchers($this->revenueVouchers($query->withCreditNotes ? VoucherTypes::REVENUE_WITH_CREDITS : VoucherTypes::REVENUE))
            ->when($query->organizationId !== null, static fn (Builder $q) => $q->where('lexoffice_vouchers.organization_id', $query->organizationId))
            ->when($query->issuedFrom, static fn (Builder $q, CarbonInterface $from) => $q->where('lexoffice_vouchers.voucher_date', '>=', DateRange::day($from)))
            ->when($query->issuedTo, static fn (Builder $q, CarbonInterface $to) => $q->where('lexoffice_vouchers.voucher_date', '<', DateRange::dayAfter($to)))
            ->when($query->customerId !== null, static fn (Builder $q) => $q->where('lexoffice_vouchers.customer_id', $query->customerId))
            ->when($query->excludedCustomerIds !== [], static fn (Builder $q) => $q->where(static fn (Builder $w) => $w
                ->whereNull('lexoffice_vouchers.customer_id')
                ->orWhereNotIn('lexoffice_vouchers.customer_id', $query->excludedCustomerIds)))
            ->when($query->openOnly, static fn (Builder $q) => $q->whereNotIn('lexoffice_vouchers.voucher_status', self::PAID_STATUSES))
            ->get();

        return $this->toExternalInvoices($vouchers);
    }

    public function invoicesByNumber(int $organizationId, array $numbers): array {
        if ($numbers === []) {
            return [];
        }
        $vouchers = $this->selectVouchers($this->revenueVouchers(VoucherTypes::REVENUE_WITH_CREDITS))
            ->where('lexoffice_vouchers.organization_id', $organizationId)
            ->whereIn('lexoffice_vouchers.voucher_number', $numbers)
            ->get();

        return $this->toExternalInvoices($vouchers);
    }

    /**
     * @param  Builder<LexofficeVoucher>  $query
     * @return Builder<LexofficeVoucher>
     */
    private function selectVouchers(Builder $query): Builder {
        return $query
            ->select(['id', 'organization_id', 'customer_id', 'voucher_type', 'voucher_status', 'voucher_number', 'voucher_date', 'due_date', 'paid_date', 'total_amount', 'open_amount', 'net_amount', 'currency', 'recipient_name', 'contact_name'])
            ->selectSub(LexofficeVoucherLine::query()->selectRaw('SUM(total_net)')->whereColumn('lexoffice_voucher_lines.voucher_id', 'lexoffice_vouchers.id'), 'lines_net')
            ->orderBy('voucher_date')
            ->orderBy('id');
    }

    /**
     * @param  Collection<int, LexofficeVoucher>  $vouchers
     * @return list<ExternalInvoice>
     */
    private function toExternalInvoices(Collection $vouchers): array {
        $withLink = Route::has('lexoffice.vouchers.file');
        $invoices = [];
        foreach ($vouchers as $voucher) {
            $sign = VoucherTypes::sign($voucher->voucher_type) < 0 ? -1.0 : 1.0;
            $gross = round($sign * abs($voucher->total_amount?->toFloat() ?? 0.0), 2);
            $paid = in_array($voucher->voucher_status, self::PAID_STATUSES, true);
            $open = $paid ? 0.0 : round($sign * abs($voucher->open_amount?->toFloat() ?? $gross), 2);
            $linesNet = $voucher->getAttribute('lines_net');
            $net = match (true) {
                $voucher->net_amount !== null => round($sign * abs($voucher->net_amount->toFloat()), 2),
                $linesNet !== null => round($sign * abs((float) $linesNet), 2),
                default => null,
            };

            $invoices[] = new ExternalInvoice(
                source: LexofficePlugin::ID,
                key: (string) $voucher->id,
                number: (string) $voucher->voucher_number,
                customerId: $voucher->customer_id,
                contactName: $voucher->contact_name ?? $voucher->recipient_name,
                type: match ($voucher->voucher_type) {
                    'downpaymentinvoice' => Invoice::TYPE_DOWN_PAYMENT,
                    'creditnote', 'salescreditnote' => Invoice::TYPE_CREDIT_NOTE,
                    default => Invoice::TYPE_INVOICE,
                },
                status: match (true) {
                    $paid => InvoiceStatus::Paid,
                    abs($open) > 0 && abs($open) < abs($gross) => InvoiceStatus::PartiallyPaid,
                    default => InvoiceStatus::Issued,
                },
                issuedOn: $voucher->voucher_date?->toImmutable(),
                dueOn: $voucher->due_date?->toImmutable(),
                paidOn: $voucher->paid_date?->toImmutable(),
                gross: $gross,
                net: $net,
                open: $open,
                currency: $voucher->currency->value,
                url: $withLink ? route('lexoffice.vouchers.file', $voucher) : null,
            );
        }

        return $invoices;
    }

    public function customerIdsWithDocuments(int $organizationId): array {
        return array_values(LexofficeVoucher::query()
            ->where('organization_id', $organizationId)
            ->whereNotNull('customer_id')
            ->distinct()
            ->pluck('customer_id')
            ->map(static fn (mixed $id): int => (int) $id)
            ->all());
    }

    public function file(int $organizationId, string $source, string $key): ?ExternalInvoiceFile {
        if ($source !== LexofficePlugin::ID) {
            return null;
        }
        $voucher = LexofficeVoucher::query()->where('organization_id', $organizationId)->whereKey((int) $key)->first();

        return $voucher === null ? null : $this->voucherFile($voucher);
    }

    public function fileForInvoice(Invoice $invoice): ?ExternalInvoiceFile {
        $externalId = ExternalReference::query()
            ->where('organization_id', $invoice->organization_id)
            ->where('plugin_id', LexofficePlugin::ID)
            ->where('external_type', LexofficeInvoiceService::EXT_TYPE_INVOICE)
            ->where('referenceable_type', $invoice->getMorphClass())
            ->where('referenceable_id', $invoice->getKey())
            ->value('external_id');
        if ($externalId === null) {
            return null;
        }
        $voucher = LexofficeVoucher::query()->where('organization_id', $invoice->organization_id)->where('external_id', (string) $externalId)->first();

        return $voucher === null ? null : $this->voucherFile($voucher);
    }

    /** Materialisiertes Belegbild zuerst (MVP-690), sonst der Abruf aus Lexoffice. */
    private function voucherFile(LexofficeVoucher $voucher): ?ExternalInvoiceFile {
        $service = new LexofficeVoucherFileService((int) $voucher->organization_id);
        $file = $service->localFile($voucher);
        if ($file === null && $service->isConfigured()) {
            try {
                $file = $service->download($voucher);
            } catch (\Throwable $e) {
                report($e);
            }
        }
        if ($file === null) {
            return null;
        }
        $base = Str::slug((string) ($voucher->voucher_number ?: 'beleg-' . $voucher->id)) ?: ('beleg-' . $voucher->id);

        return new ExternalInvoiceFile($file['body'], LexofficeVoucherFileService::safeContentType($file['content_type']), $base . '.' . $file['extension']);
    }

    /**
     * Umsatzwirksame Spiegelbelege der Arten `$types` — die eine Regel aller
     * Auswertungen. `$withVoided` nimmt stornierte Belege hinzu (Provisionen
     * müssen den Storno sehen, MVP-1119).
     *
     * @param  list<string>  $types
     * @return Builder<LexofficeVoucher>
     */
    public function revenueVouchers(array $types, bool $withVoided = false): Builder {
        $ignored = $withVoided ? array_values(array_diff(VoucherTypes::IGNORED_STATUSES, ['voided'])) : VoucherTypes::IGNORED_STATUSES;

        return LexofficeVoucher::query()
            ->whereIn('lexoffice_vouchers.voucher_type', $types)
            ->whereNotNull('lexoffice_vouchers.voucher_date')
            ->where(static fn (Builder $q) => $q
                ->whereNull('lexoffice_vouchers.voucher_status')
                ->orWhereNotIn('lexoffice_vouchers.voucher_status', $ignored))
            ->whereNotExists(static function ($query): void {
                $query->selectRaw('1')
                    ->from('external_references')
                    ->where('external_references.plugin_id', LexofficePlugin::ID)
                    ->where('external_references.external_type', LexofficeInvoiceService::EXT_TYPE_INVOICE)
                    ->where('external_references.referenceable_type', (new Invoice)->getMorphClass())
                    ->whereColumn('external_references.external_id', 'lexoffice_vouchers.external_id');
            })
            ->whereNotExists(static function ($query): void {
                $query->selectRaw('1')
                    ->from('invoices')
                    ->whereColumn('invoices.organization_id', 'lexoffice_vouchers.organization_id')
                    ->where(static fn ($w) => $w
                        ->whereColumn('invoices.number', 'lexoffice_vouchers.voucher_number')
                        ->orWhereColumn('invoices.external_number', 'lexoffice_vouchers.voucher_number'));
            });
    }

    /**
     * Positionen gespiegelter Rechnungen und Gutschriften (MVP-760), über die
     * Artikel-Zuordnung auf den eigenen Artikelstamm gelegt; Belegauswahl wie
     * {@see invoices()}.
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
            ->whereIn('lexoffice_voucher_lines.voucher_id', $this->revenueVouchers(self::PRODUCT_VOUCHER_TYPES)
                ->whereBetween('lexoffice_vouchers.voucher_date', DateRange::days($from, $to))
                ->select('lexoffice_vouchers.id'))
            ->where(static fn ($q) => $q->whereNull('lexoffice_voucher_lines.type')->orWhere('lexoffice_voucher_lines.type', '<>', 'text'))
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
