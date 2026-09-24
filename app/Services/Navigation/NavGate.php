<?php
/*
 * Created on   : Tue Jun 09 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : NavGate.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Navigation;

use App\Services\Licensing\FeatureFlagResolver;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;

/**
 * Sichtbarkeit von Navigationseinträgen = Plan-Gating UND Rechte. Eine Route ist
 * im Menü sichtbar, wenn (a) ihr Modul im Plan/Lizenz enthalten ist
 * ({@see FeatureFlagResolver::routeEnabled()}) und (b) der Nutzer die zugehörige
 * `viewAny`-Policy erfüllt. `viewAny` wird bewusst als universeller Check genutzt
 * (in allen gemappten Modellen vorhanden) – die echte Aktion autorisiert der
 * Controller weiterhin selbst.
 */
class NavGate {
    /**
     * Route-Namen-Muster → Policy-Modell (nur Modelle MIT viewAny-Policy, sonst
     * würde Gate::allows fälschlich verbergen). Nicht gelistete Routen sind aus
     * Rechte-Sicht immer sichtbar (Plan-Gating greift separat).
     *
     * @var array<string, class-string>
     */
    private const MODELS = [
        'collections.*' => \App\Models\Knowledge\ContentCollection::class,
        'sustainability.*' => \App\Models\Sustainability\SustainabilityAssessment::class,
        'crisis.*' => \App\Models\Crisis\CrisisCase::class,
        'claims.*' => \App\Models\Claims\ClaimCase::class,
        'passenger-rides.*' => \App\Models\Passenger\PassengerRide::class,
        'passenger-masterdata.*' => \App\Models\Passenger\PassengerFareTariff::class,
        'passenger-settlements.*' => \App\Models\Passenger\PassengerShiftSettlement::class,
        'print-orders.*' => \App\Models\Print\PrintOrder::class,
        'rental.*' => \App\Models\Rental\RentalCase::class,
        'asset-finance.*' => \App\Models\AssetFinance\AssetFinanceContract::class,
        'asset-compliance.*' => \App\Models\AssetCompliance\AssetComplianceProfile::class,
        'investments.*' => \App\Models\Investments\InvestmentCase::class,
        'tenders.*' => \App\Models\Applications\ApplicationOpportunity::class,
        // Nur die Liste: die Schnellerfassung hängt an communication.create, nicht an viewAny.
        'communication-notes.index' => \App\Models\Communication\CommunicationNote::class,
        'recruiting.requisitions.*' => \App\Models\Applications\JobRequisition::class,
        'recruiting.applications.*' => \App\Models\Applications\JobApplication::class,
        'customers.*' => \App\Models\Customer\Customer::class,
        'customer-queries.*' => \App\Models\Customer\CustomerQuery::class,
        'suppliers.*' => \App\Models\Supplier\Supplier::class,
        'projects.*' => \App\Models\Project\Project::class,
        'invoices.*' => \App\Models\Invoicing\Invoice::class,
        'events.*' => \App\Models\Calendar\Event::class,
        'event-categories.*' => \App\Models\Calendar\EventCategory::class,
        'materials.*' => \App\Models\Material\Material::class,
        'vehicles.*' => \App\Models\Fleet\Vehicle::class,
        'assets.*' => \App\Models\Asset\Asset::class,
        'energy-logs.*' => \App\Models\Asset\EnergyLog::class,
        'sites.*' => \App\Models\Facility\Site::class,
        'geofences.*' => \App\Models\Location\CustomerGeofence::class,
        'buildings.*' => \App\Models\Facility\Building::class,
        'floors.*' => \App\Models\Facility\Floor::class,
        'rooms.*' => \App\Models\Facility\Room::class,
        'duty-plans.*' => \App\Models\Schedule\DutyPlan::class,
        'shift-types.*' => \App\Models\Schedule\ShiftType::class,
        'schedule.availability.*' => \App\Models\Calendar\AvailabilityWindow::class,
        'schedule.exchanges.*' => \App\Models\Schedule\ShiftExchange::class,
        'timesheets.*' => \App\Models\Time\Timesheet::class,
        'day-close.*' => \App\Models\Diary\DayClosure::class,
        'travel-logs.*' => \App\Models\Travel\TravelLog::class,
        'expenses.*' => \App\Models\Travel\Expense::class,
        'per-diem-trips.*' => \App\Models\Travel\PerDiemTrip::class,
        'tours.*' => \App\Models\Diary\Tour::class,
        'teams.*' => \App\Models\Platform\Team::class,
        'qualifications.*' => \App\Models\Hr\Qualification::class,
        'documents.*' => \App\Models\Document\Document::class,
        'knowledge.*' => \App\Models\Knowledge\KnowledgeArticle::class,
        'ideas.*' => \App\Models\Ideas\IdeaMap::class,
        'form-templates.*' => \App\Models\Form\FormTemplate::class,
        'form-submissions.*' => \App\Models\Form\FormSubmission::class,
        'procedures.*' => \App\Models\Procedure\ProcedureTemplate::class,
        'procedure-runs.*' => \App\Models\Procedure\ProcedureRun::class,
        'isms.dashboard' => \App\Models\Isms\IsmsRisk::class,
        'isms.readiness' => \App\Models\Isms\IsmsRisk::class,
        'isms.csf' => \App\Models\Isms\IsmsRequirement::class,
        'isms.csf.*' => \App\Models\Isms\IsmsRequirement::class,
        'isms.suppliers.*' => \App\Models\Isms\IsmsSupplierAssessment::class,
        'isms.risks.*' => \App\Models\Isms\IsmsRisk::class,
        'isms.incidents.*' => \App\Models\Isms\IsmsSecurityIncident::class,
        'isms.vulnerabilities.*' => \App\Models\Isms\IsmsVulnerability::class,
        'isms.advisories.*' => \App\Models\Isms\IsmsAdvisory::class,
        'finance.reconciliation.*' => \App\Models\Finance\BankStatement::class,
        'finance.bank-accounts.*' => \App\Models\Finance\BankAccount::class,
        'finance.datev.*' => \App\Models\Finance\DatevBookingBatch::class,
        'finance.gobd.*' => \App\Models\Audit\GobdExport::class,
        'finance.procedure-documentation.*' => \App\Models\Finance\ProcedureDocumentation::class,
        'finance.*' => \App\Models\Finance\BillingTransfer::class,
        'isms.controls.*' => \App\Models\Isms\IsmsControl::class,
        'isms.requirements.*' => \App\Models\Isms\IsmsRequirement::class,
        'isms.statements.*' => \App\Models\Isms\IsmsRequirement::class,
        'isms.scopes.*' => \App\Models\Isms\IsmsScope::class,
        'isms.soa' => \App\Models\Isms\IsmsRequirement::class,
        'isms.software.*' => \App\Models\Isms\IsmsSoftwareProduct::class,
        'isms.conformity.*' => \App\Models\Isms\IsmsNormStatus::class,
        'isms.audits.*' => \App\Models\Isms\IsmsAudit::class,
        'isms.reviews.*' => \App\Models\Isms\IsmsManagementReview::class,
        'isms.packages.*' => \App\Models\Isms\IsmsAuditPackage::class,
    ];

    public function __construct(private readonly FeatureFlagResolver $features) {}

    /** Plan UND Recht: nur dann im Menü zeigen. */
    public function allows(?string $routeName): bool {
        return $this->features->routeEnabled($routeName) && $this->mayAccess($routeName);
    }

    /** Nur die Rechte-Ebene (viewAny der gemappten Policy). */
    public function mayAccess(?string $routeName): bool {
        if ($routeName === null) {
            return true;
        }
        $model = $this->modelForRoute($routeName);

        return $model === null || Gate::allows('viewAny', $model);
    }

    private function modelForRoute(string $routeName): ?string {
        foreach (self::MODELS as $pattern => $model) {
            if (Str::is($pattern, $routeName)) {
                return $model;
            }
        }

        return null;
    }
}
