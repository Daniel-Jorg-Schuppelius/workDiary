<?php
/*
 * Created on   : Sun Aug 23 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : VatFilingPeriodService.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Accounting\Filing;

use App\Enums\Finance\VatFilingInterval;
use App\Models\Organization;
use App\Services\Accounting\VatFilingProfileResolver;
use Carbon\CarbonImmutable;

/**
 * Voranmeldungszeiträume eines Kalenderjahres (Feature 125, MVP-685).
 *
 * Der Zeitraum folgt dem **Kalenderjahr**, auch bei abweichendem
 * Geschäftsjahr: § 18 UStG kennt kein Wirtschaftsjahr. Wechselt das Intervall
 * unterjährig, entstehen die Perioden abschnittsweise — für jeden Tag gilt das
 * Intervall, das an diesem Tag galt.
 */
class VatFilingPeriodService {
    public function __construct(private readonly VatFilingProfileResolver $profile) {}

    /**
     * Alle Perioden eines Kalenderjahres.
     *
     * @return list<VatReturnPeriod>
     */
    public function periodsFor(Organization $organization, int $year): array {
        $periods = [];
        $cursor = CarbonImmutable::parse(sprintf('%04d-%02d-%02d', $year, 1, 1));
        $end = $cursor->endOfYear();

        while ($cursor->lessThanOrEqualTo($end)) {
            $interval = $this->profile->at($organization, $cursor);

            if (! $interval->hasAdvanceReturns()) {
                // Jahreserklärung oder gar keine Meldung: eine Periode, fertig.
                if ($interval === VatFilingInterval::Annual) {
                    $periods[] = VatReturnPeriod::make($interval, $year, 1);
                }

                break;
            }

            $period = $this->periodOf($interval, $cursor);
            $periods[$period->key] = $period;
            $cursor = $period->to->addDay();
        }

        return array_values($periods);
    }

    /** Die Periode, in die ein Datum fällt. */
    public function periodAt(Organization $organization, CarbonImmutable $date): ?VatReturnPeriod {
        $interval = $this->profile->at($organization, $date);

        if ($interval === VatFilingInterval::None) {
            return null;
        }

        return $this->periodOf($interval, $date);
    }

    /**
     * Periodenschlüssel auflösen — `2026-M03`, `2026-Q1`, `2026-J`.
     *
     * Ein unbekannter Schlüssel liefert null; der Aufrufer fällt dann auf die
     * laufende Periode zurück, statt einen willkürlichen Zeitraum zu rechnen.
     */
    public function parse(string $key): ?VatReturnPeriod {
        if (preg_match('/^(\d{4})-M(\d{2})$/', $key, $match) === 1) {
            $month = (int) $match[2];

            return $month >= 1 && $month <= 12
                ? VatReturnPeriod::make(VatFilingInterval::Monthly, (int) $match[1], $month)
                : null;
        }

        if (preg_match('/^(\d{4})-Q([1-4])$/', $key, $match) === 1) {
            return VatReturnPeriod::make(VatFilingInterval::Quarterly, (int) $match[1], (int) $match[2]);
        }

        if (preg_match('/^(\d{4})-J$/', $key, $match) === 1) {
            return VatReturnPeriod::make(VatFilingInterval::Annual, (int) $match[1], 1);
        }

        return null;
    }

    private function periodOf(VatFilingInterval $interval, CarbonImmutable $date): VatReturnPeriod {
        $ordinal = match ($interval) {
            VatFilingInterval::Monthly => $date->month,
            VatFilingInterval::Quarterly => (int) ceil($date->month / 3),
            default => 1,
        };

        return VatReturnPeriod::make($interval, $date->year, $ordinal);
    }
}
