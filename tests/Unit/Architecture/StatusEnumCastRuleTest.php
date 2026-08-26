<?php
/*
 * Created on   : Sun Aug 23 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : StatusEnumCastRuleTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace Tests\Unit\Architecture;

use PHPUnit\Framework\TestCase;
use Tests\Unit\Architecture\Concerns\ScansSourceTree;

/**
 * Architektur-Gate „status-Spalte ⇒ Enum-Cast" (Vollscan 2026-08-23, F9):
 * 80 Tabellen führten status/state als freien String (Quote mit ~45 rohen
 * Literalen), obwohl app/Enums 400+ Enums hat; varchar-Längen von 10 bis 64
 * brechen unter MariaDB-Strict beim ersten längeren Wert.
 *
 * Regel: Ein Modell, dessen Tabelle eine Spalte `status` oder `state` hat,
 * castet sie auf eine App\Enums-Klasse. Der Bestand steht als BASELINE (Welle 3,
 * F9 — Quote/Invoice/Problem/Change/ServiceRequest zuerst); neue Modelle
 * dürfen nicht dazukommen, abgearbeitete werden hier gestrichen.
 */
class StatusEnumCastRuleTest extends TestCase {
    use ScansSourceTree;

    /** @var list<string> Modellklassen ohne Enum-Cast (Stand 2026-08-23) */
    private const BASELINE = [
        'App\Models\Agile\AgileSprint',
        'App\Models\Ai\AiTextSuggestion',
        'App\Models\Applications\ApplicationContractNegotiation',
        'App\Models\Applications\ApplicationContractReview',
        'App\Models\Applications\ApplicationOpportunity',
        'App\Models\Applications\ApplicationRequirement',
        'App\Models\Applications\EmployeeDraft',
        'App\Models\Applications\JobApplication',
        'App\Models\Applications\JobApplicationInterview',
        'App\Models\Applications\JobPosting',
        'App\Models\Applications\JobRequisition',
        'App\Models\AppointmentRequest',
        'App\Models\AssetComponent',
        'App\Models\AssetFinance\AssetFinanceDeadline',
        'App\Models\AssetFinance\AssetFinanceEndProcess',
        'App\Models\AssetFinance\AssetFinanceRateSchedule',
        'App\Models\B2b\B2bOrder',
        'App\Models\BillbeeOrder',
        'App\Models\CalendlyConnection',
        'App\Models\CalendlyWebhookSubscription',
        'App\Models\Change',
        'App\Models\Claims\ClaimAssessment',
        'App\Models\Communication\CustomerCircular',
        'App\Models\Communication\CustomerCircularRecipient',
        'App\Models\Contract\ContractObligation',
        'App\Models\Crisis\CrisisAction',
        'App\Models\Crisis\CrisisCase',
        'App\Models\Crisis\CrisisCommunication',
        'App\Models\Crisis\CrisisContinuityImpact',
        'App\Models\DocumentDesign\DocumentRenderProfileVersion',
        'App\Models\Domain\DomainEvent',
        'App\Models\Domain\DomainExternalInvoice',
        'App\Models\Domain\DomainProjection',
        'App\Models\EtsyConnection',
        'App\Models\EtsyReceipt',
        'App\Models\GoogleCalendarConnection',
        'App\Models\IncomingEInvoice',
        'App\Models\IntegrationInboxItem',
        'App\Models\Investments\InvestmentBudgetRequest',
        'App\Models\Investments\InvestmentCase',
        'App\Models\Investments\InvestmentDeviation',
        'App\Models\Invoice',
        'App\Models\DocumentDispatch',
        'App\Models\InvoiceSchedule',
        'App\Models\Isms\IsmsAuditProgram',
        'App\Models\JtlConnection',
        'App\Models\Location\LocationPendingEntry',
        'App\Models\Location\LocationVisit',
        'App\Models\MaintenanceWindow',
        'App\Models\Metering\MeterBillingAgreement',
        'App\Models\Migration\AccountingMigrationItem',
        'App\Models\MsgraphConnection',
        'App\Models\MsgraphContactConnection',
        'App\Models\MsgraphMailConnection',
        'App\Models\MsgraphTaskConnection',
        'App\Models\MsgraphTaskListLink',
        'App\Models\OnboardingProgress',
        'App\Models\OrgaMaxConnection',
        'App\Models\Passenger\PassengerShiftSettlement',
        'App\Models\Patrol\PatrolRun',
        'App\Models\PendingExternalConflict',
        'App\Models\PriceChangeRequest',
        'App\Models\PricingChangeAlert',
        'App\Models\Privacy\ComplianceFinding',
        'App\Models\Privacy\DpiaStep',
        'App\Models\Privacy\Measure',
        'App\Models\Privacy\RetentionProposal',
        'App\Models\Problem',
        'App\Models\Quote',
        'App\Models\RemotePendingSession',
        'App\Models\Rental\RentalCaseAsset',
        'App\Models\Rental\RentalConditionItem',
        'App\Models\Rental\RentalReservation',
        'App\Models\ScheduledJobRun',
        'App\Models\ServiceRequest',
        'App\Models\SharepointConnection',
        'App\Models\StockLot',
        'App\Models\SupplierCatalogImport',
        'App\Models\Survey\SurveyInvitation',
        'App\Models\Sustainability\SustainabilityAssessment',
        'App\Models\Sustainability\SustainabilityMeasure',
        'App\Models\TaxRule',
        'App\Models\Tenders\TenderNoticeMatch',
        'App\Models\TodoistConnection',
        'App\Models\TodoistProjectLink',
    ];

    public function test_status_columns_are_cast_to_enums(): void {
        $tables = $this->schemaTables();
        $violations = [];
        $resolved = [];

        foreach ($this->modelClasses() as $class) {
            $table = $this->tableOfModel($class);
            if ($table === '' || ! isset($tables[$table])) {
                continue;
            }

            $model = new $class();
            $casts = $model->getCasts();
            foreach (['status', 'state'] as $column) {
                if (! isset($tables[$table]['columns'][$column])) {
                    continue;
                }
                $cast = (string) ($casts[$column] ?? '');
                if (str_starts_with($cast, 'App\\Enums\\')) {
                    $resolved[] = $class;
                    continue;
                }
                if (! in_array($class, self::BASELINE, true)) {
                    $violations[] = sprintf('%s — %s.%s ohne Enum-Cast', $class, $table, $column);
                }
            }
        }

        sort($violations);
        $this->assertSame([], $violations, "status-/state-Spalte ohne Enum-Cast (Memory: Enum-Cast nie gegen ->value vergleichen; MariaDB-Strict).\n"
            . "Enum unter app/Enums anlegen und casten.\n\n" . implode("\n", $violations));

        $stale = array_values(array_intersect(self::BASELINE, $resolved));
        $this->assertSame([], $stale, "Aus der BASELINE streichen (inzwischen gecastet):\n" . implode("\n", $stale));
    }
}
