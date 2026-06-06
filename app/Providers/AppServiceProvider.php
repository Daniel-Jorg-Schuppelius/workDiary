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
use App\Automation\{ConditionEvaluator, RuleEngine};
use App\Legacy\Auth\LegacyUserProvider;
use App\Listeners\AuthEventSubscriber;
use App\Models\{ActivityCategory, Asset, Attachment, Building, Classification, ClassificationRequirement, Comment, CoverageRequirement, Customer, DiaryEntry, DutyPlan, EmergencyAssignment, Event, EventCategory, Expense, ExpenseCategory, FlexEligibility, Floor, ForeignCustomer, KeyHandover, MaintenancePlan, Material, MaterialUsage, MeterReading, Milestone, MonthClosure, NumberFormat, OpenIssue, Organization, PerDiemRate, PerDiemTrip, ProcedureBackupProof, ProcedureDeviation, ProcedureRun, ProcedureTemplate, Protocol, Qualification, Room, ScheduledShift, ServiceTicket, ShiftType, Site, Software, Supplier, Tag, Task, TimeCorrectionRequest, TimeEntry, TimeExport, Timesheet, TravelLog, User, UserGroup, WorkSchedule};
use App\Observers\{AttachmentObserver, CommentObserver, CustomerObserver, DiaryEntryObserver, EmergencyAssignmentObserver, ForeignCustomerObserver, MaterialUsageObserver, OrganizationObserver, SupplierObserver, TagObserver, TimeEntryObserver, TimesheetObserver, UserObserver};
use App\Policies\{ActivityCategoryPolicy, AssetPolicy, BuildingPolicy, ClassificationPolicy, ClassificationRequirementPolicy, CoverageRequirementPolicy, DutyPlanPolicy, EventCategoryPolicy, EventPolicy, ExpenseCategoryPolicy, ExpensePolicy, FlexEligibilityPolicy, FloorPolicy, KeyHandoverPolicy, MaintenancePlanPolicy, MaterialPolicy, MaterialUsagePolicy, MeterReadingPolicy, MilestonePolicy, MonthClosurePolicy, NumberFormatPolicy, OpenIssuePolicy, OrganizationPolicy, PerDiemRatePolicy, PerDiemTripPolicy, ProcedureBackupProofPolicy, ProcedureDeviationPolicy, ProcedureRunPolicy, ProcedureTemplatePolicy, ProtocolPolicy, QualificationPolicy, RoomPolicy, ScheduledShiftPolicy, ServiceTicketPolicy, ShiftTypePolicy, SitePolicy, SoftwarePolicy, TaskPolicy, TimeCorrectionRequestPolicy, TimeEntryPolicy, TimeExportPolicy, TimesheetPolicy, TravelLogPolicy, UserGroupPolicy, WorkSchedulePolicy};
use App\Services\Attendance\AttendanceClockService;
use App\Services\BrandingService;
use App\Services\Classification\{ClassificationManager, ClassificationResolver};
use App\Services\I18n\JsTranslationProvider;
use App\Services\Install\{EnvWriter, InstallationManager};
use App\Services\Reminders\ReminderService;
use App\Services\Routing\{NominatimGeocoder, OsrmRouter};
use App\Services\Timesheet\Stopwatch;
use App\Services\UI\DateRangeContext;
use App\Support\CarbonFmt;
use App\Support\Setting;
use Carbon\{Carbon as CarbonMutable, CarbonImmutable};
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\{Auth, Event as EventFacade, Gate, RateLimiter, View};
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;

