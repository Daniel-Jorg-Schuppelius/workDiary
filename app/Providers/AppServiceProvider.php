<?php

/*
 * Created on   : Wed Apr 29 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : AppServiceProvider.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Providers;

use App\Auth\CustomerUserProvider;
use App\Automation\Actions\ApproveExpenseAction;
use App\Automation\ConditionEvaluator;
use App\Automation\RuleEngine;
use App\Legacy\Auth\LegacyUserProvider;
use App\Listeners\AuthEventSubscriber;
use App\Models\ActivityCategory;
use App\Models\Asset;
use App\Models\Attachment;
use App\Models\Classification;
use App\Models\ClassificationRequirement;
use App\Models\Comment;
use App\Models\CoverageRequirement;
use App\Models\Customer;
use App\Models\DiaryEntry;
use App\Models\DutyPlan;
use App\Models\EmergencyAssignment;
use App\Models\Event;
use App\Models\EventCategory;
use App\Models\Expense;
use App\Models\ExpenseCategory;
use App\Models\FlexEligibility;
use App\Models\Material;
use App\Models\MaterialUsage;
use App\Models\Milestone;
use App\Models\OpenIssue;
use App\Models\Organization;
use App\Models\PerDiemRate;
use App\Models\PerDiemTrip;
use App\Models\ProcedureBackupProof;
use App\Models\ProcedureDeviation;
use App\Models\ProcedureRun;
use App\Models\ProcedureTemplate;
use App\Models\Protocol;
use App\Models\Qualification;
use App\Models\Room;
use App\Models\ScheduledShift;
use App\Models\ShiftType;
use App\Models\Tag;
use App\Models\Task;
use App\Models\TimeEntry;
use App\Models\Timesheet;
use App\Models\TravelLog;
use App\Models\User;
use App\Models\UserGroup;
use App\Models\WorkSchedule;
use App\Observers\AttachmentObserver;
use App\Observers\CommentObserver;
use App\Observers\CustomerObserver;
use App\Observers\DiaryEntryObserver;
use App\Observers\EmergencyAssignmentObserver;
use App\Observers\MaterialUsageObserver;
use App\Observers\OrganizationObserver;
use App\Observers\TagObserver;
use App\Observers\TimeEntryObserver;
use App\Observers\TimesheetObserver;
use App\Observers\UserObserver;
use App\Policies\ActivityCategoryPolicy;
use App\Policies\AssetPolicy;
use App\Policies\ClassificationPolicy;
use App\Policies\ClassificationRequirementPolicy;
use App\Policies\CoverageRequirementPolicy;
use App\Policies\DutyPlanPolicy;
use App\Policies\EventCategoryPolicy;
use App\Policies\EventPolicy;
use App\Policies\ExpenseCategoryPolicy;
use App\Policies\ExpensePolicy;
use App\Policies\FlexEligibilityPolicy;
use App\Policies\MaterialPolicy;
use App\Policies\MaterialUsagePolicy;
use App\Policies\MilestonePolicy;
use App\Policies\OpenIssuePolicy;
use App\Policies\OrganizationPolicy;
use App\Policies\PerDiemRatePolicy;
use App\Policies\PerDiemTripPolicy;
use App\Policies\ProcedureBackupProofPolicy;
use App\Policies\ProcedureDeviationPolicy;
use App\Policies\ProcedureRunPolicy;
use App\Policies\ProcedureTemplatePolicy;
use App\Policies\ProtocolPolicy;
use App\Policies\QualificationPolicy;
use App\Policies\RoomPolicy;
use App\Policies\ScheduledShiftPolicy;
use App\Policies\ShiftTypePolicy;
use App\Policies\TaskPolicy;
use App\Policies\TimeEntryPolicy;
use App\Policies\TimesheetPolicy;
use App\Policies\TravelLogPolicy;
use App\Policies\UserGroupPolicy;
use App\Policies\WorkSchedulePolicy;
use App\Services\Attendance\AttendanceClockService;
use App\Services\BrandingService;
use App\Services\Reminders\ReminderService;
use App\Services\Routing\NominatimGeocoder;
use App\Services\Routing\OsrmRouter;
use App\Services\Timesheet\Stopwatch;
use App\Services\UI\DateRangeContext;
use Carbon\CarbonImmutable;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Event as EventFacade;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;

class AppServiceProvider extends ServiceProvider {
    public function register(): void {
        $this->app->singleton(NominatimGeocoder::class, function ($app): NominatimGeocoder {
            /** @var array<string, mixed> $cfg */
            $cfg = (array) $app['config']->get('routing.nominatim', []);

            return new NominatimGeocoder($cfg);
        });

        $this->app->singleton(OsrmRouter::class, function ($app): OsrmRouter {
            /** @var array<string, mixed> $cfg */
            $cfg = (array) $app['config']->get('routing.osrm', []);

            return new OsrmRouter($cfg);
        });

        // BrandingService cached die Organisation pro Request → einmalig
        // pro Container-Lifecycle resolven.
        $this->app->singleton(BrandingService::class);

        // Automation: RuleEngine bekommt alle registrierten Aktionen injiziert.
        $this->app->singleton(ConditionEvaluator::class);
        $this->app->singleton(RuleEngine::class, function ($app): RuleEngine {
            return new RuleEngine(
                $app->make(ConditionEvaluator::class),
                [
                    $app->make(ApproveExpenseAction::class),
                ],
            );
        });
    }

    public function boot(): void {
        Auth::provider('legacy', function ($app) {
            return new LegacyUserProvider($app['hash']);
        });
        Auth::provider('customer-eloquent', function ($app) {
            return new CustomerUserProvider($app['hash']);
        });

        EventFacade::subscribe(AuthEventSubscriber::class);

        Comment::observe(CommentObserver::class);
        Attachment::observe(AttachmentObserver::class);
        Customer::observe(CustomerObserver::class);
        EmergencyAssignment::observe(EmergencyAssignmentObserver::class);
        DiaryEntry::observe(DiaryEntryObserver::class);
        Tag::observe(TagObserver::class);
        User::observe(UserObserver::class);
        TimeEntry::observe(TimeEntryObserver::class);
        Timesheet::observe(TimesheetObserver::class);
        MaterialUsage::observe(MaterialUsageObserver::class);
        Organization::observe(OrganizationObserver::class);

        Gate::policy(DutyPlan::class, DutyPlanPolicy::class);
        Gate::policy(CoverageRequirement::class, CoverageRequirementPolicy::class);
        Gate::policy(Milestone::class, MilestonePolicy::class);
        Gate::policy(Qualification::class, QualificationPolicy::class);
        Gate::policy(ScheduledShift::class, ScheduledShiftPolicy::class);
        Gate::policy(ShiftType::class, ShiftTypePolicy::class);
        Gate::policy(Task::class, TaskPolicy::class);
        Gate::policy(TimeEntry::class, TimeEntryPolicy::class);
        Gate::policy(Timesheet::class, TimesheetPolicy::class);
        Gate::policy(Material::class, MaterialPolicy::class);
        Gate::policy(Asset::class, AssetPolicy::class);
        Gate::policy(MaterialUsage::class, MaterialUsagePolicy::class);
        Gate::policy(WorkSchedule::class, WorkSchedulePolicy::class);
        Gate::policy(ActivityCategory::class, ActivityCategoryPolicy::class);
        Gate::policy(TravelLog::class, TravelLogPolicy::class);
        Gate::policy(UserGroup::class, UserGroupPolicy::class);
        Gate::policy(FlexEligibility::class, FlexEligibilityPolicy::class);
        Gate::policy(Event::class, EventPolicy::class);
        Gate::policy(EventCategory::class, EventCategoryPolicy::class);
        Gate::policy(Expense::class, ExpensePolicy::class);
        Gate::policy(ExpenseCategory::class, ExpenseCategoryPolicy::class);
        Gate::policy(PerDiemTrip::class, PerDiemTripPolicy::class);
        Gate::policy(PerDiemRate::class, PerDiemRatePolicy::class);
        Gate::policy(Room::class, RoomPolicy::class);
        Gate::policy(OpenIssue::class, OpenIssuePolicy::class);
        Gate::policy(Protocol::class, ProtocolPolicy::class);
        Gate::policy(ProcedureTemplate::class, ProcedureTemplatePolicy::class);
        Gate::policy(ProcedureRun::class, ProcedureRunPolicy::class);
        Gate::policy(ProcedureBackupProof::class, ProcedureBackupProofPolicy::class);
        Gate::policy(ProcedureDeviation::class, ProcedureDeviationPolicy::class);
        Gate::policy(Classification::class, ClassificationPolicy::class);
        Gate::policy(ClassificationRequirement::class, ClassificationRequirementPolicy::class);

        // manage-members: Org-Admin darf Mitglieder der eigenen Org verwalten
        Gate::define('manage-members', [OrganizationPolicy::class, 'manageMembers']);

        // manage-access: Verwaltung des Rechte-Bereichs (Rollen, Gruppen,
        // Zuweisungen). Erfordert die feingranulare Permission access.manage —
        // damit auch Nicht-Org-Admins (z. B. dedizierte Rechte-Verwalter)
        // adressierbar sind. Globale Plattform-Admins kommen über den
        // Spatie-Permission-Check ebenfalls hier durch, sofern sie die
        // Permission via PermissionsSeeder erhalten haben.
        Gate::define('manage-access', static function (User $user): bool {
            return $user->isAdmin() || $user->hasEffectivePermission('access.manage');
        });

        // Sekundärer Gate::before-Hook: berücksichtigt zusätzlich Permissions,
        // die ein Nutzer via Gruppen-Mitgliedschaft erbt. Spatie's eigener
        // Hook (aktiviert über permission.register_permission_check_method)
        // prüft nur direkte + via-eigene-Rolle erlangte Permissions am User.
        // Nur greifen, wenn die Ability einem registrierten Permission-Namen
        // entspricht, damit wir keine Ressource-Policies kurzschließen.
        Gate::before(static function (User $user, string $ability): ?bool {
            if (! str_contains($ability, '.')) {
                return null;
            }

            return $user->hasEffectivePermission($ability) ? true : null;
        });

        $this->configureRateLimiters();

        $this->registerStopwatchViewComposer();
        $this->registerAttendanceViewComposer();
        $this->registerDateRangeViewComposer();
        $this->registerBrandingViewComposer();
        $this->registerReminderViewComposer();

        Password::defaults(function () {
            $rule = Password::min(12)
                ->mixedCase()
                ->letters()
                ->numbers()
                ->symbols();

            return $this->app->environment('production')
                ? $rule->uncompromised()
                : $rule;
        });
    }

    private function configureRateLimiters(): void {
        RateLimiter::for('login', function (Request $request) {
            $email = (string) $request->input('email', $request->input('username', ''));

            return [
                Limit::perMinute(5)->by(strtolower($email) . '|' . $request->ip()),
                Limit::perMinute(20)->by($request->ip()),
            ];
        });

        RateLimiter::for('register', fn(Request $request) => Limit::perMinute(3)->by($request->ip()));

        RateLimiter::for('password', function (Request $request) {
            $userId = (string) ($request->user()?->getAuthIdentifier() ?? 'guest');

            return [
                Limit::perMinute(5)->by('pwd:' . $userId . '|' . $request->ip()),
                Limit::perHour(20)->by('pwd:' . $userId),
            ];
        });
    }

    /**
     * Stellt der App-Layout-View den aktuell laufenden Stoppuhr-Eintrag
     * (TimeEntry|null) als $stopwatchEntry bereit, damit das Header-Widget
     * den Live-Timer rendern kann.
     *
     * Fängt DB-/Infrastruktur-Fehler ab, damit Fehlerseiten und der
     * Login-Screen auch bei nicht erreichbarer Datenbank gerendert werden
     * können.
     */
    private function registerStopwatchViewComposer(): void {
        View::composer('layouts.app', function ($view): void {
            $entry = null;
            try {
                $user = Auth::user();
                if ($user instanceof User) {
                    $entry = app(Stopwatch::class)->current($user);
                }
            } catch (\Throwable $e) {
                report($e);
                $entry = null;
            }
            $view->with('stopwatchEntry', $entry);
        });
    }

    /**
     * Stellt der App-Layout-View den global gewählten Zeitraum
     * (Preset + Von/Bis) als $globalDateRange bereit, damit das Header-
     * Widget und Report-Controller einen einheitlichen State teilen.
     *
     * Fällt bei Session-/DB-Fehlern auf einen statischen Fallback zurück,
     * damit das Layout (z.B. die Fehlerseite) noch gerendert werden kann.
     */
    /**
     * Stellt der App-Layout-View die aktuell offene Stempelung
     * (Attendance|null) als $attendanceCurrent bereit, damit das
     * Stempeluhr-Widget im Header den Live-Timer und die Clock-in/out-
     * Buttons rendern kann.
     */
    private function registerAttendanceViewComposer(): void {
        View::composer('layouts.app', function ($view): void {
            $current = null;
            try {
                $user = Auth::user();
                if ($user instanceof User) {
                    $current = app(AttendanceClockService::class)->current($user);
                }
            } catch (\Throwable $e) {
                report($e);
                $current = null;
            }
            $view->with('attendanceCurrent', $current);
        });
    }

    /**
     * Stellt der App-Layout-View die kontextsensitiven Smart-Reminder
     * (siehe `ReminderService::for()`) als `$reminderItems` zur Verfügung.
     * Fällt bei Fehlern auf eine leere Liste zurück, damit das Layout
     * (insb. die Fehlerseite) stets gerendert werden kann.
     */
    private function registerReminderViewComposer(): void {
        View::composer('layouts.app', function ($view): void {
            $items = [];
            try {
                $user = Auth::user();
                if ($user instanceof User) {
                    $items = app(ReminderService::class)->for($user);
                }
            } catch (\Throwable $e) {
                report($e);
                $items = [];
            }
            $view->with('reminderItems', $items);
        });
    }

    private function registerDateRangeViewComposer(): void {
        View::composer(['layouts.app', 'components.header-date-range'], function ($view): void {
            try {
                $range = app(DateRangeContext::class)->current();
            } catch (\Throwable $e) {
                report($e);
                $now = CarbonImmutable::now();
                $range = [
                    'from' => $now->startOfMonth(),
                    'to' => $now->endOfMonth(),
                    'preset' => DateRangeContext::PRESET_THIS_MONTH,
                    'label' => __('Dieser Monat'),
                    'unit' => 'month',
                    'isoWeekLabel' => null,
                ];
            }
            $view->with('globalDateRange', $range);
        });
    }

    /**
     * Stellt allen Layout- und PDF-Views den BrandingService als
     * `$branding` bereit – die Views müssen den Service nicht selbst
     * resolven und können ohne Type-Hint auf `appName()`, `logoUrl()`
     * etc. zugreifen.
     */
    private function registerBrandingViewComposer(): void {
        View::composer(['layouts.*', 'auth.*', 'pdf.*'], function ($view): void {
            try {
                $branding = app(BrandingService::class);
            } catch (\Throwable $e) {
                report($e);
                $branding = null;
            }
            $view->with('branding', $branding);
        });
    }
}
