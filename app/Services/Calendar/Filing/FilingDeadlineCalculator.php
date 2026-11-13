<?php
/*
 * Created on   : Sun Aug 23 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : FilingDeadlineCalculator.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Calendar\Filing;

use App\Enums\Period\PeriodKind;
use App\Services\Calendar\HolidayService;
use App\Support\Period\PeriodKey;
use Carbon\CarbonImmutable;
use CommonToolkit\Helper\Data\DateHelper;
use InvalidArgumentException;

/**
 * Der eine Fristenrechner für Steuer- und Beitragstermine (Feature 125,
 * MVP-686; seit Feature 197, MVP-1631, im Kalender statt im Finanzmodul).
 * Eigene Organisation (Profil aus dem Finanzmodul) und Kunden (Profil aus den
 * Fristmerkmalen) rechnen hier — sonst liefen Feiertage, Verlängerung und
 * Wochenendregel auseinander, und eine Kanzlei ohne Finanzmodul hätte keinen.
 *
 * Steuertermine: Grundfrist der Vorschrift, dann § 108 Abs. 3 AO — fällt das
 * Ende auf Samstag, Sonntag oder einen gesetzlichen Feiertag am Ort der
 * Behörde, endet die Frist am nächsten Werktag. Die Dauerfristverlängerung
 * verlängert **nur** die Voranmeldung (§ 46 UStDV), nicht die Zusammenfassende
 * Meldung (§ 18a Abs. 1 UStG) und nicht die Lohnsteuer-Anmeldung (§ 41a EStG).
 *
 * Sozialversicherung (MVP-1676): Fälligkeit am drittletzten Bankarbeitstag des
 * Monats (§ 23 Abs. 1 SGB IV), Beitragsnachweis zwei Arbeitstage davor
 * (§ 28f Abs. 3 SGB IV). Bankarbeitstage: Montag bis Freitag ohne gesetzliche
 * Feiertage am Sitz der Einzugsstelle und ohne 24.12. und 31.12.; bei mehreren
 * Einzugsstellen gilt der früheste Termin.
 */
class FilingDeadlineCalculator {
    /**
     * Art. 97 § 36 Abs. 3 Nr. 1 und 2 EGAO: verlängerte Fristen beratener
     * Erklärungen der Besteuerungszeiträume 2020–2024 (Regelfall, Land- und
     * Forstwirtschaft mit abweichendem Wirtschaftsjahr). Ab 2025 gilt wieder
     * § 149 Abs. 3 AO.
     *
     * @var array<int, array{0: string, 1: string}>
     */
    private const array ADVISED_TRANSITION = [
        2020 => ['2022-08-31', '2023-01-31'],
        2021 => ['2023-08-31', '2024-01-31'],
        2022 => ['2024-07-31', '2024-12-31'],
        2023 => ['2025-05-31', '2025-10-31'],
        2024 => ['2026-04-30', '2026-09-30'],
    ];

    /**
     * Art. 97 § 36 Abs. 3 Nr. 3 und 4 EGAO: Monatsfrist unberatener Erklärungen
     * (§ 149 Abs. 2 AO, sonst sieben Monate) der Besteuerungszeiträume 2020–2023.
     *
     * @var array<int, int>
     */
    private const array UNADVISED_TRANSITION_MONTHS = [
        2020 => 10,
        2021 => 10,
        2022 => 9,
        2023 => 8,
    ];

    /** Regelfrist unberatener Erklärungen in Monaten nach Ablauf des Kalenderjahres (§ 149 Abs. 2 Satz 1 AO). */
    private const int UNADVISED_MONTHS = 7;

    /**
     * Keine Bankarbeitstage, obwohl kein Feiertag: Heiligabend und Silvester (Monat-Tag).
     *
     * @var list<string>
     */
    private const array BANK_CLOSING_DAYS = ['12-24', '12-31'];

    public function __construct(private readonly HolidayService $holidays) {}

    /**
     * Umsatzsteuer-Voranmeldung: 10. Tag nach Ablauf des Voranmeldungszeitraums
     * (§ 18 Abs. 1 UStG), mit Dauerfristverlängerung einen Monat später (§ 46 UStDV).
     */
    public function vatAdvance(FilingProfile $profile, PeriodKey $period): CarbonImmutable {
        $base = $this->tenthDayAfter($period);
        if ($profile->vatExtension) {
            $base = $base->addMonthNoOverflow();
        }

        return $this->nextBusinessDay($profile, $base);
    }

    /** Sondervorauszahlung mit dem Antrag auf Dauerfristverlängerung: 10. Februar (§ 48 Abs. 1 UStDV). */
    public function specialPrepayment(FilingProfile $profile, int $year): CarbonImmutable {
        return $this->nextBusinessDay($profile, $this->date($year, 2, 10));
    }

    /**
     * Zusammenfassende Meldung: 25. Tag nach Ablauf des Meldezeitraums
     * (§ 18a Abs. 1 UStG) — ohne Verlängerung, auch bei Dauerfristverlängerung.
     */
    public function recapitulative(FilingProfile $profile, PeriodKey $period): CarbonImmutable {
        return $this->nextBusinessDay($profile, $period->end()->addDays(25));
    }

    /** Lohnsteuer-Anmeldung: 10. Tag nach Ablauf des Anmeldungszeitraums (§ 41a Abs. 1 EStG), keine Verlängerung. */
    public function wageTaxAdvance(FilingProfile $profile, PeriodKey $period): CarbonImmutable {
        return $this->nextBusinessDay($profile, $this->tenthDayAfter($period));
    }

