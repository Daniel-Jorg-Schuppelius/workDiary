<?php
/*
 * Created on   : Tue Aug 25 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : GobdLockRegistry.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Support\Gobd;

/**
 * Registry der festschreibungspflichtigen Modelle (GoBD-Unveränderbarkeit).
 * Bis MVP-699 lebte die Liste nur im Architektur-Gate
 * {@see \Tests\Unit\Architecture\GobdLockGuardRuleTest}; die
 * Verfahrensdokumentation (Feature 134) braucht sie zur Laufzeit — daher
 * app-seitig, das Gate liest von hier. Neue Modelle mit Freeze-Guard,
 * AppendOnly-Trait oder Hash-Kette werden HIER ergänzt (Tabellenname beachten).
 */
final class GobdLockRegistry {
    /** Freeze-Guard im Modell (booted): nach Ausstellung/Finalisierung unveränderlich. */
    public const MECHANISM_FREEZE = 'freeze';

    /** AppendOnly-Trait: UPDATE/DELETE werfen auf Modell-Ebene. */
    public const MECHANISM_APPEND_ONLY = 'append_only';

    /** HashChainable: append-only UND SHA-256-Kette (audit:verify). */
    public const MECHANISM_CHAIN = 'chain';

    /**
     * Kurzname → [Modell-Datei, Tabelle, Mechanismus].
     *
     * @var array<string, array{file: string, table: string, mechanism: string}>
     */
    public const MODELS = [
        // Freeze nach Ausstellung/Finalisierung/Übergabe
        'Invoice' => ['file' => 'app/Models/Invoice.php', 'table' => 'invoices', 'mechanism' => self::MECHANISM_FREEZE],
        'DatevBookingBatch' => ['file' => 'app/Models/Finance/DatevBookingBatch.php', 'table' => 'datev_booking_batches', 'mechanism' => self::MECHANISM_FREEZE],
        // MVP-672: Festbuchung ist unveränderlich; nur der Storno-Vermerk darf noch entstehen.
        'AccountingEntry' => ['file' => 'app/Models/Accounting/AccountingEntry.php', 'table' => 'accounting_entries', 'mechanism' => self::MECHANISM_FREEZE],
        'AccountingEntryLine' => ['file' => 'app/Models/Accounting/AccountingEntryLine.php', 'table' => 'accounting_entry_lines', 'mechanism' => self::MECHANISM_FREEZE],
        // MVP-674: Ausgleiche sind unveränderlich — ein Rückläufer erzeugt eine Gegenbewegung.
        'AccountingOpenItemSettlement' => ['file' => 'app/Models/Accounting/AccountingOpenItemSettlement.php', 'table' => 'accounting_open_item_settlements', 'mechanism' => self::MECHANISM_FREEZE],
        'BillingTransfer' => ['file' => 'app/Models/Finance/BillingTransfer.php', 'table' => 'billing_transfers', 'mechanism' => self::MECHANISM_FREEZE],
        // MVP-702 (Feature 137): Fahrtenbuch-Fahrt nach Festschreibung unveränderlich — Korrektur nur als Stornofahrt.
        'TravelLog' => ['file' => 'app/Models/TravelLog.php', 'table' => 'travel_logs', 'mechanism' => self::MECHANISM_FREEZE],
        // Append-only Nachweise (AppendOnly-Trait)
        'StockMovement' => ['file' => 'app/Models/StockMovement.php', 'table' => 'stock_movements', 'mechanism' => self::MECHANISM_APPEND_ONLY],
        'DiaryEntryEvent' => ['file' => 'app/Models/DiaryEntryEvent.php', 'table' => 'diary_entry_events', 'mechanism' => self::MECHANISM_APPEND_ONLY],
        'WeatherSnapshot' => ['file' => 'app/Models/WeatherSnapshot.php', 'table' => 'weather_snapshots', 'mechanism' => self::MECHANISM_APPEND_ONLY],
        'DocumentRenderSnapshot' => ['file' => 'app/Models/DocumentDesign/DocumentRenderSnapshot.php', 'table' => 'document_render_snapshots', 'mechanism' => self::MECHANISM_APPEND_ONLY],
        'AgileEvent' => ['file' => 'app/Models/Agile/AgileEvent.php', 'table' => 'agile_events', 'mechanism' => self::MECHANISM_APPEND_ONLY],
        'AssetInspectionEvent' => ['file' => 'app/Models/AssetCompliance/AssetInspectionEvent.php', 'table' => 'asset_inspection_events', 'mechanism' => self::MECHANISM_APPEND_ONLY],
        'AssetCalibrationCertificate' => ['file' => 'app/Models/AssetCompliance/AssetCalibrationCertificate.php', 'table' => 'asset_calibration_certificates', 'mechanism' => self::MECHANISM_APPEND_ONLY],
        // Vollaudit 2026-07, H14: verankert die Buchungssperre
        'CashDailyClosing' => ['file' => 'app/Models/CashDailyClosing.php', 'table' => 'cash_daily_closings', 'mechanism' => self::MECHANISM_FREEZE],
        // Vollaudit 2026-07 (M56): ISMS-Freeze-Guards (046 — finalisiert/genehmigt = eingefroren)
        'IsmsAuditPackage' => ['file' => 'app/Models/Isms/IsmsAuditPackage.php', 'table' => 'isms_audit_packages', 'mechanism' => self::MECHANISM_FREEZE],
        'IsmsRiskAssessment' => ['file' => 'app/Models/Isms/IsmsRiskAssessment.php', 'table' => 'isms_risk_assessments', 'mechanism' => self::MECHANISM_FREEZE],
        // Vollaudit 2026-07 (M52): GoBD-nahe Nachweis-Events, jetzt mit AppendOnly-Trait
        'MonthClosureEvent' => ['file' => 'app/Models/MonthClosureEvent.php', 'table' => 'month_closure_events', 'mechanism' => self::MECHANISM_APPEND_ONLY],
        'TimeExportEvent' => ['file' => 'app/Models/TimeExportEvent.php', 'table' => 'time_export_events', 'mechanism' => self::MECHANISM_APPEND_ONLY],

        // HashChained-Ereignisketten (append-only, Hash-Kette)
        'CashEntry' => ['file' => 'app/Models/CashEntry.php', 'table' => 'cash_entries', 'mechanism' => self::MECHANISM_CHAIN], // MVP-414 Kassenbuch
        'AuditLog' => ['file' => 'app/Models/AuditLog.php', 'table' => 'audit_logs', 'mechanism' => self::MECHANISM_CHAIN],
        'OrganizationAuditLog' => ['file' => 'app/Models/OrganizationAuditLog.php', 'table' => 'organization_audit_logs', 'mechanism' => self::MECHANISM_CHAIN],
        'BillingTransferEvent' => ['file' => 'app/Models/Finance/BillingTransferEvent.php', 'table' => 'billing_transfer_events', 'mechanism' => self::MECHANISM_CHAIN],
        'DatevBookingEvent' => ['file' => 'app/Models/Finance/DatevBookingEvent.php', 'table' => 'datev_booking_events', 'mechanism' => self::MECHANISM_CHAIN],
        'AccountingEvent' => ['file' => 'app/Models/Accounting/AccountingEvent.php', 'table' => 'accounting_events', 'mechanism' => self::MECHANISM_CHAIN], // MVP-672
        'PaymentReconciliationEvent' => ['file' => 'app/Models/Finance/PaymentReconciliationEvent.php', 'table' => 'payment_reconciliation_events', 'mechanism' => self::MECHANISM_CHAIN],
        'CaseEvent' => ['file' => 'app/Models/Whistleblowing/CaseEvent.php', 'table' => 'case_events', 'mechanism' => self::MECHANISM_CHAIN],
        'RequestEvent' => ['file' => 'app/Models/Privacy/RequestEvent.php', 'table' => 'request_events', 'mechanism' => self::MECHANISM_CHAIN],
        'IncidentEvent' => ['file' => 'app/Models/Privacy/IncidentEvent.php', 'table' => 'incident_events', 'mechanism' => self::MECHANISM_CHAIN],
    ];

    /** Übersetztes Mechanismus-Label (Verfahrensdokumentation). */
    public static function mechanismLabel(string $mechanism): string {
        return (string) __('procedure-documentation.immutability.mechanism.' . $mechanism);
    }
}
