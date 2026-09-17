<?php
/*
 * Created on   : Tue Aug 25 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ProductRevenueReportBuilder.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Reporting;

use App\Models\{Invoice, InvoiceItem, LexofficeVoucherLine};
use App\Plugins\Lexoffice\{LexofficeInvoiceService, LexofficePlugin};
use App\Support\Query\DateRange;
use Carbon\CarbonImmutable;

/**
 * Umsatz je Produkt (Feature 140, MVP-705): Menge, Nettoumsatz und Anteil je
 * Artikel im Zeitraum — und je Artikelkategorie (MVP-804).
 *
 * Zwei Quellen (MVP-804, Schnitt 2):
 *  - **lokal:** Positionen ausgestellter/bezahlter lokaler Rechnungen
 *    (`invoice_items.amount` = Zeilennetto nach Positionsrabatt);
 *  - **Lexoffice:** Positionen gespiegelter Rechnungen und Gutschriften
 *    (`lexoffice_voucher_lines`, MVP-760), über die Artikel-Zuordnung
 *    (`external_article_mappings`) auf den eigenen Artikelstamm gelegt.
 *    Belege, die aus einer lokalen Rechnung an Lexoffice übergeben wurden,
 *    zählen nicht ein zweites Mal. Entwürfe und stornierte Belege zählen nicht.
 *
 * Positionen ohne Artikelbezug laufen gebündelt als „ohne Artikelbezug" mit,
 * damit die Summe zu den Abrechnungsberichten passt.
 */
class ProductRevenueReportBuilder {
    /** Ausgestellt/(teil)bezahlt — Entwürfe und Stornos zählen nicht. */
    public const STATUSES = [Invoice::STATUS_ISSUED, Invoice::STATUS_PARTIALLY_PAID, Invoice::STATUS_PAID];

    /** Umsatztragende Belegarten (wie CustomerValueReportBuilder::invoicedPerCustomer). */
    public const TYPES = [Invoice::TYPE_INVOICE, Invoice::TYPE_PARTIAL, Invoice::TYPE_FINAL];

    /** Gespiegelte Lexoffice-Belege mit Umsatzwirkung; Gutschriften mindern. */
    public const VOUCHER_TYPES = ['invoice', 'creditnote'];

    /** Belegstatus ohne Umsatzwirkung. */
    public const VOUCHER_STATUSES_EXCLUDED = ['draft', 'voided'];

    public const SOURCE_LOCAL = 'local';

    public const SOURCE_LEXOFFICE = 'lexoffice';

