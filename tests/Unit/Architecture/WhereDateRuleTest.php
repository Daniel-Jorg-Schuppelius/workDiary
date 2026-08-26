<?php
/*
 * Created on   : Tue Aug 25 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : WhereDateRuleTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace Tests\Unit\Architecture;

use PHPUnit\Framework\TestCase;
use Tests\Unit\Architecture\Concerns\ScansSourceTree;

/**
 * Architektur-Gate gegen `whereDate()` auf indizierten Spalten (Vollscan
 * 2026-08-23, A8; MVP-722).
 *
 * `whereDate($spalte, …)` erzeugt `DATE(spalte) …` — eine Funktion auf der
 * Spalte. Auf DATETIME-/TIMESTAMP-Spalten fällt damit jeder Index aus;
 * gemessen an 50.000 `time_entries`: Zeitraumsumme 7,4 ms `type=ALL` gegen
 * 0,7 ms `type=range` nach der Umstellung. 347 Stellen waren betroffen.
 *
 * Regel: Zeitraumfilter laufen über {@see \App\Support\Query\DateRange} —
 * `whereDateBetween()`/`days()` für DATE-Spalten, `whereTimestampBetween()`
 * bzw. `dayStart()`/`dayAfter()` für DATETIME/TIMESTAMP.
 *
 * Der Bestand steht als BASELINE (Stand MVP-722, nach der Umstellung der
 * gemessenen Brennpunkte). Er darf nur FALLEN: neue Dateien sind verboten,
 * bestehende dürfen nicht wachsen, und wer eine Stelle abräumt, trägt die
 * kleinere Zahl hier nach (sonst meldet {@see test_baseline_is_not_stale}).
 */
class WhereDateRuleTest extends TestCase {
    use ScansSourceTree;

    /**
     * Bewusst nicht erfasste Pfade: Präfix → Begründung.
     *
     * @var array<string, string>
     */
    private const ALLOW_LIST = [
        // Das Altsystem-Modul wird abgelöst, nicht migriert; seine Abfragen
        // laufen gegen die Legacy-Verbindung mit eigenem Schema/Indexbild.
        'app/Legacy/' => 'Altsystem-Modul: wird abgelöst, nicht umgestellt (A8).',
    ];