class AppServiceProvider extends ServiceProvider {
    public function register(): void {
        // Web-Installer: EnvWriter/InstallationManager mit Default-Pfaden binden,
        // damit Middleware und Controller sie auflösen können. Tests können diese
        // Bindings auf temporäre Pfade umbiegen.
        $this->app->singleton(EnvWriter::class, static fn(): EnvWriter => EnvWriter::forApp());
        $this->app->singleton(InstallationManager::class, function (Application $app): InstallationManager {
            return new InstallationManager($app->make(EnvWriter::class));
        });

        // Org-bewusst: scoped statt singleton, damit Settings-UI-Overrides pro
        // Mandant greifen. Setting::get() fällt automatisch auf config() zurück.
        $this->app->scoped(NominatimGeocoder::class, function (): NominatimGeocoder {
            return new NominatimGeocoder([
                'base_url' => Setting::get('routing.nominatim.base_url'),
                'user_agent' => Setting::get('routing.nominatim.user_agent'),
                'email' => Setting::get('routing.nominatim.email'),
                'rate_limit_per_sec' => (int) Setting::get('routing.nominatim.rate_limit_per_sec', 1),
                'timeout' => (int) Setting::get('routing.nominatim.timeout', 8),
            ]);
        });

        $this->app->scoped(OsrmRouter::class, function (): OsrmRouter {
            return new OsrmRouter([
                'base_url' => Setting::get('routing.osrm.base_url'),
                'profile' => Setting::get('routing.osrm.profile'),
                'timeout' => (int) Setting::get('routing.osrm.timeout', 10),
            ]);
        });

        // BrandingService cached die Organisation pro Request → einmalig
        // pro Container-Lifecycle resolven.
        $this->app->singleton(BrandingService::class);

        // Sqid-Encoder als Singleton: hält pro Modell-Klasse einen Sqids-
        // Instanz-Cache mit deterministisch permutiertem Alphabet vor.
        $this->app->singleton(\App\Services\SqidEncoder::class, function ($app): \App\Services\SqidEncoder {
            /** @var array<string, mixed> $cfg */
            $cfg = (array) $app['config']->get('sqids', []);
            $salt = (string) ($cfg['salt'] ?? '');

            if ($salt === '' && $app->environment('production')) {
                throw new \RuntimeException('SQIDS_SALT must be set in production.');
            }

            return new \App\Services\SqidEncoder(
                salt: $salt,
                minLength: (int) ($cfg['min_length'] ?? 10),
                alphabet: (string) ($cfg['alphabet'] ?? 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789'),
                blocklist: array_values((array) ($cfg['blocklist'] ?? [])),
            );
        });
        $this->app->singleton(ClassificationManager::class, function ($app): ClassificationManager {
            return new ClassificationManager($app->make(ClassificationResolver::class));
        });

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

        // In-App-Hilfe (MVP-051): Loader liest aus resources/help/.
        $this->app->singleton(\App\Services\Help\HelpTopicLoader::class, function (): \App\Services\Help\HelpTopicLoader {
            return new \App\Services\Help\HelpTopicLoader(\App\Services\Help\HelpTopicLoader::defaultPath());
        });

        // Feature-Flags (Folge zu MVP-047): einmal pro Request, damit
        // @feature und requires-feature dieselbe Auflösung sehen.
        $this->app->singleton(\App\Services\Licensing\FeatureFlagResolver::class);

        // Widget-Dashboard (Phase G): Registry als Singleton; Default-Widgets
        // werden in boot() registriert.
        $this->app->singleton(\App\Dashboard\WidgetRegistry::class);
    }

    public function boot(): void {
        // Carbon-Macro: wandelt einen (in UTC gespeicherten) Zeitpunkt in die
        // aktive Anzeige-Zeitzone um (User-Override → Organisation → Fallback).
        // Auf allen Carbon-Varianten registriert, da Eloquent-Casts
        // Illuminate\Support\Carbon liefern, manuelle Aufrufe aber auch
        // Carbon\Carbon / CarbonImmutable nutzen.
        // Carbon-Anzeige-Macros (Logik in App\Support\CarbonFmt). Inline an macro()
        // übergeben, damit larastan $this korrekt an die Carbon-Instanz bindet.
        //   ->orgTz()     : in die aktive Anzeige-Zeitzone umrechnen
        //   ->fdate()     : reines Datum im konfigurierten Format (ohne TZ-Umrechnung)
        //   ->fdatetime() : Datum+Uhrzeit in Anzeige-Zeitzone + Format
        //   ->ftime()     : nur Uhrzeit in Anzeige-Zeitzone + Format
        CarbonMutable::macro('orgTz', fn () => CarbonFmt::orgTz($this));
        CarbonImmutable::macro('orgTz', fn () => CarbonFmt::orgTz($this));
        \Illuminate\Support\Carbon::macro('orgTz', fn () => CarbonFmt::orgTz($this));
        CarbonMutable::macro('fdate', fn () => CarbonFmt::fdate($this));
        CarbonImmutable::macro('fdate', fn () => CarbonFmt::fdate($this));
        \Illuminate\Support\Carbon::macro('fdate', fn () => CarbonFmt::fdate($this));
        CarbonMutable::macro('fdatetime', fn () => CarbonFmt::fdatetime($this));
        CarbonImmutable::macro('fdatetime', fn () => CarbonFmt::fdatetime($this));
        \Illuminate\Support\Carbon::macro('fdatetime', fn () => CarbonFmt::fdatetime($this));
        CarbonMutable::macro('ftime', fn () => CarbonFmt::ftime($this));
        CarbonImmutable::macro('ftime', fn () => CarbonFmt::ftime($this));
        \Illuminate\Support\Carbon::macro('ftime', fn () => CarbonFmt::ftime($this));

        Auth::provider('legacy', function ($app) {
            return new LegacyUserProvider($app['hash']);
        });
        Auth::provider('customer-eloquent', function ($app) {
            return new CustomerUserProvider($app['hash']);
        });

        EventFacade::subscribe(AuthEventSubscriber::class);
        EventFacade::subscribe(\App\Listeners\PluginEventSubscriber::class);

        Comment::observe(CommentObserver::class);
        Attachment::observe(AttachmentObserver::class);
        Customer::observe(CustomerObserver::class);
        ForeignCustomer::observe(ForeignCustomerObserver::class);
        Supplier::observe(SupplierObserver::class);
        EmergencyAssignment::observe(EmergencyAssignmentObserver::class);
        DiaryEntry::observe(DiaryEntryObserver::class);
        Tag::observe(TagObserver::class);
        User::observe(UserObserver::class);
        TimeEntry::observe(TimeEntryObserver::class);
        Timesheet::observe(TimesheetObserver::class);
        MaterialUsage::observe(MaterialUsageObserver::class);
        Organization::observe(OrganizationObserver::class);

        Gate::policy(\App\Models\Chat\Channel::class, \App\Policies\Chat\ChannelPolicy::class);
        Gate::policy(\App\Models\Chat\Message::class, \App\Policies\Chat\MessagePolicy::class);
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
        Gate::policy(Software::class, SoftwarePolicy::class);
        Gate::policy(Site::class, SitePolicy::class);
        Gate::policy(Building::class, BuildingPolicy::class);
        Gate::policy(Floor::class, FloorPolicy::class);
        Gate::policy(MaintenancePlan::class, MaintenancePlanPolicy::class);
        Gate::policy(ServiceTicket::class, ServiceTicketPolicy::class);
        Gate::policy(KeyHandover::class, KeyHandoverPolicy::class);
        Gate::policy(MeterReading::class, MeterReadingPolicy::class);
        Gate::policy(NumberFormat::class, NumberFormatPolicy::class);
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
        Gate::policy(MonthClosure::class, MonthClosurePolicy::class);
        Gate::policy(TimeCorrectionRequest::class, TimeCorrectionRequestPolicy::class);
        Gate::policy(TimeExport::class, TimeExportPolicy::class);
        Gate::policy(\App\Models\UserBookmark::class, \App\Policies\UserBookmarkPolicy::class);
        Gate::policy(\App\Models\UserFilterPreset::class, \App\Policies\UserFilterPresetPolicy::class);
        Gate::policy(\App\Models\InvoiceTemplate::class, \App\Policies\InvoiceTemplatePolicy::class);
        Gate::policy(Supplier::class, \App\Policies\SupplierPolicy::class);

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
        $this->registerJsTranslationsViewComposer();
        $this->registerBookmarksViewComposer();
        $this->registerDashboardWidgets();

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

        // @feature('code') Blade-Direktive (Folge zu MVP-047). Identisch
        // zu @if (app(FeatureFlagResolver::class)->isEnabled('code')), nur
        // kürzer in Views. Mit @endfeature schließen.
        \Illuminate\Support\Facades\Blade::if('feature', function (string $code): bool {
            return app(\App\Services\Licensing\FeatureFlagResolver::class)->isEnabled($code);
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

    /**
     * Stellt dem App-Layout die flachen JS-Übersetzungen (siehe
     * {@see JsTranslationProvider}) als `$jsTranslations` bereit, damit das
     * Layout sie via `<script>window.__translations = …</script>` an den
     * Client-Side-`__()`-Helper übergeben kann.
     */
    private function registerJsTranslationsViewComposer(): void {
        View::composer('layouts.app', function ($view): void {
            try {
                $translations = app(JsTranslationProvider::class)->all();
            } catch (\Throwable $e) {
                report($e);
                $translations = [];
            }
            $view->with('jsTranslations', $translations);
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
        View::composer(['layouts.*', 'auth.*', 'pdf.*', 'reports.pdf.*', 'reports.drilldown.pdf.*'], function ($view): void {
            try {
                $branding = app(BrandingService::class);
            } catch (\Throwable $e) {
                report($e);
                $branding = null;
            }
            $view->with('branding', $branding);
        });
    }

    /**
     * Stellt dem App-Layout die Lesezeichen des eingeloggten Users
     * als `$userBookmarks` bereit (Phase H).
     */
    private function registerBookmarksViewComposer(): void {
        View::composer('layouts.app', function ($view): void {
            $user = Auth::user();
            $bookmarks = $user instanceof User ? $user->bookmarks()->get() : collect();
            $view->with('userBookmarks', $bookmarks);
        });
    }

    /**
     * Registriert die Standard-Dashboard-Widgets in der Registry (Phase G).
     */
    private function registerDashboardWidgets(): void {
        /** @var \App\Dashboard\WidgetRegistry $registry */
        $registry = $this->app->make(\App\Dashboard\WidgetRegistry::class);

        // Personal-KPIs, Team-KPIs, Finance, Urlaub/Flex, Schichten, Notdienste und
        // Onboarding werden bereits fest im Tab-Dashboard (dashboard/index.blade.php)
        // gerendert und sind daher hier NICHT als konfigurierbare Widgets registriert
        // (sonst doppelte Anzeige). Die Klassen bleiben für eine spätere Reaktivierung
        // erhalten. Der Widget-Loop bleibt für nicht-überlappende Widgets (z. B. Lesezeichen).
        $registry->register($this->app->make(\App\Dashboard\Widgets\BookmarksWidget::class));
    }
}
