<?php
/*
 * Created on   : Sun Sep 27 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : PriceIndexService.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Contract;

use App\Enums\Contract\PriceIndexStatus;
use App\Models\Contract\PriceIndexValue;
use App\Models\Platform\User;
use App\Services\Concerns\AssertsStatusTransition;
use App\Support\BundesbankSeries;
use App\Support\Query\DateRange;
use Carbon\CarbonInterface;
use CommonToolkit\Helper\Data\NumberHelper;

/**
 * Verbraucherpreisindex für Deutschland (MVP-952): Ursprungswerte des
 * Statistischen Bundesamts, Basis 2020 = 100, aus der Zeitreihe der
 * Bundesbank. Den letzten Monat schätzt die Bundesbank zeitweise selbst —
 * jeder neue oder geänderte Wert wartet deshalb auf eine Freigabe.
 */
class PriceIndexService {
    use AssertsStatusTransition;

    public const SERIES_URL = 'https://api.statistiken.bundesbank.de/rest/data/BBDP1/M.DE.N.VPI.C.A00000.I20.A';

    public const SOURCE = 'bundesbank';

    public function __construct(private readonly BundesbankSeries $series) {}

    /** @return int Anzahl neuer oder geänderter Monatswerte */
    public function import(): int {
        return $this->ingest($this->series->csv(self::SERIES_URL));
    }

    /**
     * Zeilen „JJJJ-MM;Wert;…" der Bundesbank-CSV (Dezimalkomma). Ein revidierter
     * Wert fällt zurück in die Prüfung.
     *
     * @return int Anzahl neuer oder geänderter Monatswerte
     */
    public function ingest(string $csv, string $source = self::SOURCE): int {
        $changed = 0;
        foreach (BundesbankSeries::monthlyValues($csv) as $month => $value) {
            // Ein Index ist nie negativ; eine solche Zeile ist keine Wertzeile.
            if (str_starts_with($value, '-')) {
                continue;
            }
            $row = $this->find(PriceIndexValue::SERIES_VPI, $month . '-01') ?? new PriceIndexValue(['series' => PriceIndexValue::SERIES_VPI, 'period_on' => $month . '-01']);
            if ($row->exists && NumberHelper::comparePrecise((string) $row->value, $value, 1) === 0) {
                continue;
            }
            $row->fill(['value' => $value, 'source' => $source, 'status' => PriceIndexStatus::Pending, 'approver_user_id' => null, 'approved_at' => null])->save();
            $changed++;
        }

        return $changed;
    }

    public function approve(PriceIndexValue $value, User $actor): PriceIndexValue {
        $this->assertStatusTransition($value->status, PriceIndexStatus::Approved);
        $value->forceFill(['status' => PriceIndexStatus::Approved, 'approver_user_id' => $actor->id, 'approved_at' => now()])->save();

        return $value;
    }

    public function reject(PriceIndexValue $value, User $actor): PriceIndexValue {
        $this->assertStatusTransition($value->status, PriceIndexStatus::Rejected);
        $value->forceFill(['status' => PriceIndexStatus::Rejected, 'approver_user_id' => $actor->id, 'approved_at' => now()])->save();

        return $value;
    }

    /** Wert einer Reihe für den Monatsersten, unabhängig vom Freigabestand. */
    public function find(string $series, string $periodOn): ?PriceIndexValue {
        return PriceIndexValue::query()->where('series', $series)
            ->whereBetween('period_on', DateRange::days($periodOn, $periodOn))
            ->first();
    }

    /** Jüngster freigegebener Wert bis einschließlich zum Stichtag. */
    public function latestApproved(CarbonInterface $until, string $series = PriceIndexValue::SERIES_VPI): ?PriceIndexValue {
        return PriceIndexValue::query()->approved()->where('series', $series)
            ->where('period_on', '<', DateRange::dayAfter($until))
            ->orderByDesc('period_on')
            ->first();
    }
}