    /**
     * Verbliebene `whereDate()`-Stellen je Datei (Stand MVP-722).
     *
     * @var array<string, int>
     */
    private const BASELINE = [
        'app/Console/Commands/Billing/ReapplyOpenRatesCommand.php' => 2,
        'app/Console/Commands/Billing/ReopenTimesCommand.php' => 2,
        'app/Console/Commands/Finance/CheckFilingObligationsCommand.php' => 1,
        'app/Console/Commands/Finance/OpenTimesDigestCommand.php' => 1,
        'app/Console/Commands/RecalculateTimeRules.php' => 2,
        'app/Console/Commands/TimeExport/ImportExternalWageItemsCommand.php' => 1,
        'app/Dashboard/Widgets/DataProtectionWidget.php' => 1,
        'app/Http/Controllers/AdminTimeEntryController.php' => 1,
        'app/Http/Controllers/Api/AbsenceController.php' => 4,
        'app/Http/Controllers/Api/DiaryController.php' => 2,
        'app/Http/Controllers/Api/EmergencyAssignmentController.php' => 2,
        'app/Http/Controllers/Api/ExpenseApiController.php' => 2,
        'app/Http/Controllers/Api/InvoiceApiController.php' => 2,
        'app/Http/Controllers/Api/OnCallShiftController.php' => 2,
        'app/Http/Controllers/Api/ProtocolApiController.php' => 2,
        'app/Http/Controllers/Api/PurchaseOrderApiController.php' => 2,
        'app/Http/Controllers/Api/ScheduledShiftApiController.php' => 2,
        'app/Http/Controllers/AssetFinance/AssetFinanceContractController.php' => 1,
        'app/Http/Controllers/AttendanceController.php' => 2,
        'app/Http/Controllers/Contract/ContractController.php' => 1,
        'app/Http/Controllers/Domain/DomainAccountingController.php' => 2,
        'app/Http/Controllers/DutyController.php' => 4,
        'app/Http/Controllers/Finance/DunningRunController.php' => 1,
        'app/Http/Controllers/Finance/FinanceTransferController.php' => 2,
        'app/Http/Controllers/Finance/JournalController.php' => 2,
        'app/Http/Controllers/Finance/OpenTimesController.php' => 4,
        'app/Http/Controllers/GuaranteeController.php' => 1,
        'app/Http/Controllers/MeterBillingController.php' => 1,
        'app/Http/Controllers/Passenger/PassengerSettlementController.php' => 1,
        'app/Http/Controllers/PresenceBoardController.php' => 5,
        'app/Http/Controllers/QuoteFollowUpController.php' => 5,
        'app/Http/Controllers/Reporting/AbsenceCalendarReportController.php' => 4,
        'app/Http/Controllers/Reporting/LogbookReportController.php' => 2,
        'app/Http/Controllers/Reporting/TimeAccountComparisonReportController.php' => 3,
        'app/Http/Controllers/Safety/SafetyInstructionController.php' => 1,
        'app/Http/Controllers/TourController.php' => 5,
        'app/Http/Controllers/WarrantyPeriodController.php' => 2,
        'app/Models/Accounting/AccountingPeriod.php' => 2,
        'app/Models/Accounting/AccountingPostingRule.php' => 2,
        'app/Models/Accounting/AccountingRecurringRun.php' => 1,
        'app/Models/Accounting/AccountingTaxCode.php' => 2,
        'app/Models/AssetBlock.php' => 1,
        'app/Models/AssetCompliance/AssetComplianceNormReference.php' => 2,
        'app/Models/DesiredShift.php' => 1,
        'app/Models/Document.php' => 4,
        'app/Models/FormTemplate.php' => 2,
        'app/Models/Isms/IsmsCorrectiveAction.php' => 1,
        'app/Models/Isms/IsmsNormStatus.php' => 2,
        'app/Models/Isms/IsmsRisk.php' => 1,
        'app/Models/Isms/IsmsSoftwareProduct.php' => 1,
        'app/Models/Isms/IsmsSupplierAssessment.php' => 1,
        'app/Models/Isms/IsmsVulnerability.php' => 1,
        'app/Models/PerDiemRate.php' => 2,
        'app/Models/ReportTarget.php' => 2,
        'app/Models/Safety/HazardAssessment.php' => 1,
        'app/Models/Safety/MedicalCheckup.php' => 1,
        'app/Models/ScheduledShift.php' => 1,
        'app/Models/Tour.php' => 1,
        'app/Plugins/Support/AbstractTimeEntryPushService.php' => 2,
        'app/Services/Accounting/AccountingProfileService.php' => 3,
        'app/Services/Accounting/AccountingSovereigntyResolver.php' => 2,
        'app/Services/Accounting/Filing/FilingObligationService.php' => 3,
        'app/Services/Accounting/Filing/RecapitulativeStatementService.php' => 2,
        'app/Services/Accounting/Filing/VatFieldBreakdownService.php' => 2,
        'app/Services/Accounting/FiscalYearService.php' => 2,
        'app/Services/Accounting/LedgerDatevExportService.php' => 4,
        'app/Services/Accounting/PeriodClosingService.php' => 3,
        'app/Services/Accounting/Posting/Adapters/CashEntryAdapter.php' => 2,
        'app/Services/Accounting/Posting/Adapters/DepreciationAdapter.php' => 3,
        'app/Services/Accounting/Posting/Adapters/ExpenseAdapter.php' => 2,
        'app/Services/Accounting/Posting/Adapters/IncomingInvoiceAdapter.php' => 2,
        'app/Services/Accounting/Posting/Adapters/PaymentAdapter.php' => 2,
        'app/Services/Accounting/Posting/Adapters/SalesInvoiceAdapter.php' => 2,
        'app/Services/Accounting/RecurringAccountingService.php' => 1,
        'app/Services/Accounting/VatFilingProfileResolver.php' => 5,
        'app/Services/Agile/AgileCapacityService.php' => 4,
        'app/Services/Archive/ArchiveSummaryService.php' => 4,
        'app/Services/Billing/ExcelHistoryImporter.php' => 1,
        'app/Services/Communication/CommunicationNoteService.php' => 1,
        'app/Services/Compliance/Rules/MaxDailyHoursRule.php' => 1,
        'app/Services/Dispatch/DispatchConflictChecker.php' => 2,
        'app/Services/Dispatch/GapFillSuggester.php' => 1,
        'app/Services/Event/CertificateService.php' => 1,
        'app/Services/Export/Specs/ScheduledShiftExportSpec.php' => 2,
        'app/Services/Export/Specs/TourExportSpec.php' => 2,
        'app/Services/Finance/CashBookService.php' => 1,
        'app/Services/Flextime/FlexCalculator.php' => 4,
        'app/Services/Import/Specs/ScheduledShiftSpec.php' => 1,
        'app/Services/Invoicing/RecurringInvoiceService.php' => 2,
        'app/Services/Invoicing/RetentionService.php' => 1,
        'app/Services/Invoicing/TaxResolver.php' => 4,
        'app/Services/Isms/ConformityService.php' => 2,
        'app/Services/Manufacturing/CapacityService.php' => 1,
        'app/Services/Metering/MeterBillingService.php' => 2,
        'app/Services/Notification/DeadlineScans/DriverLicenseCheckScan.php' => 1,
        'app/Services/Notification/DeadlineScans/GuaranteeDeadlineScan.php' => 1,
        'app/Services/Notification/DeadlineScans/IsmsDeadlineScans.php' => 4,
        'app/Services/Notification/DeadlineScans/QualificationExpiryScan.php' => 2,
        'app/Services/Notification/DeadlineScans/QuoteFollowUpScan.php' => 3,
        'app/Services/Notification/DeadlineScans/RetentionReleaseScan.php' => 3,
        'app/Services/Notification/DeadlineScans/SafetyDeadlineScans.php' => 3,
        'app/Services/Notification/DeadlineScans/SupplierCredentialScan.php' => 3,
        'app/Services/Notification/DeadlineScans/WarrantyPeriodScan.php' => 2,
        'app/Services/Org/UserOffboardingService.php' => 1,
        'app/Services/Payroll/EurostatMinimumWageImporter.php' => 1,
        'app/Services/Payroll/MinimumWageService.php' => 1,
        'app/Services/Print/PrintOrderService.php' => 1,
        'app/Services/Privacy/ComplianceAnalysisService.php' => 2,
        'app/Services/Privacy/Retention/RetentionRegistrations.php' => 2,
        'app/Services/Procedure/ProcedureTemplateService.php' => 2,
        'app/Services/Recurrence/RecurrenceGenerator.php' => 1,
        'app/Services/Reporting/PlanIstReportBuilder.php' => 4,
        'app/Services/Reporting/WorkBalanceCalculator.php' => 2,
        'app/Services/Schedule/ShiftRotationRoller.php' => 4,
        'app/Services/Schedule/StaffingSuggester.php' => 1,
        'app/Services/Search/GlobalSearchService.php' => 2,
        'app/Services/Surcharge/TimeRuleEngine.php' => 2,
        'app/Services/Sustainability/EmissionCalculationService.php' => 4,
        'app/Services/TimeApproval/DayCloseService.php' => 3,
        'app/Services/TimeApproval/MonthTotalsSnapshotter.php' => 2,
        'app/Services/TimeApproval/OvertimeRequestService.php' => 2,
        'app/Services/TimeExport/TimeExportService.php' => 2,
        'app/Services/Travel/TravelChargeService.php' => 4,
        'app/Services/Travel/TravelLogService.php' => 1,
        'app/Services/Users/DeputyResolver.php' => 4,
        'app/Services/Weather/WeatherService.php' => 1,
    ];

