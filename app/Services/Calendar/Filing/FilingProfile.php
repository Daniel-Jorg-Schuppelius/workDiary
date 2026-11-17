<?php
/*
 * Created on   : Thu Nov 12 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : FilingProfile.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Calendar\Filing;

use App\Enums\Period\PeriodKind;

/**
 * Meldeprofil eines Steuerpflichtigen für den Fristenrechner (Feature 197,
 * MVP-1631): Turnus der Umsatzsteuer-Voranmeldung, Dauerfristverlängerung,
 * steuerliche Beratung, Feiertagsregion des Finanzamts und das abweichende
 * Wirtschaftsjahr der Land- und Forstwirtschaft (§ 149 Abs. 2 Satz 2 AO).
 *
 * Das Finanzmodul baut das Profil der **eigenen** Organisation
 * ({@see \App\Services\Accounting\VatFilingProfileResolver::filingProfile()}),
 * der Regelsatz Steuerberatung das eines Kunden aus dessen Fristmerkmalen.
 *
 * `holidayRegion` null heißt: Kalender der Organisation (Rechtsraum aus den
 * Einstellungen samt eigener Feiertage) — so rechneten die eigenen Fristen
 * schon vor dem Umzug, und das bleibt (eingefrorene Fälle
 * `tests/Fixtures/filing-deadlines`). Mit Region zählen nur die gesetzlichen
 * Feiertage am Ort des Finanzamts (§ 108 Abs. 3 AO).
 */
final readonly class FilingProfile {
    public function __construct(
        /** Voranmeldungszeitraum: Monat oder Quartal; null = keine Voranmeldung (Jahreszahler, befreit). */
        public ?PeriodKind $vatInterval = PeriodKind::Quarter,
        public bool $vatExtension = false,
        public bool $taxAdvised = false,
        /** Yasumi-Provider des Finanzamts (`Germany\Bavaria`); null = Kalender der Organisation. */
        public ?string $holidayRegion = null,
        /** Land- und Forstwirtschaft mit abweichendem Wirtschaftsjahr (§ 149 Abs. 2 Satz 2 AO). */
        public bool $agriculture = false,
        /** Erster Monat des Wirtschaftsjahres (1 = Kalenderjahr). */
        public int $fiscalYearStartMonth = 1,
    ) {}

    /** Gilt die Sonderregel des abweichenden Wirtschaftsjahres der Land- und Forstwirtschaft? */
    public function hasAgricultureFiscalYear(): bool {
        return $this->agriculture && $this->fiscalYearStartMonth >= 2 && $this->fiscalYearStartMonth <= 12;
    }
}
