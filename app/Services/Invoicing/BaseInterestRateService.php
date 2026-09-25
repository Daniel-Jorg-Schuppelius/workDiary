<?php
/*
 * Created on   : Fri Sep 25 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : BaseInterestRateService.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Invoicing;

use App\Models\Invoicing\BaseInterestRate;
use App\Plugins\Support\PluginHttpFactory;
use App\Support\Query\DateRange;
use Carbon\{CarbonImmutable, CarbonInterface};
use CommonToolkit\Helper\Data\NumberHelper;
use RuntimeException;

/**
 * Basiszinssatz nach § 247 BGB (MVP-879): Abruf der Bundesbank-Reihe
 * BBIN1.M.DE.BBK.BBKBAS2.EUR.ME (Monatsendstände), Verdichtung zu
 * Gültigkeitsperioden und Nachschlagen je Stichtag. Der Satz ändert sich zum
 * 1. Januar und 1. Juli; eine Periode beginnt mit dem ersten Monat, dessen
 * Monatsendstand vom Vormonat abweicht.
 */
class BaseInterestRateService {
    public const SERIES_URL = 'https://api.statistiken.bundesbank.de/rest/data/BBIN1/M.DE.BBK.BBKBAS2.EUR.ME';

    public const SOURCE = 'bundesbank';

    public function __construct(private readonly PluginHttpFactory $http) {}

    /** @return int Anzahl neu angelegter oder geänderter Perioden */
    public function import(): int {
        $response = $this->http->coreClient('bundesbank', self::SERIES_URL)
            ->getResponse(self::SERIES_URL, ['format' => 'csv'], ['timeout' => 30]);
        if (! $response->successful()) {
            throw new RuntimeException('Bundesbank-Abruf fehlgeschlagen (HTTP ' . $response->status() . ').');
        }

        return $this->ingest($response->body());
    }

    /**
     * Zeilen „JJJJ-MM;Satz;…" der Bundesbank-CSV (Dezimalkomma); Kopf- und
     * Bemerkungszeilen werden übersprungen.
     *
     * @return int Anzahl neu angelegter oder geänderter Perioden
     */
    public function ingest(string $csv): int {
        $months = [];
        foreach (preg_split('/\r?\n/', $csv) ?: [] as $line) {
            if (preg_match('/^(\d{4})-(\d{2});(-?[\d.,]+);/', trim($line), $m) === 1) {
                $months[$m[1] . '-' . $m[2]] = NumberHelper::normalizeDecimalString($m[3]);
            }
        }
        ksort($months);

        $changed = 0;
        $previous = null;
        foreach ($months as $month => $rate) {
            if ($rate === $previous) {
                continue;
            }
            $previous = $rate;
            $row = BaseInterestRate::query()->updateOrCreate(
                ['valid_from' => $month . '-01'],
                ['rate' => $rate, 'source' => self::SOURCE],
            );
            if ($row->wasRecentlyCreated || $row->wasChanged('rate')) {
                $changed++;
            }
        }

        return $changed;
    }

    /** Basiszinssatz am Stichtag in % p. a. oder null, wenn für den Tag keiner bekannt ist. */
    public function rateOn(CarbonInterface $day): ?float {
        $row = BaseInterestRate::query()
            ->where('valid_from', '<', DateRange::dayAfter($day))
            ->orderByDesc('valid_from')
            ->first();

        return $row === null ? null : (float) $row->rate;
    }

    /**
     * Abschnitte gleichen Basiszinses zwischen zwei Tagen (beide einschließlich).
     * Null, wenn für den ersten Tag kein Satz bekannt ist.
     *
     * @return list<array{from: CarbonImmutable, to: CarbonImmutable, rate: float}>|null
     */
    public function periods(CarbonInterface $from, CarbonInterface $to): ?array {
        $start = CarbonImmutable::parse($from->toDateString());
        $end = CarbonImmutable::parse($to->toDateString());
        $first = $this->rateOn($start);
        if ($first === null || $end->lessThan($start)) {
            return null;
        }

        $changes = BaseInterestRate::query()
            ->where('valid_from', '>=', DateRange::dayAfter($start))
            ->where('valid_from', '<', DateRange::dayAfter($end))
            ->orderBy('valid_from')
            ->get();

        $periods = [];
        $cursor = $start;
        $rate = $first;
        foreach ($changes as $change) {
            $changeDay = CarbonImmutable::parse($change->valid_from->toDateString());
            $periods[] = ['from' => $cursor, 'to' => $changeDay->subDay(), 'rate' => $rate];
            $cursor = $changeDay;
            $rate = (float) $change->rate;
        }
        $periods[] = ['from' => $cursor, 'to' => $end, 'rate' => $rate];

        return $periods;
    }
}