    /**
     * @return array{
     *   rows: list<array{articleId: ?int, number: ?string, name: string, category: ?string, unit: ?string, quantity: float, net: float, share: ?float, invoices: int, sources: list<string>}>,
     *   categories: list<array{category: ?string, net: float, share: ?float, articles: int}>,
     *   total: float,
     *   withoutArticle: float,
     *   lexofficeNet: float,
     *   articleCount: int,
     * }
     */
    public function build(CarbonImmutable $from, CarbonImmutable $to): array {
        /** @var array<string, array{articleId: ?int, number: ?string, name: string, category: ?string, unit: ?string, quantity: float, net: float, share: ?float, invoices: int, sources: list<string>}> $rows */
        $rows = [];
        $lexofficeNet = 0.0;

        foreach ($this->localAggregates($from, $to) as $row) {
            $articleId = $row->getAttribute('article_id') !== null ? (int) $row->getAttribute('article_id') : null;
            $this->add($rows, $articleId !== null ? 'a:' . $articleId : 'none', [
                'articleId' => $articleId,
                'number' => $articleId !== null ? ((string) $row->getAttribute('article_number') ?: null) : null,
                'name' => $articleId !== null ? (string) $row->getAttribute('article_name') : (string) __('ohne Artikelbezug'),
                'category' => $articleId !== null ? ((string) $row->getAttribute('article_category') ?: null) : null,
                'unit' => $articleId !== null ? ((string) $row->getAttribute('article_unit') ?: null) : null,
            ], (float) $row->getAttribute('qty'), (float) $row->getAttribute('net'), (int) $row->getAttribute('document_count'), self::SOURCE_LOCAL);
        }

        foreach ($this->lexofficeAggregates($from, $to) as $row) {
            $articleId = $row->getAttribute('article_id') !== null ? (int) $row->getAttribute('article_id') : null;
            $externalId = (string) $row->getAttribute('external_article_id');
            $net = (float) $row->getAttribute('net');
            $lexofficeNet += $net;

            if ($articleId !== null) {
                $key = 'a:' . $articleId;
                $identity = [
                    'articleId' => $articleId,
                    'number' => (string) $row->getAttribute('article_number') ?: null,
                    'name' => (string) $row->getAttribute('article_name'),
                    'category' => (string) $row->getAttribute('article_category') ?: null,
                    'unit' => (string) $row->getAttribute('article_unit') ?: null,
                ];
            } elseif ($externalId !== '') {
                // Lexoffice-Artikel ohne Zuordnung zum eigenen Stamm: eigene Zeile statt Sammelposten.
                $key = 'x:' . $externalId;
                $identity = [
                    'articleId' => null,
                    'number' => (string) $row->getAttribute('lexoffice_number') ?: null,
                    'name' => (string) ($row->getAttribute('lexoffice_name') ?: $row->getAttribute('line_name')),
                    'category' => null,
                    'unit' => (string) $row->getAttribute('lexoffice_unit') ?: null,
                ];
            } else {
                $key = 'none';
                $identity = ['articleId' => null, 'number' => null, 'name' => (string) __('ohne Artikelbezug'), 'category' => null, 'unit' => null];
            }

            $this->add($rows, $key, $identity, (float) $row->getAttribute('qty'), $net, (int) $row->getAttribute('document_count'), self::SOURCE_LEXOFFICE);
        }

        $total = round(array_sum(array_column($rows, 'net')), 2);
        $withoutArticle = round((float) ($rows['none']['net'] ?? 0.0), 2);

        foreach ($rows as &$r) {
            $r['net'] = round($r['net'], 2);
            $r['quantity'] = round($r['quantity'], 3);
            $r['share'] = $total > 0 ? round($r['net'] / $total * 100, 1) : null;
        }
        unset($r);

        // Umsatzstärkste zuerst; der Sammelposten ohne Artikel steht immer am Ende.
        uksort($rows, static function (string $keyA, string $keyB) use ($rows): int {
            if (($keyA === 'none') !== ($keyB === 'none')) {
                return $keyA === 'none' ? 1 : -1;
            }

            return $rows[$keyB]['net'] <=> $rows[$keyA]['net'] ?: strcmp($rows[$keyA]['name'], $rows[$keyB]['name']);
        });
        $list = array_values($rows);

        return [
            'rows' => $list,
            'categories' => $this->categories($list, $total),
            'total' => $total,
            'withoutArticle' => $withoutArticle,
            'lexofficeNet' => round($lexofficeNet, 2),
            'articleCount' => count(array_filter($list, static fn(array $r): bool => $r['articleId'] !== null)),
        ];
    }

    /** @return \Illuminate\Support\Collection<int, InvoiceItem> */
    private function localAggregates(CarbonImmutable $from, CarbonImmutable $to) {
        return InvoiceItem::query()
            ->join('invoices', 'invoices.id', '=', 'invoice_items.invoice_id')
            ->leftJoin('articles', 'articles.id', '=', 'invoice_items.article_id')
            ->whereBetween('invoices.issued_on', DateRange::days($from, $to))
            ->whereIn('invoices.status', self::STATUSES)
            ->whereIn('invoices.type', self::TYPES)
            ->groupBy('invoice_items.article_id', 'articles.number', 'articles.name', 'articles.base_unit', 'articles.category')
            ->selectRaw(
                'invoice_items.article_id AS article_id, articles.number AS article_number, articles.name AS article_name,'
                . ' articles.base_unit AS article_unit, articles.category AS article_category,'
                . ' SUM(invoice_items.quantity) AS qty, SUM(invoice_items.amount) AS net, COUNT(DISTINCT invoice_items.invoice_id) AS document_count'
            )
            ->get();
    }

