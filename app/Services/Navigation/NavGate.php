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
        'customers.*' => \App\Models\Customer::class,
        'customer-queries.*' => \App\Models\CustomerQuery::class,
        'suppliers.*' => \App\Models\Supplier::class,
        'projects.*' => \App\Models\Project::class,
        'invoices.*' => \App\Models\Invoice::class,
        'events.*' => \App\Models\Event::class,
        'event-categories.*' => \App\Models\EventCategory::class,
        'materials.*' => \App\Models\Material::class,
        'vehicles.*' => \App\Models\Vehicle::class,
        'assets.*' => \App\Models\Asset::class,
        'energy-logs.*' => \App\Models\EnergyLog::class,
        'sites.*' => \App\Models\Site::class,
        'geofences.*' => \App\Models\Location\CustomerGeofence::class,
        'buildings.*' => \App\Models\Building::class,
        'floors.*' => \App\Models\Floor::class,
        'rooms.*' => \App\Models\Room::class,
        'duty-plans.*' => \App\Models\DutyPlan::class,
        'shift-types.*' => \App\Models\ShiftType::class,
        'schedule.availability.*' => \App\Models\AvailabilityWindow::class,
        'schedule.exchanges.*' => \App\Models\ShiftExchange::class,
        'timesheets.*' => \App\Models\Timesheet::class,
        'day-close.*' => \App\Models\DayClosure::class,
        'travel-logs.*' => \App\Models\TravelLog::class,
        'expenses.*' => \App\Models\Expense::class,
        'per-diem-trips.*' => \App\Models\PerDiemTrip::class,
        'tours.*' => \App\Models\Tour::class,
        'teams.*' => \App\Models\Team::class,
        'qualifications.*' => \App\Models\Qualification::class,
        'documents.*' => \App\Models\Document::class,
        'knowledge.*' => \App\Models\KnowledgeArticle::class,
        'ideas.*' => \App\Models\IdeaMap::class,
        'form-templates.*' => \App\Models\FormTemplate::class,
        'form-submissions.*' => \App\Models\FormSubmission::class,
        'procedures.*' => \App\Models\ProcedureTemplate::class,
        'procedure-runs.*' => \App\Models\ProcedureRun::class,
        'isms.dashboard' => \App\Models\Isms\IsmsRisk::class,
        'isms.readiness' => \App\Models\Isms\IsmsRisk::class,
        'isms.suppliers.*' => \App\Models\Isms\IsmsSupplierAssessment::class,
        'isms.risks.*' => \App\Models\Isms\IsmsRisk::class,
        'isms.incidents.*' => \App\Models\Isms\IsmsSecurityIncident::class,
        'isms.vulnerabilities.*' => \App\Models\Isms\IsmsVulnerability::class,
        'isms.advisories.*' => \App\Models\Isms\IsmsAdvisory::class,
        'finance.reconciliation.*' => \App\Models\Finance\BankStatement::class,
        'finance.bank-accounts.*' => \App\Models\Finance\BankAccount::class,
        'finance.datev.*' => \App\Models\Finance\DatevBookingBatch::class,
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