    public function test_no_new_where_date_usages(): void {
        $violations = [];

        foreach ($this->whereDateCounts() as $file => $count) {
            $allowed = self::BASELINE[$file] ?? 0;
            if ($count > $allowed) {
                $violations[] = sprintf('%s: %d (erlaubt: %d)', $file, $count, $allowed);
            }
        }

        sort($violations);

        $this->assertSame([], $violations, "Neue oder zusätzliche whereDate()-Stellen gefunden.\n"
            . "Zeitraumfilter über App\\Support\\Query\\DateRange führen:\n"
            . "  DATE-Spalte      → DateRange::whereDateBetween(\$q, 'booked_on', \$from, \$to)\n"
            . "                     bzw. ->whereBetween('booked_on', DateRange::days(\$from, \$to))\n"
            . "  DATETIME-Spalte  → DateRange::whereTimestampBetween(\$q, 'start_at', \$from, \$to)\n"
            . "                     bzw. ->where('start_at', '<', DateRange::dayAfter(\$to))\n\n"
            . implode("\n", $violations));
    }

    public function test_baseline_is_not_stale(): void {
        $actual = $this->whereDateCounts();
        $stale = [];

        foreach (self::BASELINE as $file => $count) {
            $now = $actual[$file] ?? 0;
            if ($now < $count) {
                $stale[] = sprintf('%s: %d statt %d', $file, $now, $count);
            }
        }

        sort($stale);

        $this->assertSame([], $stale, "Die BASELINE ist zu hoch — abgeräumte Stellen hier nachtragen\n"
            . "(die Liste soll monoton fallen; Einträge mit 0 ganz entfernen):\n\n"
            . implode("\n", $stale));
    }

    /**
     * @return array<string, int> repo-relativer Pfad → Anzahl
     */
    private function whereDateCounts(): array {
        $counts = [];

        foreach ($this->phpFiles('app') as $file) {
            $relative = $this->relativePath($file);
            if ($this->isAllowListed($relative, self::ALLOW_LIST)) {
                continue;
            }

            $source = $this->stripComments((string) file_get_contents($file));
            $matches = preg_match_all('/->(?:or)?[wW]hereDate\s*\(/', $source);
            if ($matches > 0) {
                $counts[$relative] = $matches;
            }
        }

        return $counts;
    }
}
