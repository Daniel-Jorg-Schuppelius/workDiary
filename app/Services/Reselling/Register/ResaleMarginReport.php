<?php
/*
 * Created on   : Thu Sep 10 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ResaleMarginReport.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Reselling\Register;

use App\Enums\Reselling\PeriodStatus;
use App\Models\Reselling\{ResalePeriod, ResalePeriodLink};
use App\Support\Query\DateRange;
use Carbon\CarbonImmutable;
use CommonToolkit\Enums\CurrencyCode;
use CommonToolkit\ValueObjects\Money;
use Illuminate\Database\Eloquent\Builder;

/**
 * Margenbericht (Feature 152, MVP-765): fällige Perioden fremder Halter im
 * Zeitraum, verdichtet je Produkt und je Rechnungsempfänger — Soll-Verkauf,
 * berechnet laut Bezügen, Soll-Einkauf, Ist-Einkauf, Marge. Beträge werden
 * nie über Währungen hinweg summiert: je Schlüssel und Währung eine Zeile.
 *
 * @phpstan-type MarginRow array{label: string, currency: CurrencyCode, periods: int, open: int, expected_sale: float, expected_purchase: float, actual_purchase: float, with_actual: int, billed: float, margin: float}
 */
final class ResaleMarginReport {
    /**
     * @return array{by_product: list<MarginRow>, by_recipient: list<MarginRow>, currencies: list<CurrencyCode>, mixed: bool}
     */
    public function build(CarbonImmutable $from, CarbonImmutable $to): array {
        $periods = $this->periods($from, $to)
            ->with(['subscription.customer:id,name', 'subscription.foreignCustomer:id,name,customer_id', 'subscription.foreignCustomer.customer:id,name', 'subscription.article:id,number,name', 'subscription.lexofficeArticle:id,article_number,name', 'links', 'purchases'])
            ->get();

        /** @var array<string, MarginRow> $byProduct */
        $byProduct = [];
        /** @var array<string, MarginRow> $byRecipient */
        $byRecipient = [];
        /** @var array<string, CurrencyCode> $currencies */
        $currencies = [];
        foreach ($periods as $period) {
            $subscription = $period->subscription;
            $currency = $period->currency;
            $currencies[$currency->value] = $currency;
            $actual = $period->actualPurchase();
            $delta = [
                'periods' => 1,
                'open' => in_array($period->status, [PeriodStatus::Open, PeriodStatus::Partial], true) ? 1 : 0,
                'expected_sale' => $period->expected_sale?->toFloat() ?? 0.0,
                'expected_purchase' => $period->expected_purchase?->toFloat() ?? 0.0,
                'actual_purchase' => $actual ?? 0.0,
                'with_actual' => $actual === null ? 0 : 1,
                'billed' => (float) $period->links->sum(static fn(ResalePeriodLink $l): float => $l->amount?->toFloat() ?? 0.0),
            ];
            $productLabel = $subscription->productLabel() ?? $subscription->label;
            $recipient = $subscription->billedTo()->name ?? (string) __('resale.holder.unassigned');
            self::add($byProduct, $subscription->productKey() . '|' . $currency->value, $productLabel, $currency, $delta);
            self::add($byRecipient, $recipient . '|' . $currency->value, $recipient, $currency, $delta);
        }

        ksort($currencies);

        return [
            'by_product' => self::finish($byProduct),
            'by_recipient' => self::finish($byRecipient),
            'currencies' => array_values($currencies),
            'mixed' => count($currencies) > 1,
        ];
    }

