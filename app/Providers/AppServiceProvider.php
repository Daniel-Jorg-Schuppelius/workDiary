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
use App\Legacy\LegacyBridge;
use App\Listeners\AuthEventSubscriber;
use App\Models\{ActivityCategory, Asset, Attachment, Building, Classification, ClassificationRequirement, Comment, CommunicationNote, CoverageRequirement, Customer, DiaryEntry, DutyPlan, EmergencyAssignment, Event, EventCategory, Expense, ExpenseCategory, FlexEligibility, Floor, KeyHandover, MaintenancePlan, Material, MaterialUsage, MeterReading, Milestone, MonthClosure, NumberFormat, OpenIssue, Organization, PerDiemRate, PerDiemTrip, ProcedureBackupProof, ProcedureDeviation, ProcedureRun, ProcedureTemplate, Project, Protocol, Qualification, Room, ScheduledShift, ServiceTicket, ShiftType, Site, Software, Supplier, Tag, Task, TimeCorrectionRequest, TimeEntry, TimeExport, Timesheet, TravelLog, User, UserGroup, WorkSchedule};
use App\Observers\{AttachmentObserver, CommentObserver, CustomerObserver, DiaryEntryObserver, EmergencyAssignmentObserver, MaterialUsageObserver, OrganizationObserver, ProjectObserver, ProtocolObserver, TagObserver, TimeEntryObserver, TimesheetObserver, UserObserver};
use App\Policies\{ActivityCategoryPolicy, AssetPolicy, BuildingPolicy, ClassificationPolicy, ClassificationRequirementPolicy, CommunicationNotePolicy, CoverageRequirementPolicy, DutyPlanPolicy, EventCategoryPolicy, EventPolicy, ExpenseCategoryPolicy, ExpensePolicy, FlexEligibilityPolicy, FloorPolicy, KeyHandoverPolicy, MaintenancePlanPolicy, MaterialPolicy, MaterialUsagePolicy, MeterReadingPolicy, MilestonePolicy, MonthClosurePolicy, NumberFormatPolicy, OpenIssuePolicy, OrganizationPolicy, PerDiemRatePolicy, PerDiemTripPolicy, ProcedureBackupProofPolicy, ProcedureDeviationPolicy, ProcedureRunPolicy, ProcedureTemplatePolicy, ProtocolPolicy, QualificationPolicy, RoomPolicy, ScheduledShiftPolicy, ServiceTicketPolicy, ShiftTypePolicy, SitePolicy, SoftwarePolicy, TaskPolicy, TimeCorrectionRequestPolicy, TimeEntryPolicy, TimeExportPolicy, TimesheetPolicy, TravelLogPolicy, UserGroupPolicy, WorkSchedulePolicy};
use App\Services\Attendance\AttendanceClockService;
use App\Services\BrandingService;
use App\Services\Classification\{ClassificationManager, ClassificationResolver};
use App\Services\I18n\JsTranslationProvider;
use App\Services\Install\{EnvWriter, InstallationManager};
use App\Services\Reminders\ReminderService;
use App\Services\Routing\{NominatimGeocoder, OsrmRouter};
use App\Services\Timesheet\Stopwatch;
use App\Services\UI\DateRangeContext;
use App\Support\{CarbonFmt, Setting};
use Carbon\{Carbon as CarbonMutable, CarbonImmutable};
use CommonToolkit\Enums\HashAlgorithm;
use CommonToolkit\Helper\Data\CryptoHelper;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\{Auth, Event as EventFacade, Gate, RateLimiter, View};
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;

