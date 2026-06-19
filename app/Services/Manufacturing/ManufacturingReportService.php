<?php
/*
 * Created on   : Tue Jun 16 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ManufacturingReportService.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Manufacturing;

use App\Models\{ManufacturingOrder, ManufacturingOrderReport};
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Teilrückmeldungen eines Fertigungsauftrags (Feature 047, MVP-065): erfasst
 * produzierte, Gut-, Ausschuss- und Nacharbeitsmenge je Rückmeldung und lagert
 * die Gutmenge optional sofort als Fertigerzeugnis ein.
 */
class ManufacturingReportService {
    public const SCALE = 4;

    public function __construct(private readonly ManufacturingInventoryService $inventory) {}

    public function report(
        ManufacturingOrder $order,
        string $producedQty,
        string $goodQty,
        string $scrapQty = '0',
        string $reworkQty = '0',
        ?int $reportedBy = null,
        ?string $note = null,
        bool $receiveGood = true,
    ): ManufacturingOrderReport {
        $good = $this->positive($goodQty);

        return DB::transaction(function () use ($order, $producedQty, $good, $scrapQty, $reworkQty, $reportedBy, $note, $receiveGood): ManufacturingOrderReport {
            /** @var ManufacturingOrderReport $report */
            $report = $order->reports()->create([
                'produced_qty' => $this->positive($producedQty),
                'good_qty' => $good,
                'scrap_qty' => $this->positive($scrapQty),
                'rework_qty' => $this->positive($reworkQty),
                'note' => $note,
                'reported_by' => $reportedBy,
                'reported_at' => Carbon::now(),
            ]);

            if ($receiveGood && bccomp($good, '0', self::SCALE) > 0 && $order->warehouse_id !== null) {
                $this->inventory->receiveFinishedGood($order, $good);
            }

            return $report;
        });
    }

    /** @return numeric-string */
    private function positive(string $value): string {
        $value = str_replace(',', '.', trim($value));
        if ($value === '' || ! is_numeric($value)) {
            return '0';
        }

        return bccomp($value, '0', self::SCALE) < 0 ? bcmul($value, '-1', self::SCALE) : $value;
    }
}