    /** @return \Illuminate\Support\Collection<int, LexofficeVoucherLine> */
    private function lexofficeAggregates(CarbonImmutable $from, CarbonImmutable $to) {
        $sign = "CASE WHEN lexoffice_vouchers.voucher_type = 'creditnote' THEN -1 ELSE 1 END";

        return LexofficeVoucherLine::query()
            ->join('lexoffice_vouchers', 'lexoffice_vouchers.id', '=', 'lexoffice_voucher_lines.voucher_id')
            ->leftJoin('external_article_mappings', function ($join): void {
                $join->on('external_article_mappings.external_id', '=', 'lexoffice_voucher_lines.external_article_id')
                    ->on('external_article_mappings.organization_id', '=', 'lexoffice_voucher_lines.organization_id')
                    ->where('external_article_mappings.plugin_id', '=', LexofficePlugin::ID);
            })
            ->leftJoin('articles', 'articles.id', '=', 'external_article_mappings.article_id')
            ->leftJoin('lexoffice_articles', 'lexoffice_articles.id', '=', 'lexoffice_voucher_lines.lexoffice_article_id')
            ->whereIn('lexoffice_vouchers.voucher_type', self::VOUCHER_TYPES)
            ->whereBetween('lexoffice_vouchers.voucher_date', DateRange::days($from, $to))
            ->where(static fn($q) => $q->whereNull('lexoffice_vouchers.voucher_status')->orWhereNotIn('lexoffice_vouchers.voucher_status', self::VOUCHER_STATUSES_EXCLUDED))
            ->where(static fn($q) => $q->whereNull('lexoffice_voucher_lines.type')->orWhere('lexoffice_voucher_lines.type', '<>', 'text'))
            // Aus einer lokalen Rechnung übergeben: zählt bereits lokal.
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
    }

    /**
     * @param  array<string, array{articleId: ?int, number: ?string, name: string, category: ?string, unit: ?string, quantity: float, net: float, share: ?float, invoices: int, sources: list<string>}>  $rows
     * @param  array{articleId: ?int, number: ?string, name: string, category: ?string, unit: ?string}  $identity
     */
    private function add(array &$rows, string $key, array $identity, float $quantity, float $net, int $documents, string $source): void {
        if (! isset($rows[$key])) {
            $rows[$key] = $identity + ['quantity' => 0.0, 'net' => 0.0, 'share' => null, 'invoices' => 0, 'sources' => []];
        }
        $rows[$key]['quantity'] += $quantity;
        $rows[$key]['net'] += $net;
        $rows[$key]['invoices'] += $documents;
        if (! in_array($source, $rows[$key]['sources'], true)) {
            $rows[$key]['sources'][] = $source;
        }
    }

    /**
     * Umsatz je Artikelkategorie (MVP-804, Feature 107). Positionen ohne
     * zugeordneten Artikel oder ohne Kategorie laufen unter `null`.
     *
     * @param  list<array{articleId: ?int, category: ?string, net: float}>  $rows
     * @return list<array{category: ?string, net: float, share: ?float, articles: int}>
     */
    private function categories(array $rows, float $total): array {
        $groups = [];
        foreach ($rows as $row) {
            $key = $row['articleId'] !== null ? ($row['category'] ?? '') : '';
            $groups[$key] ??= ['category' => $key !== '' ? $key : null, 'net' => 0.0, 'share' => null, 'articles' => 0];
            $groups[$key]['net'] += $row['net'];
            if ($row['articleId'] !== null) {
                $groups[$key]['articles']++;
            }
        }

        $list = array_values($groups);
        foreach ($list as &$group) {
            $group['net'] = round($group['net'], 2);
            $group['share'] = $total > 0 ? round($group['net'] / $total * 100, 1) : null;
        }
        unset($group);

        usort($list, static fn(array $a, array $b): int => ($a['category'] === null) <=> ($b['category'] === null) ?: $b['net'] <=> $a['net']);

        return $list;
    }
}