    /**
     * Exportzeilen (CSV/XLSX): Kopf + beide Blöcke untereinander, Beträge als
     * Dezimalstrings mit Komma — Excel-DE liest sie als Zahl.
     *
     * @param  array{by_product: list<MarginRow>, by_recipient: list<MarginRow>}  $report
     * @return array{header: list<string>, rows: list<list<int|float|string|null>>}
     */
    public function exportRows(array $report): array {
        $header = [
            (string) __('resale.margin.block'), (string) __('resale.margin.key'), (string) __('resale.margin.currency'),
            (string) __('resale.report.periods'), (string) __('resale.report.open'), (string) __('resale.report.expected_sale'),
            (string) __('resale.report.billed'), (string) __('resale.report.expected_purchase'), (string) __('resale.report.actual_purchase'),
            (string) __('resale.report.margin'),
        ];
        $rows = [];
        foreach ([['by_product', (string) __('resale.report.by_product')], ['by_recipient', (string) __('resale.report.by_recipient')]] as [$block, $blockLabel]) {
            foreach ($report[$block] as $row) {
                $rows[] = [
                    $blockLabel,
                    $row['label'],
                    $row['currency']->value,
                    $row['periods'],
                    $row['open'],
                    self::decimal($row['expected_sale'], $row['currency']),
                    self::decimal($row['billed'], $row['currency']),
                    self::decimal($row['expected_purchase'], $row['currency']),
                    $row['with_actual'] > 0 ? self::decimal($row['actual_purchase'], $row['currency']) : '',
                    self::decimal($row['margin'], $row['currency']),
                ];
            }
        }

        return ['header' => $header, 'rows' => $rows];
    }

    /**
     * Fällige Perioden fremder Halter mit Beginn im Zeitraum (halboffen).
     *
     * @return Builder<ResalePeriod>
     */
    public function periods(CarbonImmutable $from, CarbonImmutable $to): Builder {
        return ResalePeriod::query()
            ->where('starts_on', '>=', DateRange::day($from))
            ->where('starts_on', '<', DateRange::dayAfter($to))
            ->whereHas('subscription', static fn(Builder $s) => $s->where('is_own_holding', false));
    }

    /** Frühester Periodenbeginn der Organisation — Vorgabe für „von", damit der Bericht alles zeigt. */
    public function earliestStart(CarbonImmutable $fallback): CarbonImmutable {
        $min = ResalePeriod::query()->min('starts_on');

        return is_string($min) && $min !== '' ? CarbonImmutable::parse($min) : $fallback;
    }

    /**
     * @param  array<string, MarginRow>  $target
     * @param  array{periods: int, open: int, expected_sale: float, expected_purchase: float, actual_purchase: float, with_actual: int, billed: float}  $delta
     */
    private static function add(array &$target, string $key, string $label, CurrencyCode $currency, array $delta): void {
        $row = $target[$key] ?? ['label' => $label, 'currency' => $currency, 'periods' => 0, 'open' => 0, 'expected_sale' => 0.0, 'expected_purchase' => 0.0, 'actual_purchase' => 0.0, 'with_actual' => 0, 'billed' => 0.0, 'margin' => 0.0];
        $row['periods'] += $delta['periods'];
        $row['open'] += $delta['open'];
        $row['with_actual'] += $delta['with_actual'];
        $row['expected_sale'] += $delta['expected_sale'];
        $row['expected_purchase'] += $delta['expected_purchase'];
        $row['actual_purchase'] += $delta['actual_purchase'];
        $row['billed'] += $delta['billed'];
        $target[$key] = $row;
    }

    /**
     * Marge = Berechnet − Einkauf; Ist-Einkauf, sobald jede Periode der Zeile
     * einen hat, sonst Soll-Einkauf. Sortiert nach Soll-Verkauf.
     *
     * @param  array<string, MarginRow>  $rows
     * @return list<MarginRow>
     */
    private static function finish(array $rows): array {
        foreach ($rows as &$row) {
            $purchase = $row['periods'] > 0 && $row['with_actual'] === $row['periods'] ? $row['actual_purchase'] : $row['expected_purchase'];
            $row['margin'] = round($row['billed'] - $purchase, 2);
        }
        unset($row);
        usort($rows, static fn(array $a, array $b): int => $b['expected_sale'] <=> $a['expected_sale'] ?: strcmp($a['label'], $b['label']));

        return $rows;
    }

    private static function decimal(float $value, CurrencyCode $currency): string {
        return Money::ofFloat($value, $currency, 2)->format(withSymbol: false, withThousandsSeparator: false);
    }
}
