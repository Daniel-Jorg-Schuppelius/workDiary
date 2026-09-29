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

use App\Models\Invoicing\{Invoice, InvoiceItem};
use App\Services\Billing\Contracts\ExternalRevenue;
use App\Support\Query\DateRange;
use Carbon\CarbonImmutable;

/**
 * Umsatz je Produkt (Feature 140, MVP-705): Menge, Nettoumsatz und Anteil je
 * Artikel im Zeitraum — und je Artikelkategorie (MVP-804).
 *
 * Zwei Quellen (MVP-804, Schnitt 2):
 *  - **lokal:** Positionen ausgestellter/bezahlter lokaler Rechnungen
 *    (`invoice_items.amount` = Zeilennetto nach Positionsrabatt); Gutschriften
 *    und Stornobelege tragen negative Mengen und mindern im Monat ihrer
 *    Ausstellung (MVP-990);
 *  - **Buchhaltungsprogramm:** Positionen gespiegelter Rechnungen und
 *    Gutschriften aus den {@see ExternalRevenue}-Quellen der Plugins (MVP-1035),
 *    möglichst auf den eigenen Artikelstamm gelegt. Aus einer lokalen Rechnung
 *    übergebene Belege zählen nicht ein zweites Mal; Entwürfe und stornierte
 *    Belege zählen nicht. Herkunft je Zeile = Plugin-ID der Quelle.
 *
 * Positionen ohne Artikelbezug laufen gebündelt als „ohne Artikelbezug" mit,
 * damit die Summe zu den Abrechnungsberichten passt.
 */
class ProductRevenueReportBuilder {
    /** Ausgestellt/(teil)bezahlt — Entwürfe und Stornos zählen nicht. */
    public const STATUSES = [Invoice::STATUS_ISSUED, Invoice::STATUS_PARTIALLY_PAID, Invoice::STATUS_PAID];

    /**
     * Umsatztragende Belegarten; Gutschrift und Stornobeleg spiegeln die
     * Positionen mit negativer Menge und mindern (MVP-990) — sonst zählte eine
     * stornierte Rechnung weiter voll, weil ihr Original ausgestellt bleibt.
     */
    public const TYPES = [Invoice::TYPE_INVOICE, Invoice::TYPE_PARTIAL, Invoice::TYPE_FINAL, Invoice::TYPE_CREDIT_NOTE, Invoice::TYPE_CANCELLATION];

    public const SOURCE_LOCAL = 'local';

    /**
     * @return array{
     *   rows: list<array{articleId: ?int, number: ?string, name: string, category: ?string, unit: ?string, quantity: float, net: float, share: ?float, invoices: int, sources: list<string>}>,
     *   categories: list<array{category: ?string, net: float, share: ?float, articles: int}>,
     *   total: float,
     *   withoutArticle: float,
     *   externalNet: float,
     *   articleCount: int,
     * }
     */
    public function build(CarbonImmutable $from, CarbonImmutable $to): array {
        /** @var array<string, array{articleId: ?int, number: ?string, name: string, category: ?string, unit: ?string, quantity: float, net: float, share: ?float, invoices: int, sources: list<string>}> $rows */
        $rows = [];
        $externalNet = 0.0;

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

        foreach (app(ExternalRevenue::class)->productRevenue($from, $to) as $source => $lines) {
            foreach ($lines as $line) {
                $externalNet += $line->net;
                if ($line->articleId !== null) {
                    $key = 'a:' . $line->articleId;
                } elseif ($line->externalKey !== null) {
                    // Artikel des Buchhaltungsprogramms ohne Zuordnung zum eigenen Stamm: eigene Zeile statt Sammelposten.
                    $key = 'x:' . $source . ':' . $line->externalKey;
                } else {
                    $key = 'none';
                }
                $identity = $key === 'none'
                    ? ['articleId' => null, 'number' => null, 'name' => (string) __('ohne Artikelbezug'), 'category' => null, 'unit' => null]
                    : ['articleId' => $line->articleId, 'number' => $line->number, 'name' => $line->name, 'category' => $line->category, 'unit' => $line->unit];
                $this->add($rows, $key, $identity, $line->quantity, $line->net, $line->documents, $source);
            }
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
            'externalNet' => round($externalNet, 2),
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
