<?php
/*
 * Created on   : Fri Jun 19 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ManufacturingQualityService.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Manufacturing;

use App\Models\{ManufacturingOrder, ManufacturingOrderReport};
use Illuminate\Support\Collection;

/**
 * Qualitätskennzahlen der Fertigung (Feature 047/048, E7). Aggregiert die
 * Teilrückmeldungen ({@see ManufacturingOrderReport}: Gut/Ausschuss/Nacharbeit)
 * zu Ausbeute (Yield), Ausschuss- und Nacharbeitsquote – je Auftrag oder über
 * alle Aufträge eines Artikels (Basis für SPC-Auswertungen).
 *
 * @phpstan-type QualityMetrics array{produced: numeric-string, good: numeric-string, scrap: numeric-string, rework: numeric-string, yield: numeric-string, scrap_rate: numeric-string, rework_rate: numeric-string}
 */
class ManufacturingQualityService {
    public const SCALE = 4;

    /** @return QualityMetrics */
    public function metricsFor(ManufacturingOrder $order): array {
        return $this->aggregate($order->reports()->get());
    }

    /** @return QualityMetrics */
    public function metricsForArticle(int $articleId): array {
        $orderIds = ManufacturingOrder::query()->where('article_id', $articleId)->pluck('id')->all();

        return $this->aggregate(
            ManufacturingOrderReport::query()->whereIn('manufacturing_order_id', $orderIds)->get()
        );
    }

    /**
     * @param  Collection<int, ManufacturingOrderReport>  $reports
     * @return QualityMetrics
     */
    private function aggregate(Collection $reports): array {
        $produced = '0';
        $good = '0';
        $scrap = '0';
        $rework = '0';

        foreach ($reports as $report) {
            $produced = bcadd($produced, $this->num($report->produced_qty), self::SCALE);
            $good = bcadd($good, $this->num($report->good_qty), self::SCALE);
            $scrap = bcadd($scrap, $this->num($report->scrap_qty), self::SCALE);
            $rework = bcadd($rework, $this->num($report->rework_qty), self::SCALE);
        }

        return [
            'produced' => $produced,
            'good' => $good,
            'scrap' => $scrap,
            'rework' => $rework,
            'yield' => $this->rate($good, $produced),
            'scrap_rate' => $this->rate($scrap, $produced),
            'rework_rate' => $this->rate($rework, $produced),
        ];
    }

    /**
     * @param  numeric-string  $part
     * @param  numeric-string  $total
     * @return numeric-string
     */
    private function rate(string $part, string $total): string {
        return bccomp($total, '0', self::SCALE) > 0 ? bcdiv($part, $total, self::SCALE) : '0.0000';
    }

    /** @return numeric-string */
    private function num(mixed $value): string {
        $value = (string) $value;

        return is_numeric($value) ? $value : '0';
    }
}
