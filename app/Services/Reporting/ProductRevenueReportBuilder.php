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

use App\Models\{Invoice, InvoiceItem};
use App\Support\Query\DateRange;
use Carbon\CarbonImmutable;

/**
 * Umsatz je Produkt (Feature 140, MVP-705): Menge, Nettoumsatz und Anteil je
 * Artikel aus den Positionen lokal ausgestellter Rechnungen im Zeitraum.
 *
 * Datenbasis bewusst NUR lokale Rechnungen (invoice_items.amount = Zeilennetto
 * nach Positionsrabatt): gespiegelte Lexoffice-/Buchhaltungsbelege tragen
 * keine Positionen (Vollscan G1, Schnitt 2 nicht gebaut). Positionen ohne
 * Artikelbezug laufen gebündelt als „ohne Artikelbezug" mit, damit die Summe
 * zum Abrechnungsbericht passt.
 */
class ProductRevenueReportBuilder {
    /** Ausgestellt/(teil)bezahlt — Entwürfe und Stornos zählen nicht. */
    public const STATUSES = [Invoice::STATUS_ISSUED, Invoice::STATUS_PARTIALLY_PAID, Invoice::STATUS_PAID];

    /** Umsatztragende Belegarten (wie CustomerValueReportBuilder::invoicedPerCustomer). */
    public const TYPES = [Invoice::TYPE_INVOICE, Invoice::TYPE_PARTIAL, Invoice::TYPE_FINAL];

    /**
     * @return array{
     *   rows: list<array{articleId: ?int, number: ?string, name: string, unit: ?string, quantity: float, net: float, share: ?float, invoices: int}>,
     *   total: float,
     *   withoutArticle: float,
     *   articleCount: int,
     * }
     */
    public function build(CarbonImmutable $from, CarbonImmutable $to): array {
        $aggregates = InvoiceItem::query()
            ->join('invoices', 'invoices.id', '=', 'invoice_items.invoice_id')
            ->leftJoin('articles', 'articles.id', '=', 'invoice_items.article_id')
            ->whereBetween('invoices.issued_on', DateRange::days($from, $to))
            ->whereIn('invoices.status', self::STATUSES)
            ->whereIn('invoices.type', self::TYPES)
            ->groupBy('invoice_items.article_id', 'articles.number', 'articles.name', 'articles.base_unit')
            ->selectRaw(
                'invoice_items.article_id AS article_id, articles.number AS article_number, articles.name AS article_name, articles.base_unit AS article_unit,'
                . ' SUM(invoice_items.quantity) AS qty, SUM(invoice_items.amount) AS net, COUNT(DISTINCT invoice_items.invoice_id) AS invoice_count'
            )
            ->get();

        $rows = [];
        $total = 0.0;
        $withoutArticle = 0.0;
        foreach ($aggregates as $row) {
            $net = round((float) $row->getAttribute('net'), 2);
            $articleId = $row->getAttribute('article_id') !== null ? (int) $row->getAttribute('article_id') : null;
            $total += $net;
            if ($articleId === null) {
                $withoutArticle += $net;
            }
            $rows[] = [
                'articleId' => $articleId,
                'number' => $articleId !== null ? (string) $row->getAttribute('article_number') ?: null : null,
                'name' => $articleId !== null ? (string) $row->getAttribute('article_name') : (string) __('ohne Artikelbezug'),
                'unit' => $articleId !== null ? (string) $row->getAttribute('article_unit') ?: null : null,
                'quantity' => round((float) $row->getAttribute('qty'), 3),
                'net' => $net,
                'share' => null,
                'invoices' => (int) $row->getAttribute('invoice_count'),
            ];
        }

        $total = round($total, 2);
        foreach ($rows as &$r) {
            $r['share'] = $total > 0 ? round($r['net'] / $total * 100, 1) : null;
        }
        unset($r);

        // Umsatzstärkste zuerst; der Sammelposten ohne Artikel steht immer am Ende.
        usort($rows, static function (array $a, array $b): int {
            if (($a['articleId'] === null) !== ($b['articleId'] === null)) {
                return $a['articleId'] === null ? 1 : -1;
            }

            return $b['net'] <=> $a['net'] ?: strcmp($a['name'], $b['name']);
        });

        return [
            'rows' => $rows,
            'total' => $total,
            'withoutArticle' => round($withoutArticle, 2),
            'articleCount' => count(array_filter($rows, static fn(array $r): bool => $r['articleId'] !== null)),
        ];
    }
}