class AppServiceProvider extends ServiceProvider {
    public function register(): void {
        // Wetterprovider (Feature 062): je Org über Setting `weather.provider`
        // aufgelöst. Org-Kontext muss VOR der Container-Auflösung gebunden sein
        // (vgl. FetchProtocolWeatherJob).
        $this->app->bind(\App\Services\Weather\Contracts\WeatherProvider::class, static function ($app): \App\Services\Weather\Contracts\WeatherProvider {
            return match (Setting::get('weather.provider', 'open-meteo')) {
                'dwd' => new \App\Services\Weather\DwdProvider($app->make(\App\Plugins\Support\PluginHttpFactory::class)->coreClient('weather-dwd', \App\Services\Weather\DwdProvider::BASE)),
                default => new \App\Services\Weather\OpenMeteoProvider($app->make(\App\Plugins\Support\PluginHttpFactory::class)->coreClient('weather-open-meteo', 'https://api.open-meteo.com')),
            };
        });

        // E-Mail-Eingang (Feature 056): IMAP-Transport über webklex/php-imap;
        // Tests binden ein Fake-Gateway. Der Intake-Kern hängt nur am Interface.
        // Transport-Weiche (Feature 102): IMAP bleibt Default, msgraph-Postfächer
        // laufen über die Graph-Mail-Verbindung der Organisation.
        $this->app->singleton(\App\Services\Mail\MailboxGateway::class, \App\Services\Mail\TransportSelectingMailboxGateway::class);

        // Settings-Registry (Feature 067, MVP-173): Definitionen werden je
        // Prozess einmal aus config/settings-registry.php hydriert.
        $this->app->singleton(\App\Settings\SettingsRegistry::class);

        // Ablauf-Scanner (Feature 041, MVP-057): Singleton, damit
        // Konnektor-Probes (extend()) den Scan-Lauf erreichen.
        $this->app->singleton(\App\Services\Operations\Expiry\ExpiryScanner::class);

        // Aufbewahrungs-Registry (Restpunkte 66+67): Fristen je Rechtsraum
        // aus config/retention.php; die Policies liefern die überfälligen
        // Datensätze für den Review-Scan (Vorschläge statt Direktlöschung).
        // Alle Policy-Registrierungen: RetentionRegistrations (B4).
        $this->app->singleton(\App\Services\Privacy\Retention\RetentionRegistry::class, function (): \App\Services\Privacy\Retention\RetentionRegistry {
            $registry = new \App\Services\Privacy\Retention\RetentionRegistry;
            \App\Services\Privacy\Retention\RetentionRegistrations::register($registry);

            return $registry;
        });

        // Hinweisgeber-Anhang-Scanner: Treiber per Konfiguration (Default: kein
        // Scanner → fail-safe Quarantaene). Tests koennen einen Fake binden.
        // Click-to-Dial (W4.5): HTTP-Adapter als Standard; Tests binden einen
        // Fake, ohne den Service anfassen zu muessen.
        $this->app->bind(\App\Services\Cti\Dial\CtiDialer::class, \App\Services\Cti\Dial\HttpCtiDialer::class);

        $this->app->bind(\App\Services\Whistleblowing\Scanning\ScanDriver::class, function (): \App\Services\Whistleblowing\Scanning\ScanDriver {
            return match ((string) config('whistleblowing.scanner', 'none')) {
                'clamav' => new \App\Services\Whistleblowing\Scanning\ClamAvScanDriver,
                default => new \App\Services\Whistleblowing\Scanning\NullScanDriver,
            };
        });

        // Web-Installer: EnvWriter/InstallationManager mit Default-Pfaden binden;
        // Tests biegen die Bindings auf temporäre Pfade um.
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
            ], app(\App\Plugins\Support\PluginHttpFactory::class));
        });

        // Kunden-Sonderkonditionen (Feature 098): scoped, damit der interne
        // Agreement-Cache pro Request bzw. pro Queue-Job lebt und zwischen Jobs
        // verworfen wird — ein langlebiger Worker rechnet sonst mit veralteten
        // Konditionen. Invalidierung im selben Request/Job über die
        // saved/deleted-Hooks von CustomerBillingAgreement/CustomerBillingRate.
        $this->app->scoped(\App\Services\Billing\AgreementRateResolver::class);

        // Org-Standardsatz (MVP-482): gleicher Lebenszyklus — der Satz je
        // Organisation wird pro Request/Job einmal gelesen.
        $this->app->scoped(\App\Services\Billing\OrganizationDefaultRateResolver::class);

        // Schreibfehler-Wörterbuch: Map je Organisation wird pro Request/Job
        // einmal geladen (scoped, nie static — Octane/Worker).
        $this->app->scoped(\App\Services\Invoicing\TextCorrectionService::class);

        $this->app->scoped(OsrmRouter::class, function (): OsrmRouter {
            return new OsrmRouter([
                'base_url' => Setting::get('routing.osrm.base_url'),
                'profile' => Setting::get('routing.osrm.profile'),
                'timeout' => (int) Setting::get('routing.osrm.timeout', 10),
            ], app(\App\Plugins\Support\PluginHttpFactory::class));
        });

        // BrandingService cached die Organisation pro Request → einmalig
        // pro Container-Lifecycle resolven.
        $this->app->singleton(BrandingService::class);

        // ThemeService nutzt denselben Request-Cache (über BrandingService)
        // und hält die validierten Org-Custom-Themes vor.
        $this->app->singleton(\App\Services\ThemeService::class);

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

        // Externe Bestands-Dispatcher (MVP-072): Registry muss Singleton sein,
        // damit Plugins beim Booten registrieren und der Outbox-Job auflösen kann.
        $this->app->singleton(\App\Services\Inventory\ExternalInventoryDispatcherResolver::class);

        // Externe Bestandsprovider (Feature 078, MVP-319): gleiche Registry-Mechanik —
        // Plugins registrieren ihre Provider-Factory beim Booten.
        $this->app->singleton(\App\Services\Inventory\InventoryProviderResolver::class);

        // Generische Integrations-Outbox (MVP-114): gleiche Registry-Mechanik.
        $this->app->singleton(\App\Services\Integration\IntegrationOutboxDispatcherResolver::class);

        // Belegfluss-Quellen (Feature 105; Vollscan 2026-08, B9): Kern-Quellen
        // hier, Plugin-Quellen (Lexoffice/orgaMAX) registriert der jeweilige
        // Plugin-Provider beim Booten — der Kern kennt keine Plugin-Tabellen.
        $this->app->singleton(\App\Services\Billing\Feed\DocumentFeedSourceRegistry::class, function (): \App\Services\Billing\Feed\DocumentFeedSourceRegistry {
            $registry = new \App\Services\Billing\Feed\DocumentFeedSourceRegistry;
            $registry->register(new \App\Services\Billing\Feed\Sources\InvoiceSource($registry));
            $registry->register(new \App\Services\Billing\Feed\Sources\QuoteSource);
            $registry->register(new \App\Services\Billing\Feed\Sources\AccountingVoucherSource);
            $registry->register(new \App\Services\Billing\Feed\Sources\IncomingEInvoiceSource);
            $registry->register(new \App\Services\Billing\Feed\Sources\ExpenseSource($registry));

            return $registry;
        });

        // Auslagen-Beleg-Provider (Feature 105/106; Vollscan 2026-08, B9):
        // Singleton, damit das Buchhaltungs-Plugin beim Booten registrieren
        // kann. Ohne Registrierung greift der NullExpenseLinkProvider.
        $this->app->singleton(\App\Services\Billing\ExpenseLinkProviderResolver::class);

        // Versand-Provider (Feature 059, MVP-128): Carrier-Plugins registrieren
        // ihren ShippingProvider beim Booten, der ShipmentService löst darüber auf.
        $this->app->singleton(\App\Services\Shipping\ShippingProviderRegistry::class);

        // Beleg-Rückabruf je Buchhaltungssystem (Feature 122, MVP-731): eine
        // Registry statt Plugin-Capabilities — InvoicePlane hat mangels API
        // gar keine Plugin-Klasse und muss trotzdem mitspielen können.
        $this->app->singleton(\App\Services\Finance\Accounting\Vouchers\VoucherPullerRegistry::class);
        // Ohne Pilotinstanz gibt es keinen InvoicePlane-Leser — und damit
        // keinen erfundenen Beleg (Feature 086).
        $this->app->bind(
            \App\Plugins\InvoicePlane\Schema\VoucherReaderFactory::class,
            \App\Plugins\InvoicePlane\Schema\NullVoucherReaderFactory::class,
        );

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

        // Normprofil-Registry (Feature 046): Profile aus config/isms-norms/
        // einmal pro Prozess laden + Schema validieren.
        $this->app->singleton(\App\Services\Isms\NormProfileRegistry::class);

        // Crosswalk-Registry (Nachtrag NIST): Norm-Zuordnungen aus
        // config/isms-crosswalks/ einmal pro Prozess laden + validieren.
        $this->app->singleton(\App\Services\Isms\CrosswalkRegistry::class);

        // In-App-Hilfe (MVP-051): Loader liest aus resources/help/.
        $this->app->singleton(\App\Services\Help\HelpTopicLoader::class, function (): \App\Services\Help\HelpTopicLoader {
            return new \App\Services\Help\HelpTopicLoader(\App\Services\Help\HelpTopicLoader::defaultPath());
        });

        // Feature-Flags (Folge zu MVP-047): scoped statt singleton — einmal pro
        // Request/Queue-Job, damit @feature und requires-feature dieselbe
        // Auflösung sehen. Der interne $resolved-Cache hängt am
        // currentOrganization-Kontext OHNE Org-Key; als singleton würde ein
        // langlebiger Worker die Flags der ersten Org auf Jobs anderer Orgs
        // anwenden (Cross-Tenant-Leck). scoped wird pro Job verworfen.
        $this->app->scoped(\App\Services\Licensing\FeatureFlagResolver::class);

        // Dashboard-Datenquelle: scoped, damit die Kacheln eines Requests sich
        // Abfragen teilen (interner $memo) — als Singleton würde ein Worker die
        // Daten des ersten Nutzers an den nächsten Job weiterreichen.
        $this->app->scoped(\App\Services\Dashboard\DashboardService::class);

        // Widget-Dashboard (Phase G): Registry als Singleton; Default-Widgets
        // werden in boot() registriert.
        $this->app->singleton(\App\Dashboard\WidgetRegistry::class);
    }

    public function boot(): void {
        // IP-Geolokalisierung (Feature 085): lokale .mmdb aus config/geoip.php.
        // Ohne DB-Datei degradiert IpLocationHelper::lookup() sauber zu null.
        \CommonToolkit\Helper\Geo\IpLocationHelper::configure([
            'database' => config('geoip.database'),
            'locale' => (string) config('geoip.locale', 'de'),
        ]);

        // LIKE-Suche mit escapten Wildcards (`%`/`_` in Nutzereingaben wirken
        // sonst als Wildcards). Explizites ESCAPE-Zeichen '!' statt Backslash,
        // da Backslash in MySQL- und SQLite-String-Literalen unterschiedlich
        // behandelt wird.
        \Illuminate\Database\Query\Builder::macro('whereLikeEscaped', function (string $column, string $term, string $side = 'both', string $boolean = 'and') {
            /** @var \Illuminate\Database\Query\Builder $this */
            $escaped = str_replace(['!', '%', '_'], ['!!', '!%', '!_'], $term);
            $pattern = match ($side) {
                'prefix' => $escaped . '%',
                'suffix' => '%' . $escaped,
                default => '%' . $escaped . '%',
            };

            // $column über die Grammar gequotet (injection-sicher), Suchbegriff
            // als Bindung. PHPStan kann die literal-string-Eigenschaft nach dem
            // Laufzeit-Quoting nicht beweisen.
            // @phpstan-ignore argument.type
            return $this->whereRaw($this->grammar->wrap($column) . " like ? escape '!'", [$pattern], $boolean);
        });
        \Illuminate\Database\Query\Builder::macro('orWhereLikeEscaped', function (string $column, string $term, string $side = 'both') {
            /** @var \Illuminate\Database\Query\Builder $this */
            return $this->whereLikeEscaped($column, $term, $side, 'or');
        });

        // organization_id-FK für NEUE Migrationen (konsolidierungs-audit-2026-07,
        // D9), Mehrheitssemantik des Bestands: Pflicht-FK kaskadiert beim
        // Org-Löschen, optionaler FK wird genullt. Bestand bleibt unangetastet.
        // Achtung MySQL: Index-/FK-Namen max. 64 Zeichen und DB-weit eindeutig —
        // bei langen Tabellennamen weiterhin kurze Namen explizit vergeben
        // (dann foreignId()->constrained(indexName: ...) statt Macro).
        \Illuminate\Database\Schema\Blueprint::macro('organizationFk', function () {
            /** @var \Illuminate\Database\Schema\Blueprint $this */
            return $this->foreignId('organization_id')->constrained('organizations')->cascadeOnDelete();
        });
        \Illuminate\Database\Schema\Blueprint::macro('organizationFkNullable', function () {
            /** @var \Illuminate\Database\Schema\Blueprint $this */
            return $this->foreignId('organization_id')->nullable()->constrained('organizations')->nullOnDelete();
        });

        // Mandanten-Hygiene im langlebigen Queue-Worker (Whitebox 2026-07-10,
        // J1/J2): Jeder Job startet mit sauberem Container, sonst verschleppt
        // ein gebundenes 'currentOrganization' in den nächsten Job (org-gescopte
        // Queries filtern still auf die falsche Org). Der sync-Driver läuft
        // INNERHALB eines Requests — dort bleibt der Request-Kontext erhalten.
        \Illuminate\Support\Facades\Queue::before(static function (\Illuminate\Queue\Events\JobProcessing $event): void {
            if ($event->connectionName === 'sync') {
                return;
            }
            app()->forgetInstance('currentOrganization');
        });

        // Queue-Worker-Heartbeat (Feature 067, MVP-177-Vorgriff): Cache-Key für
        // die Diagnose-Seite, im Worker-Loop gedrosselt geschrieben.
        \Illuminate\Support\Facades\Queue::looping(static function (): void {
            static $lastWritten = null;
            $now = CarbonImmutable::now();
            if ($lastWritten !== null && $now->diffInSeconds($lastWritten, true) < 30) {
                return;
            }
            $lastWritten = $now;
            \Illuminate\Support\Facades\Cache::put(
                \App\Services\Diagnostics\DiagnosticsService::QUEUE_WORKER_HEARTBEAT_KEY,
                $now->toIso8601String(),
            );
        });

        // Zustellnachweis für Rechnungs-/Mahnmails (Vollaudit 2026-07, M26):
        // queued → sent + Message-ID, sobald der Mailer wirklich versendet.
        EventFacade::listen(
            \Illuminate\Mail\Events\MessageSent::class,
            \App\Listeners\RecordInvoiceMailDelivery::class,
        );

        // Laufzeit-Nachweise der Registry-Jobs (Feature 067, MVP-177):
        // Start/Erfolg/Fehler/Skip je Schedule-Event → runs/states.
        EventFacade::listen(
            \Illuminate\Console\Events\ScheduledTaskStarting::class,
            [\App\Scheduling\ScheduleRunRecorder::class, 'handleStarting'],
        );
        EventFacade::listen(
            \Illuminate\Console\Events\ScheduledTaskFinished::class,
            [\App\Scheduling\ScheduleRunRecorder::class, 'handleFinished'],
        );
        EventFacade::listen(
            \Illuminate\Console\Events\ScheduledTaskFailed::class,
            [\App\Scheduling\ScheduleRunRecorder::class, 'handleFailed'],
        );
        EventFacade::listen(
            \Illuminate\Console\Events\ScheduledBackgroundTaskFinished::class,
            [\App\Scheduling\ScheduleRunRecorder::class, 'handleBackgroundFinished'],
        );
        EventFacade::listen(
            \Illuminate\Console\Events\ScheduledTaskSkipped::class,
            [\App\Scheduling\ScheduleRunRecorder::class, 'handleSkipped'],
        );

        // Stichtags-Rekonstruktion (Nachtrag 046b): jede Bewertungsänderung
        // (SoA-Aussage, Norm-Konformitätsstatus) erzeugt einen append-only
        // Snapshot — Model-Events, damit auch Service-Updates erfasst werden.
        \App\Models\Isms\IsmsApplicabilityStatement::observe(\App\Observers\IsmsAssessmentSnapshotObserver::class);
        \App\Models\Isms\IsmsNormStatus::observe(\App\Observers\IsmsAssessmentSnapshotObserver::class);

        // Task→Board-Sync (Feature 064, P3): Statuswechsel außerhalb des Boards
        // → AgileBoardService::syncColumnFromTask (B4 aus dem Provider gezogen).
        Task::observe(\App\Observers\TaskObserver::class);
        // F14: Fachlogik aus den Model-Hooks in Observer (TimeEntry-saving
        // lebt im bestehenden TimeEntryObserver, s. u.).
        \App\Models\Attendance::observe(\App\Observers\AttendanceObserver::class);
        \App\Models\InvoiceItem::observe(\App\Observers\InvoiceItemObserver::class);

        // Carbon-Anzeige-Macros (Logik in App\Support\CarbonFmt): orgTz/fdate/
        // fdatetime/ftime. Auf allen Carbon-Varianten registriert (Eloquent-Casts
        // liefern Illuminate\Support\Carbon, manuelle Aufrufe auch Carbon\Carbon).
        // Inline an macro() übergeben, damit larastan $this an die Instanz bindet.
        CarbonMutable::macro('orgTz', fn() => CarbonFmt::orgTz($this));
        CarbonImmutable::macro('orgTz', fn() => CarbonFmt::orgTz($this));
        \Illuminate\Support\Carbon::macro('orgTz', fn() => CarbonFmt::orgTz($this));
        CarbonMutable::macro('fdate', fn() => CarbonFmt::fdate($this));
        CarbonImmutable::macro('fdate', fn() => CarbonFmt::fdate($this));
        \Illuminate\Support\Carbon::macro('fdate', fn() => CarbonFmt::fdate($this));
        CarbonMutable::macro('fdatetime', fn() => CarbonFmt::fdatetime($this));
        CarbonImmutable::macro('fdatetime', fn() => CarbonFmt::fdatetime($this));
        \Illuminate\Support\Carbon::macro('fdatetime', fn() => CarbonFmt::fdatetime($this));
        CarbonMutable::macro('ftime', fn() => CarbonFmt::ftime($this));
        CarbonImmutable::macro('ftime', fn() => CarbonFmt::ftime($this));
        \Illuminate\Support\Carbon::macro('ftime', fn() => CarbonFmt::ftime($this));

        Auth::provider('legacy', function ($app) {
            return LegacyBridge::makeAuthProvider($app['hash']);
        });
        Auth::provider('customer-eloquent', function ($app) {
            return new CustomerUserProvider($app['hash']);
        });

        EventFacade::subscribe(AuthEventSubscriber::class);
        EventFacade::subscribe(\App\Listeners\PluginEventSubscriber::class);

        Comment::observe(CommentObserver::class);
        // Feature 107 W10: VK-Preisverlauf für den DATPREIS-Export „seit Datum".
        // F8/E6: contact_* führend — Projektion in die Inline-Spalten.
        \App\Models\ContactAddress::observe(\App\Observers\ContactDetailsProjectionObserver::class);
        \App\Models\ContactBankAccount::observe(\App\Observers\ContactDetailsProjectionObserver::class);
        \App\Models\Article::observe(\App\Observers\ArticleSalePriceObserver::class);
        \App\Models\ArticleVariant::observe(\App\Observers\ArticleVariantSalePriceObserver::class);
        Attachment::observe(AttachmentObserver::class);
        Customer::observe(CustomerObserver::class);
        // Aufgehobene Zahlungszuordnung → Gegenbuchung im lokalen Hauptbuch
        // (Feature 125, MVP-674). Ohne aktivierte Buchhaltung passiert nichts.
        \App\Models\Finance\PaymentAllocation::observe(\App\Observers\PaymentAllocationAccountingObserver::class);
        // Supplier/ForeignCustomer: Audit-Logging via Auditable-Trait, kein Observer mehr (A1).
        EmergencyAssignment::observe(EmergencyAssignmentObserver::class);
        DiaryEntry::observe(DiaryEntryObserver::class);
        Tag::observe(TagObserver::class);
        User::observe(UserObserver::class);
        TimeEntry::observe(TimeEntryObserver::class);
        // Rückrichtung der Zeit-Plugins: ein Observer für alle Quellen, statt je
        // Plugin einer — jeder würde sonst dieselbe Referenz-Abfrage fahren.
        TimeEntry::observe(\App\Plugins\Support\TimeWritebackObserver::class);
        // Git-Issue-Status-Rückrichtung (Audit 2026-08, Welle 1.4): Statuswechsel
        // an Issue-verknüpften Aufgaben → Outbox (GitHub/GitLab, opt-in).
        \App\Models\Task::observe(\App\Plugins\Support\GitIssueImport\GitIssueWritebackObserver::class);
        Timesheet::observe(TimesheetObserver::class);
        MaterialUsage::observe(MaterialUsageObserver::class);
        Organization::observe(OrganizationObserver::class);
        Project::observe(ProjectObserver::class);
        Protocol::observe(ProtocolObserver::class);

        Gate::policy(\App\Models\IdeaMap::class, \App\Policies\IdeaMapPolicy::class);
        Gate::policy(\App\Models\TextCorrection::class, \App\Policies\TextCorrectionPolicy::class);
        // Agiles Projektmanagement (Feature 064).
        Gate::policy(\App\Models\Agile\AgileBoard::class, \App\Policies\Agile\AgileBoardPolicy::class);
        Gate::policy(\App\Models\Agile\AgileWorkItem::class, \App\Policies\Agile\AgileWorkItemPolicy::class);
        Gate::policy(\App\Models\GobdExport::class, \App\Policies\GobdExportPolicy::class);
        Gate::policy(\App\Models\Finance\ProcedureDocumentation::class, \App\Policies\Finance\ProcedureDocumentationPolicy::class);
        // Feature 068: Bewerbungs-/Ausschreibungsmodul (getrennte Rechtebereiche).
        Gate::policy(\App\Models\Ai\AiProviderConnection::class, \App\Policies\Ai\AiProviderConnectionPolicy::class);
        Gate::policy(\App\Models\Ai\AiMemoryEntry::class, \App\Policies\Ai\AiMemoryEntryPolicy::class);
        Gate::policy(\App\Models\Applications\ApplicationOpportunity::class, \App\Policies\Applications\ApplicationOpportunityPolicy::class);
        Gate::policy(\App\Models\Applications\JobRequisition::class, \App\Policies\Applications\JobRequisitionPolicy::class);
        Gate::policy(\App\Models\Applications\JobApplication::class, \App\Policies\Applications\JobApplicationPolicy::class);
        Gate::policy(\App\Models\Applications\ApplicationContractNegotiation::class, \App\Policies\Applications\ApplicationContractNegotiationPolicy::class);
        Gate::policy(\App\Models\Applications\EmployeeDraft::class, \App\Policies\Applications\EmployeeDraftPolicy::class);
        // Feature 069: Investitionsplanung (eigene Finanz-Rechte).
        Gate::policy(\App\Models\Investments\InvestmentCase::class, \App\Policies\Investments\InvestmentCasePolicy::class);
        // Feature 070: Krisenmanagement (eigene Rechte + Stab-Notfallzugriff).
        Gate::policy(\App\Models\Crisis\CrisisCase::class, \App\Policies\Crisis\CrisisCasePolicy::class);
        // Feature 071: Nachhaltigkeit/ESG.
        Gate::policy(\App\Models\Sustainability\SustainabilityAssessment::class, \App\Policies\Sustainability\SustainabilityAssessmentPolicy::class);
        // Feature 072: Reklamation/Gewährleistung (getrennte Rollen-Rechte).
        Gate::policy(\App\Models\Claims\ClaimCase::class, \App\Policies\Claims\ClaimCasePolicy::class);
        // Feature 083: Domainverwaltung / DomainReselling (Verbindung, Portfolio, Reseller).
        Gate::policy(\App\Models\Domain\DomainProviderConnection::class, \App\Policies\Domain\DomainProviderConnectionPolicy::class);
        Gate::policy(\App\Models\Domain\DomainProjection::class, \App\Policies\Domain\DomainProjectionPolicy::class);
        Gate::policy(\App\Models\Domain\DomainResellerAccount::class, \App\Policies\Domain\DomainResellerAccountPolicy::class);
        // MVP-456: Personenbeförderung — eine Rechtefamilie für Fahrtakte,
        // Tarife, Konzessionen, Fahrzeugprofile und Schichtabrechnung.
        Gate::policy(\App\Models\Passenger\PassengerRide::class, \App\Policies\Passenger\PassengerRidePolicy::class);
        Gate::policy(\App\Models\Passenger\PassengerFareTariff::class, \App\Policies\Passenger\PassengerRidePolicy::class);
        Gate::policy(\App\Models\Passenger\PassengerConcession::class, \App\Policies\Passenger\PassengerRidePolicy::class);
        Gate::policy(\App\Models\Passenger\PassengerVehicleProfile::class, \App\Policies\Passenger\PassengerRidePolicy::class);
        Gate::policy(\App\Models\Passenger\PassengerShiftSettlement::class, \App\Policies\Passenger\PassengerRidePolicy::class);
        // MVP-459: Druckauftrag — gleiche Rechtefamilie wie die Fertigung
        // (1:1-Spezialisierung), eigener Modelltyp in der Policy.
        Gate::policy(\App\Models\Print\PrintOrder::class, \App\Policies\Print\PrintOrderPolicy::class);
        // Feature 073: Geräte-/Maschinenverleih (Akte + versionierte Preislisten).
        Gate::policy(\App\Models\Rental\RentalCase::class, \App\Policies\Rental\RentalCasePolicy::class);
        Gate::policy(\App\Models\Disposal\DisposalJob::class, \App\Policies\Disposal\DisposalJobPolicy::class);
        Gate::policy(\App\Models\Rental\RentalRateCard::class, \App\Policies\Rental\RentalRateCardPolicy::class);
        // Feature 074: Leasing/Finanzierung (vertrauliche Konditionen).
        Gate::policy(\App\Models\AssetFinance\AssetFinanceContract::class, \App\Policies\AssetFinance\AssetFinanceContractPolicy::class);
        // Feature 075: Prüfmittel/Eichung/Kalibrierung.
        Gate::policy(\App\Models\AssetCompliance\AssetComplianceProfile::class, \App\Policies\AssetCompliance\AssetComplianceProfilePolicy::class);
        // Prüftermine: update-Ability für die Einladung externer Prüfer (MVP-290).
        Gate::policy(\App\Models\AssetCompliance\AssetInspectionSchedule::class, \App\Policies\AssetCompliance\AssetInspectionSchedulePolicy::class);
        Gate::policy(\App\Models\ServiceQueue::class, \App\Policies\ServiceQueuePolicy::class);
        // Servicekatalog (Feature 065, MVP-154): view = Ticket-Sicht, manage = service_catalog.manage.
        Gate::policy(\App\Models\RequestItem::class, \App\Policies\RequestItemPolicy::class);
        // Problem-Management (Feature 065, MVP-156): view = Ticket-Sicht, manage = service_desk.problem.manage.
        Gate::policy(\App\Models\Problem::class, \App\Policies\ProblemPolicy::class);
        // Change-/CAB-Management (Feature 065, MVP-157): view = Ticket-Sicht, manage = service_desk.change.manage.
        Gate::policy(\App\Models\Change::class, \App\Policies\ChangePolicy::class);
        Gate::policy(\App\Models\Chat\Channel::class, \App\Policies\Chat\ChannelPolicy::class);
        Gate::policy(\App\Models\Chat\Message::class, \App\Policies\Chat\MessagePolicy::class);
        Gate::policy(\App\Models\Whistleblowing\WhistleblowingCase::class, \App\Policies\WhistleblowingCasePolicy::class);
        Gate::policy(\App\Models\Integration\WebhookEndpoint::class, \App\Policies\Integration\WebhookEndpointPolicy::class);
        Gate::policy(\App\Models\Location\CustomerGeofence::class, \App\Policies\Location\CustomerGeofencePolicy::class);
        Gate::policy(\App\Models\Isms\IsmsRisk::class, \App\Policies\Isms\IsmsRiskPolicy::class);
        Gate::policy(\App\Models\Isms\IsmsControl::class, \App\Policies\Isms\IsmsControlPolicy::class);
        Gate::policy(\App\Models\Isms\IsmsRequirement::class, \App\Policies\Isms\IsmsRequirementPolicy::class);
        Gate::policy(\App\Models\Isms\IsmsScope::class, \App\Policies\Isms\IsmsScopePolicy::class);
        Gate::policy(\App\Models\Isms\IsmsSoftwareProduct::class, \App\Policies\Isms\IsmsSoftwareProductPolicy::class);
        Gate::policy(\App\Models\Isms\IsmsSoftwareInstallation::class, \App\Policies\Isms\IsmsSoftwareInstallationPolicy::class);
        Gate::policy(\App\Models\Isms\IsmsNormStatus::class, \App\Policies\Isms\IsmsNormStatusPolicy::class);
        Gate::policy(\App\Models\Isms\IsmsAudit::class, \App\Policies\Isms\IsmsAuditPolicy::class);
        Gate::policy(\App\Models\Isms\IsmsManagementReview::class, \App\Policies\Isms\IsmsManagementReviewPolicy::class);
        Gate::policy(\App\Models\Isms\IsmsAuditPackage::class, \App\Policies\Isms\IsmsAuditPackagePolicy::class);
        Gate::policy(\App\Models\Isms\IsmsSecurityIncident::class, \App\Policies\Isms\IsmsSecurityIncidentPolicy::class);
        Gate::policy(\App\Models\Isms\IsmsVulnerability::class, \App\Policies\Isms\IsmsVulnerabilityPolicy::class);
        Gate::policy(\App\Models\Isms\IsmsAdvisory::class, \App\Policies\Isms\IsmsAdvisoryPolicy::class);
        Gate::policy(\App\Models\Isms\IsmsSupplierAssessment::class, \App\Policies\Isms\IsmsSupplierAssessmentPolicy::class);
        // Arbeitsschutz-Register (Feature 132): GBU/Unterweisung/Vorsorge auf den safety.*-Rechten.
        Gate::policy(\App\Models\Safety\HazardAssessment::class, \App\Policies\Safety\HazardAssessmentPolicy::class);
        Gate::policy(\App\Models\Safety\SafetyInstruction::class, \App\Policies\Safety\SafetyInstructionPolicy::class);
        Gate::policy(\App\Models\Safety\SafetyInstructionParticipant::class, \App\Policies\Safety\SafetyInstructionParticipantPolicy::class);
        Gate::policy(\App\Models\Safety\MedicalCheckup::class, \App\Policies\Safety\MedicalCheckupPolicy::class);

        // Provisionen (Feature 146).
        Gate::policy(\App\Models\Sales\CommissionRule::class, \App\Policies\Sales\CommissionRulePolicy::class);
        Gate::policy(\App\Models\Sales\CommissionSettlementRun::class, \App\Policies\Sales\CommissionSettlementRunPolicy::class);

        // Trainingsmanagement (Feature 145).
        Gate::policy(\App\Models\Training\TrainingCourse::class, \App\Policies\Training\TrainingCoursePolicy::class);
        Gate::policy(\App\Models\Training\TrainingRequirement::class, \App\Policies\Training\TrainingRequirementPolicy::class);
        Gate::policy(\App\Models\Training\TrainingAssignment::class, \App\Policies\Training\TrainingAssignmentPolicy::class);
        Gate::policy(\App\Models\ExternalParticipant::class, \App\Policies\ExternalParticipantPolicy::class);
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
        Gate::policy(\App\Models\Permit::class, \App\Policies\PermitPolicy::class);
        Gate::policy(Site::class, SitePolicy::class);
        Gate::policy(Building::class, BuildingPolicy::class);
        Gate::policy(Floor::class, FloorPolicy::class);
        Gate::policy(MaintenancePlan::class, MaintenancePlanPolicy::class);
        Gate::policy(ServiceTicket::class, ServiceTicketPolicy::class);
        Gate::policy(\App\Models\SlaViolation::class, \App\Policies\SlaViolationPolicy::class);
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
        Gate::policy(\App\Models\CustomerQuery::class, \App\Policies\CustomerQueryPolicy::class);
        Gate::policy(\App\Models\SafetyEvent::class, \App\Policies\SafetyEventPolicy::class);
        Gate::policy(\App\Models\Notification\NotificationRule::class, \App\Policies\NotificationRulePolicy::class);
        Gate::policy(\App\Models\Surcharge\SurchargeRule::class, \App\Policies\SurchargeRulePolicy::class);
        Gate::policy(CommunicationNote::class, CommunicationNotePolicy::class);
        Gate::policy(\App\Models\Document::class, \App\Policies\DocumentPolicy::class);
        Gate::policy(\App\Models\KnowledgeArticle::class, \App\Policies\KnowledgeArticlePolicy::class);
        Gate::policy(\App\Models\FormTemplate::class, \App\Policies\FormTemplatePolicy::class);
        Gate::policy(\App\Models\FormSubmission::class, \App\Policies\FormSubmissionPolicy::class);
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
        Gate::policy(Supplier::class, \App\Policies\SupplierPolicy::class);
        Gate::policy(\App\Models\Privacy\ProcessingActivity::class, \App\Policies\Privacy\ProcessingActivityPolicy::class);
        Gate::policy(\App\Models\Privacy\DataSubjectRequest::class, \App\Policies\Privacy\DataSubjectRequestPolicy::class);
        Gate::policy(\App\Models\Privacy\Processor::class, \App\Policies\Privacy\ProcessorPolicy::class);
        Gate::policy(\App\Models\Privacy\ProcessingAgreement::class, \App\Policies\Privacy\ProcessingAgreementPolicy::class);
        Gate::policy(\App\Models\Privacy\Incident::class, \App\Policies\Privacy\IncidentPolicy::class);
        Gate::policy(\App\Models\Privacy\Dpia::class, \App\Policies\Privacy\DpiaPolicy::class);
        Gate::policy(\App\Models\Privacy\TechnicalMeasure::class, \App\Policies\Privacy\TechnicalMeasurePolicy::class);
        Gate::policy(\App\Models\Privacy\JointControllerAgreement::class, \App\Policies\Privacy\JointControllerAgreementPolicy::class);
        Gate::policy(\App\Models\Privacy\ComplianceFinding::class, \App\Policies\Privacy\ComplianceFindingPolicy::class);
        Gate::policy(\App\Models\Finance\BillingTransfer::class, \App\Policies\Finance\BillingTransferPolicy::class);
        Gate::policy(\App\Models\Finance\BankAccount::class, \App\Policies\Finance\BankAccountPolicy::class);
        Gate::policy(\App\Models\Finance\BankStatement::class, \App\Policies\Finance\BankStatementPolicy::class);
        Gate::policy(\App\Models\Finance\BankTransaction::class, \App\Policies\Finance\BankTransactionPolicy::class);
        Gate::policy(\App\Models\Finance\DatevBookingBatch::class, \App\Policies\Finance\DatevBookingBatchPolicy::class);

        // manage-members: Org-Admin darf Mitglieder der eigenen Org verwalten
        Gate::define('manage-members', [OrganizationPolicy::class, 'manageMembers']);

        // manage-access: Verwaltung des Rechte-Bereichs über die feingranulare
        // Permission access.manage, damit auch dedizierte Rechte-Verwalter ohne
        // Org-Admin-Rolle adressierbar sind.
        Gate::define('manage-access', static function (User $user): bool {
            return $user->isAdmin() || $user->hasEffectivePermission('access.manage');
        });

        // manage-plugins: Plugin-Verwaltung + Fehler-Inbox (Review 2026-08, W1c).
        // Route-Middleware `can:manage-plugins` schützt auch künftig ergänzte
        // Controller-Methoden — der frühere Inline-Guard war pro Methode zu vergessen.
        Gate::define('manage-plugins', static function (User $user): bool {
            return $user->isAdmin() && $user->organization_id !== null;
        });

        // Sekundärer Gate::before-Hook: berücksichtigt zusätzlich via Gruppen
        // geerbte Permissions (Spaties eigener Hook prüft nur direkte + über die
        // eigene Rolle erlangte). Nur bei ability mit '.', damit keine
        // Ressource-Policies kurzgeschlossen werden.
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

        // Hinweisgeber-Portal (Abschnitt 19): Anzeige moderat, Absenden streng.
        // IP nur als kurzlebiger, gehashter Cache-Key (datensparsam) – nie im
        // Fachmodell gespeichert.
        RateLimiter::for('wb-view', fn(Request $request) => Limit::perMinute(30)->by('wbv:' . CryptoHelper::hash((string) $request->ip(), HashAlgorithm::SHA1)));
        RateLimiter::for('wb-submit', fn(Request $request) => Limit::perMinute(5)->by('wbs:' . CryptoHelper::hash((string) $request->ip(), HashAlgorithm::SHA1)));
        // Postfach-Login: streng gegen Brute-Force des Geheimnisses.
        RateLimiter::for('wb-login', fn(Request $request) => [
            Limit::perMinute(5)->by('wbl:' . CryptoHelper::hash((string) $request->ip(), HashAlgorithm::SHA1)),
            Limit::perHour(30)->by('wblh:' . CryptoHelper::hash((string) $request->ip(), HashAlgorithm::SHA1)),
        ]);
        // Betroffenen-Selbstmeldeportal (Feature 043, MVP-728): Ansicht
        // moderat, Absenden streng — jede Anfrage loest eine Mail an eine frei
        // eingegebene Adresse aus, deshalb zusaetzlich ein Stundenlimit.
        RateLimiter::for('dsar-view', fn(Request $request) => Limit::perMinute(30)->by('dsv:' . CryptoHelper::hash((string) $request->ip(), HashAlgorithm::SHA1)));
        RateLimiter::for('dsar-submit', fn(Request $request) => [
            Limit::perMinute(3)->by('dss:' . CryptoHelper::hash((string) $request->ip(), HashAlgorithm::SHA1)),
            Limit::perHour(10)->by('dssh:' . CryptoHelper::hash((string) $request->ip(), HashAlgorithm::SHA1)),
        ]);
        // Öffentlicher Karrierebereich (MVP-437): Ansicht großzügig, Bewerbungs-
        // eingang streng gegen Massensendungen — gehashte IP als Cache-Key.
        RateLimiter::for('careers-view', fn(Request $request) => Limit::perMinute(30)->by('crv:' . CryptoHelper::hash((string) $request->ip(), HashAlgorithm::SHA1)));
        // Oeffentlicher OCI-Punchout-Katalog (Feature 099, MVP-457): Browse
        // grosszuegig (Katalog-Blaettern), der Credential-Einstieg streng gegen
        // Brute-Force — gehashte IP als Cache-Key (datensparsam).
        RateLimiter::for('b2b-view', fn(Request $request) => Limit::perMinute(60)->by('b2bv:' . CryptoHelper::hash((string) $request->ip(), HashAlgorithm::SHA1)));
        RateLimiter::for('b2b-login', fn(Request $request) => [
            Limit::perMinute(5)->by('b2bl:' . CryptoHelper::hash((string) $request->ip(), HashAlgorithm::SHA1)),
            Limit::perHour(30)->by('b2blh:' . CryptoHelper::hash((string) $request->ip(), HashAlgorithm::SHA1)),
        ]);
        RateLimiter::for('careers-submit', fn(Request $request) => [
            Limit::perMinute(5)->by('crs:' . CryptoHelper::hash((string) $request->ip(), HashAlgorithm::SHA1)),
            Limit::perHour(20)->by('crsh:' . CryptoHelper::hash((string) $request->ip(), HashAlgorithm::SHA1)),
        ]);

        // Portal-Rückfragen (MVP-512): angemeldete Portalbenutzer, gegen
        // Kommentar-Flut gedeckelt — Schlüssel ist der Portal-Account.
        RateLimiter::for('portal-query', function (Request $request) {
            $userId = (string) ($request->user('customer')?->getAuthIdentifier() ?? 'guest');

            return [
                Limit::perMinute(5)->by('pq:' . $userId),
                Limit::perHour(30)->by('pqh:' . $userId),
            ];
        });

        RateLimiter::for('password', function (Request $request) {
            $userId = (string) ($request->user()?->getAuthIdentifier() ?? 'guest');

            return [
                Limit::perMinute(5)->by('pwd:' . $userId . '|' . $request->ip()),
                Limit::perHour(20)->by('pwd:' . $userId),
            ];
        });

        // Sessionloser Todoist-Webhook (Feature 055, MVP-115): großzügig gegen
        // Bursts, aber gegen Flooding gedeckelt; Verluste heilt der stündliche
        // Polling-Abgleich (todoist:sync).
        RateLimiter::for('todoist-webhook', fn(Request $request) => Limit::perMinute(120)->by('twh:' . $request->ip()));
        // Zeiterfassungs-Webhooks (Feature 124, MVP-613): unauthentifizierter
        // Endpunkt, deshalb gedrosselt. Verlorene Aufrufe heilt das Polling.
        RateLimiter::for('time-tracking-webhook', fn (Request $request) => Limit::perMinute(120)->by('ttwh:' . $request->ip()));

        // Sessionloser Lexoffice-Webhook (Audit 2026-08, Welle 1.3): gleiche
        // Abwägung — Bursts erlauben, Flooding deckeln; Verluste heilt der
        // geplante Pull-Sync.
        RateLimiter::for('lexoffice-webhook', fn(Request $request) => Limit::perMinute(120)->by('lwh:' . $request->ip()));

        // Sessionlose Token-Ingest-Endpunkte (CTI-Webhook, Stempelterminal,
        // Standort-Push; Bauturbo Welle D): pro-IP großzügig (240/min ≈ 4/s) für
        // reale Geräte-/Batch-Bursts, aber gegen Token-Brute-Force auf den
        // Pfad-Token gedeckelt.
        RateLimiter::for('webhook-ingest', fn(Request $request) => Limit::perMinute(240)->by('whi:' . $request->ip()));

        // Stempelterminal-Ingest (MVP-516): zusätzlich zum IP-Limit ein
        // Limit je Gerätetoken — ein amoklaufendes/kopiertes Terminal drosselt
        // nur sich selbst, nicht den ganzen Standort hinter einem NAT.
        RateLimiter::for('terminal-ingest', fn(Request $request) => [
            Limit::perMinute(240)->by('whi:' . $request->ip()),
            Limit::perMinute(120)->by('term:' . \CommonToolkit\Helper\Data\CryptoHelper::hash((string) $request->route('token'))),
        ]);

        // @feature('code') Blade-Direktive (Folge zu MVP-047): Kurzform für
        // @if (app(FeatureFlagResolver::class)->isEnabled('code')); @endfeature.
        \Illuminate\Support\Facades\Blade::if('feature', function (string $code): bool {
            return app(\App\Services\Licensing\FeatureFlagResolver::class)->isEnabled($code);
        });

        // Pro-Request-Nonce für @vite-Script-Tags; derselbe Nonce dient
        // Inline-Scripts (@cspNonce) und dem CSP-Header.
        \Illuminate\Support\Facades\Vite::useCspNonce();

        // @cspNonce → nonce="..."-Attribut für Inline-<script>-Tags (CSP).
        \Illuminate\Support\Facades\Blade::directive('cspNonce', static function (): string {
            return "<?php \$__n = \\Illuminate\\Support\\Facades\\Vite::cspNonce(); echo \$__n ? 'nonce=\"'.e(\$__n).'\"' : ''; ?>";
        });
    }

    /**
     * Stellt dem App-Layout den laufenden Stoppuhr-Eintrag als $stopwatchEntry
     * bereit. Fängt DB-/Infrastruktur-Fehler ab, damit Fehler- und Login-Seite
     * auch bei nicht erreichbarer Datenbank rendern.
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
     * Stellt dem App-Layout die offene Stempelung als $attendanceCurrent
     * bereit (Live-Timer + Clock-in/out im Header-Widget).
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
     * Stellt dem App-Layout die Smart-Reminder ({@see ReminderService::for()})
     * als `$reminderItems` bereit; bei Fehlern leere Liste (Fehlerseite rendert).
     */
    private function registerReminderViewComposer(): void {
        View::composer('layouts.app', function ($view): void {
            $items = [];
            try {
                $user = Auth::user();
                if ($user instanceof User) {
                    // 3–5 Count-Queries je Seitenaufruf (Vollscan 2026-08-23, A7):
                    // je Nutzer + Locale eine Minute cachen — Erinnerungen sind
                    // keine Echtzeitdaten.
                    $items = \Illuminate\Support\Facades\Cache::remember(
                        'reminders:' . (int) $user->id . ':' . app()->getLocale(),
                        \App\Services\Navigation\NavigationRegistry::BADGE_TTL,
                        static fn (): array => app(ReminderService::class)->for($user),
                    );
                }
            } catch (\Throwable $e) {
                report($e);
                $items = [];
            }
            $view->with('reminderItems', $items);
        });
    }

    /**
     * Stellt dem App-Layout die flachen JS-Übersetzungen
     * ({@see JsTranslationProvider}) als `$jsTranslations` für den
     * Client-Side-`__()`-Helper bereit.
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
     * Stellt allen Layout- und PDF-Views den BrandingService als `$branding`
     * bereit (kein eigenes resolve nötig).
     */
    private function registerBrandingViewComposer(): void {
        View::composer(['layouts.*', 'auth.*', 'pdf.*', 'reports.pdf.*', 'reports.drilldown.pdf.*', 'components.pdf-layout'], function ($view): void {
            try {
                $branding = app(BrandingService::class);
            } catch (\Throwable $e) {
                report($e);
                $branding = null;
            }
            $view->with('branding', $branding);
        });

        // ThemeService als `$theme` für das App-Layout (Theme-Injektion +
        // Anti-Flash-Seed) und die Auth-Seiten. PDFs nutzen kein Runtime-Theme.
        View::composer(['layouts.*', 'auth.*'], function ($view): void {
            try {
                $theme = app(\App\Services\ThemeService::class);
            } catch (\Throwable $e) {
                report($e);
                $theme = null;
            }
            $view->with('theme', $theme);
        });
    }

    /** Stellt dem App-Layout die Lesezeichen des Users als `$userBookmarks` bereit (Phase H). */
    private function registerBookmarksViewComposer(): void {
        View::composer('layouts.app', function ($view): void {
            $user = Auth::user();
            $bookmarks = $user instanceof User ? $user->bookmarks()->get() : collect();
            $view->with('userBookmarks', $bookmarks);
        });
    }

    /**
     * Registriert die Dashboard-Kacheln.
     *
     * Seit dem Umbau des Dashboards (2026-08) sind auch die vormals fest
     * verdrahteten Blöcke (KPI-Zeile, Tabs Überblick/Aufgaben/Aktivität/
     * Finanzen) Kacheln — dadurch kann jeder Nutzer sie unter „Dashboard
     * anpassen" ausblenden und umsortieren, und Organisationen können eine
     * Vorgabe setzen. Reihenfolge hier ist unerheblich; die Vorgabe-Position
     * liefert Widget::defaultOrder().
     */
    private function registerDashboardWidgets(): void {
        /** @var \App\Dashboard\WidgetRegistry $registry */
        $registry = $this->app->make(\App\Dashboard\WidgetRegistry::class);

        foreach ([
            // Überblick
            \App\Dashboard\Widgets\OnboardingWidget::class,
            \App\Dashboard\Widgets\PersonalKpisWidget::class,
            \App\Dashboard\Widgets\TeamKpisWidget::class,
            \App\Dashboard\Widgets\TodayShiftsWidget::class,
            \App\Dashboard\Widgets\UpcomingShiftsWidget::class,
            \App\Dashboard\Widgets\ScheduledShiftsWidget::class,
            \App\Dashboard\Widgets\RecentEmergenciesWidget::class,
            \App\Dashboard\Widgets\BookmarksWidget::class,

            // Zeit
            \App\Dashboard\Widgets\AttendanceClockWidget::class,
            \App\Dashboard\Widgets\StopwatchWidget::class,
            \App\Dashboard\Widgets\FlexBalanceWidget::class,
            \App\Dashboard\Widgets\TimeAccountsWidget::class,
            \App\Dashboard\Widgets\TimeCorrectionsWidget::class,

            // Aufgaben & Aktivität
            \App\Dashboard\Widgets\RemindersWidget::class,
            \App\Dashboard\Widgets\OpenIssuesWidget::class,
            \App\Dashboard\Widgets\RecentEntriesWidget::class,
            \App\Dashboard\Widgets\RecentCommentsWidget::class,
            \App\Dashboard\Widgets\RecentAttachmentsWidget::class,
            \App\Dashboard\Widgets\TeamActivityWidget::class,
            \App\Dashboard\Widgets\KanbanStatusWidget::class,
            \App\Dashboard\Widgets\ServiceTicketsWidget::class,
            \App\Dashboard\Widgets\ChatUnreadWidget::class,
            \App\Dashboard\Widgets\ApprovalsWidget::class,

            // Finanzen
            \App\Dashboard\Widgets\FinanceWidget::class,
            \App\Dashboard\Widgets\VacationFlexWidget::class,
            \App\Dashboard\Widgets\OpenTimesWidget::class,
            \App\Dashboard\Widgets\OpenItemsWidget::class,
            \App\Dashboard\Widgets\TaxFilingsWidget::class,

            // Fristen & Betrieb
            \App\Dashboard\Widgets\AssetComplianceWidget::class,
            \App\Dashboard\Widgets\AssetBlocksWidget::class,
            \App\Dashboard\Widgets\ContractDeadlinesWidget::class,
            \App\Dashboard\Widgets\LeasingDeadlinesWidget::class,
            \App\Dashboard\Widgets\SafetyDueWidget::class,
            \App\Dashboard\Widgets\TrainingDueWidget::class,
            \App\Dashboard\Widgets\DataProtectionWidget::class,
            \App\Dashboard\Widgets\OperationsTasksWidget::class,
            \App\Dashboard\Widgets\IntegrationInboxWidget::class,
            \App\Dashboard\Widgets\BackupStatusWidget::class,
            \App\Dashboard\Widgets\PluginHealthWidget::class,
        ] as $widget) {
            $registry->register($this->app->make($widget));
        }
    }
}
