<?php
/*
 * Created on   : Sun Aug 23 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : DeadlineScanRegistry.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Notification\DeadlineScans;

/**
 * Registry aller Fristen-Scans (Vollscan 2026-08, B11): eine Klasse je
 * Fachmodul, der Command iteriert nur noch.
 *
 * Reihenfolge = historische handle()-Reihenfolge des ScanDeadlinesCommand
 * (Wartung lief dort nach den Vergabefristen, jetzt im Asset-Modul davor).
 * Sie ist NICHT verhaltensrelevant: Dedup/Eskalation laufen pro (Org, Event,
 * Subjekt, Stufe) über das notification_dispatch_log, kein Event/Subjekt-Paar
 * kommt in zwei Scans vor, und Statefortschreibung (escalation_level,
 * last_warned_period) bleibt scan-intern. Festgeschrieben bleibt sie trotzdem
 * — für stabile Logs und vergleichbare Läufe.
 */
final class DeadlineScanRegistry {
    /** @var list<class-string<DeadlineScan>> */
    private const SCANS = [
        OpenIssueDeadlineScan::class,
        CommunicationFollowupScan::class,
        DocumentExpiryScan::class,
        IsmsDeadlineScans::class,
        SlaTicketScan::class,
        HelpdeskFollowupScans::class,
        SlaQuotaScan::class,
        AssetDeadlineScans::class,
        TenderDeadlineScan::class,
        QualificationExpiryScan::class,
        ShiftExchangeReminderScan::class,
        RentalReturnScan::class,
        AssetFinanceDeadlineScan::class,
        ContractObligationScan::class,
        AssetInspectionScan::class,
        DriverLicenseCheckScan::class,
        DomainExpiryScan::class,
        InvestmentDecisionScan::class,
        QuoteFollowUpScan::class,
        RetentionReleaseScan::class,
        GuaranteeDeadlineScan::class,
        WarrantyPeriodScan::class,
        SupplierCredentialScan::class,
        // Neue Scans hängen hinten an (stabile Log-Reihenfolge) — Arbeitsschutz (Feature 132).
        SafetyDeadlineScans::class,
        // Wetterwarnungen für disponierte Einsätze (Feature 062, MVP-716).
        WeatherWarningScan::class,
        // Pflichtschulungen (Feature 145, MVP-727).
        TrainingDeadlineScan::class,
    ];

    /** @return list<DeadlineScan> */
    public function scans(): array {
        return array_map(
            static fn(string $class): DeadlineScan => app($class),
            self::SCANS,
        );
    }
}
