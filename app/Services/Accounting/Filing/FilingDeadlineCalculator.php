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

namespace App\Services\Accounting\Filing;

use App\Enums\Finance\FilingObligationKind;
use App\Models\Organization;
use App\Services\Accounting\VatFilingProfileResolver;
use App\Services\HolidayService;
use Carbon\CarbonImmutable;

/**
 * Fristen der steuerlichen Meldepflichten (Feature 125, MVP-686).
 *
 * Zwei Regeln bestimmen jeden Termin: die Grundfrist der jeweiligen Vorschrift
 * und § 108 Abs. 3 AO — fällt das Ende auf Samstag, Sonntag oder einen
 * Feiertag, verschiebt es sich auf den nächsten Werktag. Welche Feiertage
 * gelten, hängt vom Rechtsraum der Organisation ab; deshalb derselbe
 * {@see HolidayService} wie bei Zuschlägen und Compliance.
 *
 * Die Dauerfristverlängerung verlängert **nur** die Voranmeldung. Für die
 * Zusammenfassende Meldung gilt sie ausdrücklich nicht (§ 18a Abs. 1 UStG).
 */
class FilingDeadlineCalculator {
    public function __construct(
        private readonly VatFilingProfileResolver $profile,
        private readonly HolidayService $holidays,
    ) {}

    /**
     * Abgabefrist einer Voranmeldung: 10. Tag nach Periodenende, mit
     * Dauerfristverlängerung einen Monat später.
     */
    public function vatAdvance(Organization $organization, VatReturnPeriod $period): CarbonImmutable {
        $base = $period->to->addDay()->startOfDay()->addDays(9);

        if ($this->profile->hasExtension($organization, $period->to)) {
            $base = $base->addMonthNoOverflow();
        }

        return $this->holidays->nextBusinessDay($base);
    }

    /** Sondervorauszahlung: 10. Februar des Jahres (§ 48 Abs. 1 UStDV). */
    public function specialPrepayment(int $year): CarbonImmutable {
        return $this->holidays->nextBusinessDay(CarbonImmutable::parse(sprintf('%04d-%02d-%02d', $year, 2, 10)));
    }

    /**
     * Zusammenfassende Meldung: 25. Tag nach Meldezeitraum — ohne
     * Verlängerung, auch wenn eine Dauerfristverlängerung vorliegt.
     */
    public function recapitulative(VatReturnPeriod $period): CarbonImmutable {
        return $this->holidays->nextBusinessDay($period->to->addDay()->startOfDay()->addDays(24));
    }

    /**
     * Jahreserklärung: 31.07. des Folgejahres, steuerlich beraten der letzte
     * Tag des Februars im Zweitfolgejahr (§ 149 Abs. 2/3 AO).
     */
    public function annualReturn(int $year, bool $advised): CarbonImmutable {
        $base = $advised
            ? CarbonImmutable::parse(sprintf('%04d-%02d-%02d', $year + 2, 2, 1))->endOfMonth()->startOfDay()
            : CarbonImmutable::parse(sprintf('%04d-%02d-%02d', $year + 1, 7, 31));

        return $this->holidays->nextBusinessDay($base);
    }

    /** Frist einer Pflicht — ein Aufrufpfad für alle Arten. */
    public function forKind(Organization $organization, FilingObligationKind $kind, VatReturnPeriod $period, bool $advised = false): CarbonImmutable {
        return match ($kind) {
            FilingObligationKind::VatAdvance => $this->vatAdvance($organization, $period),
            FilingObligationKind::SpecialPrepayment => $this->specialPrepayment($period->year),
            FilingObligationKind::Recapitulative => $this->recapitulative($period),
            FilingObligationKind::AnnualReturn => $this->annualReturn($period->year, $advised),
        };
    }

}