    /**
     * Jahreserklärung des Besteuerungszeitraums $year (§ 149 Abs. 2 und 3 AO):
     * unberaten sieben Monate nach Ablauf des Kalenderjahres (31.07.), beraten
     * der letzte Februartag des zweiten Folgejahres; Land- und Forstwirtschaft
     * mit abweichendem Wirtschaftsjahr sieben Monate nach Ende des im Jahr
     * begonnenen Wirtschaftsjahres bzw. beraten der 31.07. des zweiten
     * Folgejahres. Für 2020–2024 die Übergangsfristen aus Art. 97 § 36 EGAO.
     */
    public function annualReturn(FilingProfile $profile, int $year): CarbonImmutable {
        return $this->nextBusinessDay($profile, $this->annualReturnBase($profile, $year));
    }

    /**
     * Fälligkeit der Sozialversicherungsbeiträge eines Monats: drittletzter
     * Bankarbeitstag (§ 23 Abs. 1 SGB IV), frühester Termin der Bundesländer.
     *
     * @param  list<string>  $regions  Yasumi-Provider der Einzugsstellen (mindestens einer)
     */
    public function contributionDue(array $regions, PeriodKey $month): CarbonImmutable {
        return $this->earliest($regions, fn (string $region): CarbonImmutable => $this->contributionDueIn($region, $month));
    }

    /**
     * Beitragsnachweis eines Monats: zwei Arbeitstage vor der Fälligkeit
     * (§ 28f Abs. 3 SGB IV), gezählt in Bankarbeitstagen — also der
     * fünftletzte Bankarbeitstag; er muss zu dessen Beginn vorliegen.
     *
     * @param  list<string>  $regions  Yasumi-Provider der Einzugsstellen (mindestens einer)
     */
    public function contributionStatement(array $regions, PeriodKey $month): CarbonImmutable {
        return $this->earliest($regions, fn (string $region): CarbonImmutable => CarbonImmutable::instance(
            DateHelper::addWorkingDays($this->contributionDueIn($region, $month), -2, $this->bankClosedDays($region, $month->year)),
        ));
    }

    /** Nächster Werktag ab $date nach dem Kalender des Profils (§ 108 Abs. 3 AO). */
    public function nextBusinessDay(FilingProfile $profile, CarbonImmutable $date): CarbonImmutable {
        return $profile->holidayRegion === null
            ? $this->holidays->nextBusinessDay($date)
            : $this->holidays->nextStatutoryBusinessDay($date, $profile->holidayRegion);
    }

    private function annualReturnBase(FilingProfile $profile, int $year): CarbonImmutable {
        $agriculture = $profile->hasAgricultureFiscalYear();
        if ($profile->taxAdvised) {
            if (isset(self::ADVISED_TRANSITION[$year])) {
                return CarbonImmutable::parse(self::ADVISED_TRANSITION[$year][$agriculture ? 1 : 0])->startOfDay();
            }

            return $agriculture ? $this->date($year + 2, 7, 31) : $this->date($year + 2, 2, 1)->endOfMonth()->startOfDay();
        }

        $months = self::UNADVISED_TRANSITION_MONTHS[$year] ?? self::UNADVISED_MONTHS;
        // Letzter Monat des Zeitraums: Dezember bzw. der Schlussmonat des im Jahr begonnenen Wirtschaftsjahres.
        $lastMonth = $agriculture ? $this->date($year + 1, $profile->fiscalYearStartMonth - 1, 1) : $this->date($year, 12, 1);

        return $lastMonth->addMonthsNoOverflow($months)->endOfMonth()->startOfDay();
    }

    private function tenthDayAfter(PeriodKey $period): CarbonImmutable {
        return $period->end()->addDays(10);
    }

    private function contributionDueIn(string $region, PeriodKey $month): CarbonImmutable {
        if ($month->kind !== PeriodKind::Month) {
            throw new InvalidArgumentException('Beitragstermine gelten je Monat: ' . $month->key());
        }
        $closed = $this->bankClosedDays($region, $month->year);
        $day = $month->end()->addDay();
        for ($i = 0; $i < 3; $i++) {
            $day = CarbonImmutable::instance(DateHelper::getPreviousWorkingDay($day, $closed));
        }

        return $day->startOfDay();
    }

    /** @return list<string> Tage ohne Bankbetrieb (Y-m-d) eines Jahres am Sitz der Einzugsstelle */
    private function bankClosedDays(string $region, int $year): array {
        $days = array_keys($this->holidays->statutoryForYear($year, $region));
        foreach (self::BANK_CLOSING_DAYS as $monthDay) {
            $days[] = $year . '-' . $monthDay;
        }

        return $days;
    }

    /**
     * @param  list<string>  $regions
     * @param  callable(string): CarbonImmutable  $date
     */
    private function earliest(array $regions, callable $date): CarbonImmutable {
        $earliest = null;
        foreach (array_values(array_unique($regions)) as $region) {
            $candidate = $date($region);
            if ($earliest === null || $candidate->lessThan($earliest)) {
                $earliest = $candidate;
            }
        }

        return $earliest ?? throw new InvalidArgumentException('Ohne Bundesland der Einzugsstelle kein Beitragstermin.');
    }

    private function date(int $year, int $month, int $day): CarbonImmutable {
        return CarbonImmutable::create($year, $month, $day, 0, 0, 0) ?? throw new InvalidArgumentException("Datum nicht darstellbar: {$year}-{$month}-{$day}");
    }
}
