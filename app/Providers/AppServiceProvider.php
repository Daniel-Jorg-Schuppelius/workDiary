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
        $this->app->bind(\App\Services\Weather\Contracts\WeatherProvider::class, static function (): \App\Services\Weather\Contracts\WeatherProvider {
            return match (Setting::get('weather.provider', 'open-meteo')) {
                'dwd' => new \App\Services\Weather\DwdProvider(new \GuzzleHttp\Client),
                default => new \App\Services\Weather\OpenMeteoProvider(new \GuzzleHttp\Client),
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
        $this->app->singleton(\App\Services\Privacy\Retention\RetentionRegistry::class, function (): \App\Services\Privacy\Retention\RetentionRegistry {
            $registry = new \App\Services\Privacy\Retention\RetentionRegistry;

            // CTI-Anrufmetadaten (Vollaudit 2026-07, M18): Rufnummer aus
            // Referenz-Payload und Notiz-Betreff anonymisieren; Richtung/
            // Zeitpunkt/Dauer bleiben als Vorgangsnachweis.
            $registry->register(new \App\Services\Privacy\Retention\RetentionPolicy(
                area: 'cti_calls',
                modelClass: \App\Models\ExternalReference::class,
                overdueQuery: fn($organization, $cutoff) => \App\Models\ExternalReference::query()
                    ->forPlugin($organization->id, \App\Services\Cti\CtiCallService::PLUGIN_ID, \App\Services\Cti\CtiCallService::EXTERNAL_TYPE)
                    ->where('synced_at', '<', $cutoff)
                    ->whereRaw("json_extract(payload, '$.anonymized') is null"),
                purge: function (\App\Models\ExternalReference $subject): void {
                    $payload = (array) $subject->payload;
                    unset($payload['number']);
                    $subject->forceFill(['payload' => [...$payload, 'anonymized' => true]])->save();
                    $note = $subject->referenceable;
                    if ($note instanceof CommunicationNote) {
                        $note->forceFill(['subject' => (string) __('Anruf (anonymisiert)')])->save();
                    }
                },
            ));

            // Ideenkarten im Papierkorb (Vollaudit 2026-07, M21): soft-gelöschte
            // Karten nach Frist endgültig entfernen (Knoten/Links/Shares kaskadieren).
            $registry->register(new \App\Services\Privacy\Retention\RetentionPolicy(
                area: 'idea_maps',
                modelClass: \App\Models\IdeaMap::class,
                overdueQuery: fn($organization, $cutoff) => \App\Models\IdeaMap::query()
                    ->withoutGlobalScopes()
                    ->onlyTrashed()
                    ->where('organization_id', $organization->id)
                    ->where('deleted_at', '<', $cutoff),
                purge: function (\App\Models\IdeaMap $subject): void {
                    $subject->forceDelete();
                },
            ));

            // Fehlerberichte mit Seitenkontext-PII (Vollaudit 2026-07, N15).
            $registry->register(new \App\Services\Privacy\Retention\RetentionPolicy(
                area: 'problem_reports',
                modelClass: \App\Models\ProblemReport::class,
                overdueQuery: fn($organization, $cutoff) => \App\Models\ProblemReport::query()
                    ->withoutGlobalScopes()
                    ->where('organization_id', $organization->id)
                    ->where('status', \App\Enums\Support\ProblemReportStatus::Closed->value)
                    ->where('updated_at', '<', $cutoff),
                purge: function (\App\Models\ProblemReport $subject): void {
                    foreach ($subject->attachments()->get() as $attachment) {
                        \Illuminate\Support\Facades\Storage::disk($attachment->disk)->delete((string) $attachment->path);
                        $attachment->delete();
                    }
                    $subject->delete();
                },
            ));

            // Führerscheinkontrollen (Vollaudit 2026-07, N24): nach Nachweisfrist
            // löschen — Vorschlag über den Review-Scan, keine Direktlöschung.
            $registry->register(new \App\Services\Privacy\Retention\RetentionPolicy(
                area: 'driver_license_checks',
                modelClass: \App\Models\DriverLicenseCheck::class,
                overdueQuery: fn($organization, $cutoff) => \App\Models\DriverLicenseCheck::query()
                    ->withoutGlobalScopes()
                    ->where('organization_id', $organization->id)
                    ->where('checked_at', '<', $cutoff),
                purge: function (\App\Models\DriverLicenseCheck $subject): void {
                    $subject->delete();
                },
            ));

            // Abgeschlossene Betroffenenanfragen nach Nachweisfrist.
            $registry->register(new \App\Services\Privacy\Retention\RetentionPolicy(
                area: 'privacy_requests',
                modelClass: \App\Models\Privacy\DataSubjectRequest::class,
                overdueQuery: fn($organization, $cutoff) => \App\Models\Privacy\DataSubjectRequest::query()
                    ->withoutGlobalScopes()
                    ->where('organization_id', $organization->id)
                    ->whereNotNull('closed_at')
                    ->where('closed_at', '<', $cutoff),
                purge: function (\App\Models\Privacy\DataSubjectRequest $subject): void {
                    foreach ($subject->attachments()->get() as $attachment) {
                        \Illuminate\Support\Facades\Storage::disk('local')->delete((string) $attachment->path);
                        $attachment->delete();
                    }
                    $subject->delete();
                },
            ));

            // Bewerbungen (Feature 068, MVP-192): purge anonymisiert
            // (Kennzahlen bleiben, PII verschwindet).
            $registry->register(new \App\Services\Privacy\Retention\RetentionPolicy(
                area: 'applications',
                modelClass: \App\Models\Applications\JobApplication::class,
                overdueQuery: fn($organization, $cutoff) => \App\Models\Applications\JobApplication::query()
                    ->withoutGlobalScopes()
                    ->where('organization_id', $organization->id)
                    ->whereNull('anonymized_at')
                    ->whereNotNull('retention_until')
                    ->where('retention_until', '<=', now()->toDateString()),
                purge: function (\App\Models\Applications\JobApplication $subject): void {
                    $subject->interviews()->update(['notes' => null]);
                    $subject->reviews()->update(['comment' => null]);
                    $subject->forceFill([
                        'candidate_name' => null,
                        'email' => null,
                        'phone' => null,
                        'email_hash' => null,
                        'notes' => null,
                        'status' => 'deleted',
                        'anonymized_at' => now(),
                    ])->save();
                },
            ));

            // Reklamationsakten (Feature 072, MVP-256): abgeschlossene Fälle
            // nach Ablauf anonymisieren (Melder-PII), Kennzahlen bleiben.
            $registry->register(new \App\Services\Privacy\Retention\RetentionPolicy(
                area: 'claims',
                modelClass: \App\Models\Claims\ClaimCase::class,
                overdueQuery: fn($organization, $cutoff) => \App\Models\Claims\ClaimCase::query()
                    ->withoutGlobalScopes()
                    ->where('organization_id', $organization->id)
                    ->whereNull('anonymized_at')
                    ->whereNotNull('closed_at')
                    ->where('closed_at', '<', $cutoff),
                purge: function (\App\Models\Claims\ClaimCase $subject): void {
                    $subject->forceFill([
                        'reporter_name' => null,
                        'reporter_email' => null,
                        'anonymized_at' => now(),
                    ])->save();
                },
            ));

            // Lohn-/Zeitexporte inkl. abgelegter Dateien. Vollaudit 2026-07
            // (N6): Purge auditiert jetzt als export.deleted und räumt Zeilen mit.
            $registry->register(new \App\Services\Privacy\Retention\RetentionPolicy(
                area: 'exports',
                modelClass: TimeExport::class,
                overdueQuery: fn($organization, $cutoff) => TimeExport::query()
                    ->withoutGlobalScopes()
                    ->where('organization_id', $organization->id)
                    ->where('created_at', '<', $cutoff),
                purge: function (TimeExport $subject): void {
                    $subject->audit('export.deleted', ['reason' => 'retention', 'file_path' => $subject->file_path]);
                    $path = (string) ($subject->file_path ?? '');
                    if ($path !== '') {
                        \Illuminate\Support\Facades\Storage::disk('local')->delete($path);
                    }
                    $subject->lines()->delete();
                    $subject->delete();
                },
            ));

            // Eingangsrechnungen im DMS — GoBD-Ausnahme: solange nicht
            // archiviert, gilt das Dokument als in Verwendung (kein Vorschlag).
            $registry->register(new \App\Services\Privacy\Retention\RetentionPolicy(
                area: 'documents_invoice',
                modelClass: \App\Models\Document::class,
                overdueQuery: fn($organization, $cutoff) => \App\Models\Document::query()
                    ->withoutGlobalScopes()
                    ->where('organization_id', $organization->id)
                    ->where('document_type', \App\Enums\Document\DocumentType::Invoice->value)
                    ->where('created_at', '<', $cutoff),
                exempt: fn($subject): ?string => $subject->getAttribute('status') !== \App\Enums\Document\DocumentStatus::Archived
                    ? 'Noch nicht archiviert — Dokument gilt als in Verwendung (GoBD).'
                    : null,
            ));

            // Fahrtakten (MVP-456, Konzept §11): abgeschlossene Fahrten werden
            // nach Frist anonymisiert — Orts-/Fahrgastfelder genullt (encrypted-
            // Regel: NULL, nie ""), Beträge/Steuer/Zeiten bleiben als Nachweis.
            $registry->register(new \App\Services\Privacy\Retention\RetentionPolicy(
                area: 'passenger_rides',
                modelClass: \App\Models\Passenger\PassengerRide::class,
                overdueQuery: fn($organization, $cutoff) => \App\Models\Passenger\PassengerRide::query()
                    ->withoutGlobalScopes()
                    ->where('organization_id', $organization->id)
                    ->whereIn('status', array_values(array_map(
                        static fn(\App\Enums\Passenger\RideStatus $status): string => $status->value,
                        array_filter(\App\Enums\Passenger\RideStatus::cases(), static fn(\App\Enums\Passenger\RideStatus $status): bool => $status->isFinal()),
                    )))
                    ->whereNull('anonymized_at')
                    ->where(fn($query) => $query
                        ->where('completed_at', '<', $cutoff)
                        ->orWhere('cancelled_at', '<', $cutoff)),
                purge: function (\App\Models\Passenger\PassengerRide $subject): void {
                    $subject->forceFill([
                        'pickup_address' => null,
                        'destination_address' => null,
                        'waypoints' => null,
                        'passenger_name' => null,
                        'passenger_contact' => null,
                        'route_note' => null,
                        'closing_note' => null,
                        'anonymized_at' => now(),
                    ])->save();
                    $subject->audit('passenger.ride_anonymized', ['reason' => 'retention']);
                },
            ));

            return $registry;
        });

        // Hinweisgeber-Anhang-Scanner: Treiber per Konfiguration (Default: kein
        // Scanner → fail-safe Quarantaene). Tests koennen einen Fake binden.
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

        // Versand-Provider (Feature 059, MVP-128): Carrier-Plugins registrieren
        // ihren ShippingProvider beim Booten, der ShipmentService löst darüber auf.
        $this->app->singleton(\App\Services\Shipping\ShippingProviderRegistry::class);

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
            \Illuminate\Console\Events\ScheduledTaskSkipped::class,
            [\App\Scheduling\ScheduleRunRecorder::class, 'handleSkipped'],
        );

        // Stichtags-Rekonstruktion (Nachtrag 046b): jede Bewertungsänderung
        // (SoA-Aussage, Norm-Konformitätsstatus) erzeugt einen append-only
        // Snapshot — Model-Events, damit auch Service-Updates erfasst werden.
        \App\Models\Isms\IsmsApplicabilityStatement::saved(
            fn($model) => app(\App\Services\Isms\AssessmentSnapshotService::class)->record($model),
        );
        \App\Models\Isms\IsmsNormStatus::saved(
            fn($model) => app(\App\Services\Isms\AssessmentSnapshotService::class)->record($model),
        );

        // Task→Board-Sync (Feature 064, P3): Statuswechsel außerhalb des Boards
        // schiebt das Work-Item in die erste Spalte der Zielkategorie. Kein
        // Task-Write hier → keine Endlos-Schleife mit AgileBoardService::move().
        Task::saved(function (Task $task): void {
            if (! $task->wasChanged('status')) {
                return;
            }
            $item = \App\Models\Agile\AgileWorkItem::query()->where('task_id', $task->id)->first();
            if ($item === null) {
                return;
            }
            $rawStatus = $task->getAttribute('status');
            $status = (string) ($rawStatus instanceof \BackedEnum ? $rawStatus->value : $rawStatus);
            $currentCategory = $item->column?->category?->value;
            if ($currentCategory === $status) {
                return;
            }
            $target = \App\Models\Agile\AgileBoardColumn::query()
                ->where('board_id', $item->board_id)
                ->where('category', $status)
                ->orderBy('position')
                ->first();
            if ($target === null || (int) $target->id === (int) $item->column_id) {
                return;
            }
            $from = $item->column_id;
            \App\Models\Agile\AgileWorkItem::query()->whereKey($item->id)
                ->update(['column_id' => $target->id, 'lock_version' => \Illuminate\Support\Facades\DB::raw('lock_version + 1')]);
            \App\Models\Agile\AgileEvent::record([
                'organization_id' => $item->organization_id,
                'board_id' => $item->board_id,
                'work_item_id' => $item->id,
                'event' => 'column.moved',
                'payload' => ['from' => $from, 'to' => $target->id, 'origin' => 'task_sync'],
                'created_at' => now(),
            ]);
        });

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
        Attachment::observe(AttachmentObserver::class);
        Customer::observe(CustomerObserver::class);
        // Supplier/ForeignCustomer: Audit-Logging via Auditable-Trait, kein Observer mehr (A1).
        EmergencyAssignment::observe(EmergencyAssignmentObserver::class);
        DiaryEntry::observe(DiaryEntryObserver::class);
        Tag::observe(TagObserver::class);
        User::observe(UserObserver::class);
        TimeEntry::observe(TimeEntryObserver::class);
        // Rückrichtung der Zeit-Plugins: ein Observer für alle Quellen, statt je
        // Plugin einer — jeder würde sonst dieselbe Referenz-Abfrage fahren.
        TimeEntry::observe(\App\Plugins\Support\TimeWritebackObserver::class);
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
        Gate::policy(\App\Models\InvoiceTemplate::class, \App\Policies\InvoiceTemplatePolicy::class);
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

        // Sessionlose Token-Ingest-Endpunkte (CTI-Webhook, Stempelterminal,
        // Standort-Push; Bauturbo Welle D): pro-IP großzügig (240/min ≈ 4/s) für
        // reale Geräte-/Batch-Bursts, aber gegen Token-Brute-Force auf den
        // Pfad-Token gedeckelt.
        RateLimiter::for('webhook-ingest', fn(Request $request) => Limit::perMinute(240)->by('whi:' . $request->ip()));

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

    /** Registriert die Standard-Dashboard-Widgets in der Registry (Phase G). */
    private function registerDashboardWidgets(): void {
        /** @var \App\Dashboard\WidgetRegistry $registry */
        $registry = $this->app->make(\App\Dashboard\WidgetRegistry::class);

        // Personal-/Team-KPIs, Finance, Urlaub/Flex, Schichten, Notdienste und
        // Onboarding rendert das Tab-Dashboard fest → hier NICHT als Widget
        // registriert (sonst doppelt); Klassen bleiben für spätere Reaktivierung
        // (Entscheidung bestätigt: Vollreview 2026-07-29, W2.4 — reaktivieren
        // oder löschen entscheidet ein künftiges Dashboard-Feature).
        $registry->register($this->app->make(\App\Dashboard\Widgets\BookmarksWidget::class));
        $registry->register($this->app->make(\App\Dashboard\Widgets\DataProtectionWidget::class));
        // Aufgabencenter-Kachel (Feature 041/MVP-058, nachgezogen als B3/MVP-344).
        $registry->register($this->app->make(\App\Dashboard\Widgets\OperationsTasksWidget::class));
    }
}
