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
        'buildings.*' => \App\Models\Building::class,
        'floors.*' => \App\Models\Floor::class,
        'rooms.*' => \App\Models\Room::class,
        'duty-plans.*' => \App\Models\DutyPlan::class,
        'shift-types.*' => \App\Models\ShiftType::class,
        'timesheets.*' => \App\Models\Timesheet::class,
        'travel-logs.*' => \App\Models\TravelLog::class,
        'expenses.*' => \App\Models\Expense::class,
        'per-diem-trips.*' => \App\Models\PerDiemTrip::class,
        'tours.*' => \App\Models\Tour::class,
        'teams.*' => \App\Models\Team::class,
        'qualifications.*' => \App\Models\Qualification::class,
        'documents.*' => \App\Models\Document::class,
        'knowledge.*' => \App\Models\KnowledgeArticle::class,
        'form-templates.*' => \App\Models\FormTemplate::class,
        'form-submissions.*' => \App\Models\FormSubmission::class,
        'isms.risks.*' => \App\Models\Isms\IsmsRisk::class,
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
