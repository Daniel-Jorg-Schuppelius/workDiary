<?php
/*
 * Created on   : Wed Apr 29 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : web.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

use App\Http\Controllers\{AccountPasswordController, ActivityCategoryController, AdminTimeEntryController, ApiTokenController, ArchiveController, AssetController, AttachmentController, AttendanceController, AuditLogController, BrandingController, CalendarFeedController, CommentController, CommunicationNoteController, CoverageRequirementController, CustomerController, DashboardController, DiaryCaseFileController, DiaryController, DiaryExportController, DutyController, DutyPlanController, EmergencyAssignmentController, EnergyLogController, EventCategoryController, EventController, EventParticipantController, ExpenseApprovalController, ExpenseController, FlexController, FlexEligibilityController, ForeignCustomerController, GeocodeController, GlobalSearchController, HelpController, HolidayController, HomeController, IcsFeedController, InvoiceController, KanbanController, LicenseController, LocaleController, MaterialController, MilestoneController, OnCallShiftController, OnboardingController, OpenIssueController, OrgMemberController, OrganizationController, OrganizationSwitchController, PayrollController, PerDiemTripController, PrintController, ProfileController, ProjectBillingRuleController, ProjectController, ProjectRecurrenceRuleController, ProtocolController, PublicProtocolSignatureController, PublicSignatureController, PushSubscriptionController, QualificationController, RoomController, ScheduleController, ScheduleImportController, ScheduledShiftController, ShiftTypeController, SickLeaveController, SoftwareController, SoftwareInstallationController, StopwatchController, SupplierController, TagController, TaskController, TeamController, TimeEntryCommentController, TimeEntryController, TimesheetController, TimesheetEntryController, TimesheetMaterialController, TimesheetSignatureController, TodayController, TourController, TravelLogController, UserBookmarkController, VacationController, VehicleController, WeekController, WorkScheduleController};
use App\Http\Controllers\Admin\Access\{AccessHubController, MemberController as AccessMemberController, PermissionController as AccessPermissionController, RoleController as AccessRoleController, UserGroupController as AccessUserGroupController};
use App\Http\Controllers\Admin\{AutomationRuleController, BackupHeartbeatController, BranchProfileController, ClassificationController, ClassificationRequirementController, DemoTenantController, DiagnosticsController, EntryTypeController, ExpenseCategoryController, ImportController, InvoiceMailTemplateController, LicenseAdminController, MetricsController, PerDiemRateController, PluginController as AdminPluginController, PluginErrorController as AdminPluginErrorController, PrivacyController, SupportAccessAuditController, SupportReportController};
use App\Http\Controllers\Asset\MaintenancePlanController;
use App\Http\Controllers\Auth\{LoginController, PasswordResetController, TenantRegistrationController, TwoFactorChallengeController};
use App\Http\Controllers\KeyHandover\KeyHandoverController;
use App\Http\Controllers\MeterReading\MeterReadingController;
use App\Http\Controllers\Reporting\{AbsencesReportController, AssetAnalysisReportController, AttendanceReportController, AuditActivityReportController, BillingReportController, CoverageReportController, CustomerAnalysisReportController, CustomerProjectReportController, EntryTypeAnalysisReportController, EntryTypeDrilldownReportController, ExpenseReportController, ExternalPayoutReportController, FleetReportController, MaterialReportController, MonthByUserTeamReportController, MyMonthReportController, MyYearReportController, OnCallReportController, OperationsReportController, ProjectDetailsReportController, ProjectInactiveReportController, QualificationReportController, SicknessReportController, WeekByUserReportController, WorkBalanceReportController};
use App\Http\Controllers\ServiceTicket\ServiceTicketController;
use App\Http\Controllers\TwoFactorController;
use App\Http\Controllers\UI\DateRangeController;
use Illuminate\Support\Facades\Route;

// Projekt-Bindung: erlaubt numerische ID (Backward-Compat) bzw. opake Sqid
// ODER die zusammengesetzte URL "<kunde-slug>/<projekt-slug>" (Sentinel
// "intern" für Projekte ohne Kunden). Siehe Project::getRouteKey().
//
// ACHTUNG: Das erste Alternativ-Segment [A-Za-z0-9]+ deckt sowohl numerische
// IDs als auch Sqids ab und ist damit an ein rein alphanumerisches Sqid-
// Alphabet gekoppelt (config/sqids.php). Wird SQIDS_ALPHABET auf Zeichen
// außerhalb [A-Za-z0-9] gesetzt, matchen Projekt-Sqids dieses Pattern nicht
// mehr → 404. Der Test SqidRoutePatternTest sichert diese Annahme ab.
Route::pattern('project', '[A-Za-z0-9]+|[a-z0-9-]+/[a-z0-9-]+');

// Lizenz-Aktivierung (umgeht EnsureValidLicense via bypass_paths)
Route::get('/license', [LicenseController::class, 'show'])->name('license.show');
Route::post('/license', [LicenseController::class, 'store'])->middleware('throttle:6,1')->name('license.store');

// Startseite (öffentlich)
Route::get('/', HomeController::class)->name('home');

// Auth
Route::get('/login', [LoginController::class, 'showLoginForm'])->name('login')->middleware('guest');
Route::post('/login', [LoginController::class, 'login'])->middleware(['guest', 'throttle:login']);
Route::post('/logout', [LoginController::class, 'logout'])->name('logout');

// Zweiter Login-Schritt (Zwei-Faktor): session-basiert (auth.2fa.id), daher
// weder guest- noch auth-Middleware. Der Controller prüft die Session selbst.
Route::get('/two-factor-challenge', [TwoFactorChallengeController::class, 'create'])->name('two-factor.login');
Route::post('/two-factor-challenge', [TwoFactorChallengeController::class, 'store'])->middleware('throttle:login')->name('two-factor.login.attempt');
Route::post('/two-factor-challenge/email', [TwoFactorChallengeController::class, 'email'])->middleware('throttle:login')->name('two-factor.login.email');
Route::post('/two-factor-challenge/webauthn/options', [TwoFactorChallengeController::class, 'webauthnOptions'])->name('two-factor.login.webauthn.options');
Route::post('/two-factor-challenge/webauthn', [TwoFactorChallengeController::class, 'webauthnVerify'])->middleware('throttle:login')->name('two-factor.login.webauthn');

Route::get('/register', [TenantRegistrationController::class, 'showForm'])->name('register')->middleware('guest');
Route::post('/register', [TenantRegistrationController::class, 'register'])->middleware(['guest', 'throttle:register']);

// Passwort vergessen (self-contained Reset-Flow, Gast).
Route::middleware('guest')->group(function (): void {
    Route::get('/password/forgot', [PasswordResetController::class, 'request'])->name('password.request');
    Route::post('/password/forgot', [PasswordResetController::class, 'email'])->middleware('throttle:6,1')->name('password.email');
    Route::get('/password/reset/{token}', [PasswordResetController::class, 'reset'])->name('password.reset');
    Route::post('/password/reset', [PasswordResetController::class, 'update'])->middleware('throttle:6,1')->name('password.update');
});

Route::post('/locale/{locale}', [LocaleController::class, 'switch'])->name('locale.switch');

// Öffentlicher ICS-Feed (nur Visibility=Public Events)
Route::get('calendar/public.ics', [IcsFeedController::class, 'public'])->name('events.ics.public');

// Tokenisierter persönlicher Schedule-Feed (Urlaube + Schichten).
// Throttle als Brute-Force-/Abuse-Schutz (Feed wird von Kalender-Clients gepollt).
Route::get('calendar/feed/{token}.ics', [IcsFeedController::class, 'personalSchedule'])
    ->middleware('throttle:120,1')
    ->name('calendar.feed.personal');

// Öffentlicher Stundenzettel-Sign-Link (Magic-Token)
Route::get('sign/timesheet/{token}', [PublicSignatureController::class, 'show'])
    ->middleware('throttle:30,1')
    ->name('timesheets.public-sign');
Route::post('sign/timesheet/{token}', [PublicSignatureController::class, 'store'])
    ->middleware('throttle:6,1')
    ->name('timesheets.public-sign.submit');
Route::get('sign/timesheet/thanks', [PublicSignatureController::class, 'thanks'])->name('timesheets.public-thanks');

// Öffentlicher Protokoll-Signaturlink (MVP-022 §3.3)
Route::get('sign/protocol/{token}', [PublicProtocolSignatureController::class, 'show'])
    ->middleware('throttle:30,1')
    ->name('protocols.public-sign');
Route::post('sign/protocol/{token}', [PublicProtocolSignatureController::class, 'store'])
    ->middleware('throttle:6,1')
    ->name('protocols.public-sign.submit');

// Backup-Heartbeat (MVP-046 §5): externer Endpoint mit Bearer-Token,
// daher außerhalb von Auth-Gruppen, mit CSRF-Ausnahme (siehe bootstrap/app.php).
Route::post('admin/backup/heartbeat', [BackupHeartbeatController::class, 'store'])
    ->middleware('throttle:60,1')
    ->name('admin.backup.heartbeat');

// Tagebuch (nur für eingeloggte Benutzer)
Route::middleware('auth')->group(function () {
    // Diese Routen sind für jeden eingeloggten User erreichbar – auch für
    // Legacy-only-Accounts, die sonst keinen Zugriff auf das neue System
    // haben. Ohne sie könnten sie weder den Modus zurückwechseln noch ihr
    // Passwort/Profil verwalten.
    Route::post('/mode/{mode}', [HomeController::class, 'switchMode'])->name('mode.switch');

    Route::get('account/password', [AccountPasswordController::class, 'edit'])->name('account.password.edit');
    Route::post('account/password', [AccountPasswordController::class, 'update'])->middleware('throttle:password')->name('account.password.update');

    // Zwei-Faktor-Authentifizierung (Selbstverwaltung).
    Route::get('account/two-factor', [TwoFactorController::class, 'show'])->name('account.2fa.show');
    Route::post('account/two-factor', [TwoFactorController::class, 'enable'])->name('account.2fa.enable');
    Route::post('account/two-factor/confirm', [TwoFactorController::class, 'confirm'])->name('account.2fa.confirm');
    Route::post('account/two-factor/recovery-codes', [TwoFactorController::class, 'regenerateRecoveryCodes'])->name('account.2fa.recovery');
    Route::post('account/two-factor/email', [TwoFactorController::class, 'enableEmail'])->name('account.2fa.email.enable');
    Route::post('account/two-factor/email/resend', [TwoFactorController::class, 'resendEmailCode'])->name('account.2fa.email.resend');
    Route::post('account/two-factor/email/confirm', [TwoFactorController::class, 'confirmEmail'])->name('account.2fa.email.confirm');
    Route::post('account/two-factor/webauthn/options', [TwoFactorController::class, 'webauthnOptions'])->name('account.2fa.webauthn.options');
    Route::post('account/two-factor/webauthn', [TwoFactorController::class, 'webauthnRegister'])->name('account.2fa.webauthn.register');
    Route::delete('account/two-factor/credential/{credential}', [TwoFactorController::class, 'removeCredential'])->name('account.2fa.credential.destroy');
    Route::delete('account/two-factor', [TwoFactorController::class, 'disable'])->name('account.2fa.disable');

    Route::get('account/profile', [ProfileController::class, 'edit'])->name('account.profile.edit');
    Route::put('account/profile', [ProfileController::class, 'update'])->name('account.profile.update');

    // Notification-Center (MVP-018): eigene Benachrichtigungen, keine
    // Permission nötig — Controller arbeitet ausschließlich auf auth()->user().
    Route::get('notifications', [\App\Http\Controllers\NotificationCenterController::class, 'index'])->name('notifications.index');
    Route::post('notifications/read-all', [\App\Http\Controllers\NotificationCenterController::class, 'readAll'])->name('notifications.readAll');
    Route::post('notifications/{id}/read', [\App\Http\Controllers\NotificationCenterController::class, 'read'])->name('notifications.read');

    // Persönliche Lesezeichen (Phase H)
    Route::get('account/bookmarks', [UserBookmarkController::class, 'index'])->name('bookmarks.index');
    Route::get('account/bookmarks/create', [UserBookmarkController::class, 'create'])->name('bookmarks.create');
    Route::post('account/bookmarks', [UserBookmarkController::class, 'store'])->name('bookmarks.store');
    Route::get('account/bookmarks/{bookmark}/edit', [UserBookmarkController::class, 'edit'])->name('bookmarks.edit');
    Route::put('account/bookmarks/{bookmark}', [UserBookmarkController::class, 'update'])->name('bookmarks.update');
    Route::delete('account/bookmarks/{bookmark}', [UserBookmarkController::class, 'destroy'])->name('bookmarks.destroy');

    // Filter-Presets (Folge-Iteration zu Phase H).
    Route::get('account/filter-presets', [\App\Http\Controllers\UserFilterPresetController::class, 'index'])->name('filter-presets.index');
    Route::post('account/filter-presets', [\App\Http\Controllers\UserFilterPresetController::class, 'store'])->name('filter-presets.store');
    Route::put('account/filter-presets/{preset}', [\App\Http\Controllers\UserFilterPresetController::class, 'update'])->name('filter-presets.update');
    Route::delete('account/filter-presets/{preset}', [\App\Http\Controllers\UserFilterPresetController::class, 'destroy'])->name('filter-presets.destroy');

    // Dashboard-Widget-Konfiguration (Phase G).
    Route::get('me/dashboard/customize', [\App\Http\Controllers\Me\DashboardCustomizationController::class, 'index'])
        ->name('dashboard.customize');
    Route::post('me/dashboard/customize', [\App\Http\Controllers\Me\DashboardCustomizationController::class, 'save'])
        ->name('dashboard.customize.save');

    // Persönlicher Kalender-Feed (Token-Generierung + Subscribe-URL).
    Route::get('account/calendar', [CalendarFeedController::class, 'show'])
        ->name('account.calendar.show');
    Route::post('account/calendar/rotate', [CalendarFeedController::class, 'rotate'])
        ->name('account.calendar.rotate');
    Route::delete('account/calendar', [CalendarFeedController::class, 'revoke'])
        ->name('account.calendar.revoke');

    // Alle folgenden Routen gehören zum neuen System und sind nur für
    // dort freigeschaltete User (is_new_system=true) bzw. Admins erreichbar.
    Route::middleware(['access.new', \App\Http\Middleware\EnforcePlanModules::class])->group(function () {
        Route::get('dashboard', [DashboardController::class, '__invoke'])->name('dashboard');

        // ── Hinweisgeber: interne Fallbearbeitung (Phase 3) ─────────────────
        // Autorisierung pro Aktion ueber WhistleblowingCasePolicy (Permission
        // UND Fall-Zuweisung). {case} wird org-scoped ueber public_id gebunden.
        Route::prefix('compliance/meldungen')
            ->name('whistleblowing.internal.')
            ->middleware(\App\Http\Middleware\Whistleblowing\RequireMeldestelleTwoFactor::class)
            ->group(function (): void {
            Route::get('/', [\App\Http\Controllers\Whistleblowing\InternalCaseController::class, 'index'])->name('index');
            Route::get('{case}', [\App\Http\Controllers\Whistleblowing\InternalCaseController::class, 'show'])->name('show');
            Route::post('{case}/eingang', [\App\Http\Controllers\Whistleblowing\InternalCaseController::class, 'acknowledge'])->name('acknowledge');
            Route::post('{case}/status', [\App\Http\Controllers\Whistleblowing\InternalCaseController::class, 'status'])->name('status');
            Route::post('{case}/zuweisungen', [\App\Http\Controllers\Whistleblowing\InternalCaseController::class, 'assign'])->name('assign');
            Route::post('{case}/notizen', [\App\Http\Controllers\Whistleblowing\InternalCaseController::class, 'note'])->name('note');
            Route::post('{case}/nachrichten', [\App\Http\Controllers\Whistleblowing\InternalCaseController::class, 'message'])->name('message');
            Route::post('{case}/konflikt', [\App\Http\Controllers\Whistleblowing\InternalCaseController::class, 'conflict'])->name('conflict');
            Route::post('{case}/notfallzugriff', [\App\Http\Controllers\Whistleblowing\InternalCaseController::class, 'emergency'])->name('emergency');
            Route::post('{case}/betroffene', [\App\Http\Controllers\Whistleblowing\InternalCaseController::class, 'subject'])->name('subject');
            Route::get('{case}/anhaenge/{attachment}', [\App\Http\Controllers\Whistleblowing\InternalAttachmentController::class, 'download'])->name('attachment');
            Route::post('{case}/export', [\App\Http\Controllers\Whistleblowing\InternalCaseController::class, 'export'])->name('export');
            Route::post('{case}/loeschen', [\App\Http\Controllers\Whistleblowing\InternalCaseController::class, 'destroy'])->name('destroy');
        });

        // ── Hinweisgeber: Portal-Verwaltung (Permission settings.manage) ─────
        Route::prefix('compliance/portal')
            ->name('whistleblowing.portal.')
            ->middleware(\App\Http\Middleware\Whistleblowing\RequireMeldestelleTwoFactor::class)
            ->group(function (): void {
                Route::get('/', [\App\Http\Controllers\Whistleblowing\WhistleblowingPortalController::class, 'edit'])->name('edit');
                Route::put('/', [\App\Http\Controllers\Whistleblowing\WhistleblowingPortalController::class, 'update'])->name('update');
                Route::post('/slug', [\App\Http\Controllers\Whistleblowing\WhistleblowingPortalController::class, 'rotateSlug'])->name('rotate');
            });

        // ── Datenschutzmanagement (Feature 043, MVP 1) ──────────────────────
        // Modul-Gate via module.datenschutz (config/plans.routes); Autorisierung
        // pro Aktion ueber die Privacy-Policies (Rolle `datenschutz`).
        Route::prefix('compliance/datenschutz')->name('dataprotection.')->group(function (): void {
            // VVT (Verzeichnis von Verarbeitungstaetigkeiten)
            Route::get('vvt', [\App\Http\Controllers\Privacy\ProcessingActivityController::class, 'index'])->name('activities.index');
            Route::get('vvt/neu', [\App\Http\Controllers\Privacy\ProcessingActivityController::class, 'create'])->name('activities.create');
            Route::get('vvt/export', [\App\Http\Controllers\Privacy\ProcessingActivityController::class, 'export'])->name('activities.export');
            Route::post('vvt', [\App\Http\Controllers\Privacy\ProcessingActivityController::class, 'store'])->name('activities.store');
            Route::get('vvt/{activity}', [\App\Http\Controllers\Privacy\ProcessingActivityController::class, 'show'])->name('activities.show');
            Route::post('vvt/{activity}/version', [\App\Http\Controllers\Privacy\ProcessingActivityController::class, 'addVersion'])->name('activities.version');
            Route::post('vvt/{activity}/pruefung', [\App\Http\Controllers\Privacy\ProcessingActivityController::class, 'submitReview'])->name('activities.submit');
            Route::post('vvt/{activity}/freigabe', [\App\Http\Controllers\Privacy\ProcessingActivityController::class, 'approve'])->name('activities.approve');

            // Betroffenenanfragen (DSR)
            Route::get('anfragen', [\App\Http\Controllers\Privacy\DataSubjectRequestController::class, 'index'])->name('requests.index');
            Route::get('anfragen/neu', [\App\Http\Controllers\Privacy\DataSubjectRequestController::class, 'create'])->name('requests.create');
            Route::post('anfragen', [\App\Http\Controllers\Privacy\DataSubjectRequestController::class, 'store'])->name('requests.store');
            Route::get('anfragen/{request}', [\App\Http\Controllers\Privacy\DataSubjectRequestController::class, 'show'])->name('requests.show');
            Route::get('anfragen/{request}/export', [\App\Http\Controllers\Privacy\DataSubjectRequestController::class, 'export'])->name('requests.export');
            Route::post('anfragen/{request}/identitaet', [\App\Http\Controllers\Privacy\DataSubjectRequestController::class, 'verifyIdentity'])->name('requests.verify');
            Route::post('anfragen/{request}/zuweisung', [\App\Http\Controllers\Privacy\DataSubjectRequestController::class, 'assign'])->name('requests.assign');
            Route::post('anfragen/{request}/entscheidung', [\App\Http\Controllers\Privacy\DataSubjectRequestController::class, 'decide'])->name('requests.decide');

            // ── MVP 2: Dienstleister-/AVV-Register (Art. 28) ─────────────────
            Route::get('dienstleister', [\App\Http\Controllers\Privacy\ProcessorController::class, 'index'])->name('processors.index');
            Route::get('dienstleister/neu', [\App\Http\Controllers\Privacy\ProcessorController::class, 'create'])->name('processors.create');
            Route::post('dienstleister', [\App\Http\Controllers\Privacy\ProcessorController::class, 'store'])->name('processors.store');
            Route::get('dienstleister/{processor}', [\App\Http\Controllers\Privacy\ProcessorController::class, 'show'])->name('processors.show');
            Route::put('dienstleister/{processor}', [\App\Http\Controllers\Privacy\ProcessorController::class, 'update'])->name('processors.update');

            Route::get('avv', [\App\Http\Controllers\Privacy\ProcessingAgreementController::class, 'index'])->name('agreements.index');
            Route::post('avv', [\App\Http\Controllers\Privacy\ProcessingAgreementController::class, 'store'])->name('agreements.store');
            Route::get('avv/{agreement}', [\App\Http\Controllers\Privacy\ProcessingAgreementController::class, 'show'])->name('agreements.show');
            Route::get('avv/{agreement}/dokument', [\App\Http\Controllers\Privacy\ProcessingAgreementController::class, 'downloadDocument'])->name('agreements.document');
            Route::post('avv/{agreement}/aktivieren', [\App\Http\Controllers\Privacy\ProcessingAgreementController::class, 'activate'])->name('agreements.activate');
            Route::post('avv/{agreement}/kuendigen', [\App\Http\Controllers\Privacy\ProcessingAgreementController::class, 'terminate'])->name('agreements.terminate');
            Route::post('avv/{agreement}/rueckgabe', [\App\Http\Controllers\Privacy\ProcessingAgreementController::class, 'confirmReturn'])->name('agreements.return');
            Route::post('avv/{agreement}/taetigkeiten', [\App\Http\Controllers\Privacy\ProcessingAgreementController::class, 'syncActivities'])->name('agreements.activities');
            Route::post('avv/{agreement}/subprozessoren', [\App\Http\Controllers\Privacy\ProcessingAgreementController::class, 'storeSubprocessor'])->name('agreements.subprocessor.store');
            Route::post('avv/{agreement}/subprozessoren/{subprocessor}/freigabe', [\App\Http\Controllers\Privacy\ProcessingAgreementController::class, 'approveSubprocessor'])->name('agreements.subprocessor.approve');

            // ── MVP 3: Datenschutzvorfaelle (Art. 33/34) + Massnahmen ────────
            Route::get('vorfaelle', [\App\Http\Controllers\Privacy\IncidentController::class, 'index'])->name('incidents.index');
            Route::get('vorfaelle/neu', [\App\Http\Controllers\Privacy\IncidentController::class, 'create'])->name('incidents.create');
            Route::post('vorfaelle', [\App\Http\Controllers\Privacy\IncidentController::class, 'store'])->name('incidents.store');
            Route::get('vorfaelle/{incident}', [\App\Http\Controllers\Privacy\IncidentController::class, 'show'])->name('incidents.show');
            Route::post('vorfaelle/{incident}/bewertung', [\App\Http\Controllers\Privacy\IncidentController::class, 'assess'])->name('incidents.assess');
            Route::post('vorfaelle/{incident}/meldeentscheidung', [\App\Http\Controllers\Privacy\IncidentController::class, 'decideNotification'])->name('incidents.decide');
            Route::post('vorfaelle/{incident}/gemeldet', [\App\Http\Controllers\Privacy\IncidentController::class, 'markReported'])->name('incidents.reported');
            Route::post('vorfaelle/{incident}/behoerdenmeldung', [\App\Http\Controllers\Privacy\IncidentController::class, 'recordAuthorityReport'])->name('incidents.authority-report');
            Route::post('vorfaelle/{incident}/kunde-informiert', [\App\Http\Controllers\Privacy\IncidentController::class, 'notifyController'])->name('incidents.notify-controller');
            Route::post('vorfaelle/{incident}/abschluss', [\App\Http\Controllers\Privacy\IncidentController::class, 'close'])->name('incidents.close');
            Route::post('vorfaelle/{incident}/massnahmen', [\App\Http\Controllers\Privacy\IncidentController::class, 'storeMeasure'])->name('incidents.measure.store');
            Route::post('vorfaelle/{incident}/massnahmen/{measure}/erledigt', [\App\Http\Controllers\Privacy\IncidentController::class, 'completeMeasure'])->name('incidents.measure.complete');

            // DSFA (Art. 35) je Verarbeitungstaetigkeit
            Route::post('vvt/{activity}/dsfa', [\App\Http\Controllers\Privacy\DpiaController::class, 'store'])->name('activities.dpia');

            // TOM-Katalog (Art. 32)
            Route::get('tom', [\App\Http\Controllers\Privacy\TechnicalMeasureController::class, 'index'])->name('tom.index');
            Route::get('tom/neu', [\App\Http\Controllers\Privacy\TechnicalMeasureController::class, 'create'])->name('tom.create');
            Route::post('tom', [\App\Http\Controllers\Privacy\TechnicalMeasureController::class, 'store'])->name('tom.store');
            Route::get('tom/{measure}', [\App\Http\Controllers\Privacy\TechnicalMeasureController::class, 'show'])->name('tom.show');
            Route::post('tom/{measure}/version', [\App\Http\Controllers\Privacy\TechnicalMeasureController::class, 'addVersion'])->name('tom.version');
            Route::post('tom/{measure}/freigabe', [\App\Http\Controllers\Privacy\TechnicalMeasureController::class, 'approve'])->name('tom.approve');
            Route::post('tom/{measure}/zuordnung', [\App\Http\Controllers\Privacy\TechnicalMeasureController::class, 'assignActivity'])->name('tom.assign');
            Route::post('tom/{measure}/pruefung', [\App\Http\Controllers\Privacy\TechnicalMeasureController::class, 'review'])->name('tom.review');

            // GVV – Gemeinsam Verantwortliche (Art. 26)
            Route::get('gvv', [\App\Http\Controllers\Privacy\JointControllerAgreementController::class, 'index'])->name('gvv.index');
            Route::post('gvv', [\App\Http\Controllers\Privacy\JointControllerAgreementController::class, 'store'])->name('gvv.store');
            Route::get('gvv/{gvv}', [\App\Http\Controllers\Privacy\JointControllerAgreementController::class, 'show'])->name('gvv.show');
            Route::put('gvv/{gvv}', [\App\Http\Controllers\Privacy\JointControllerAgreementController::class, 'update'])->name('gvv.update');
            Route::get('gvv/{gvv}/dokument', [\App\Http\Controllers\Privacy\JointControllerAgreementController::class, 'downloadDocument'])->name('gvv.document');
            Route::post('gvv/{gvv}/taetigkeiten', [\App\Http\Controllers\Privacy\JointControllerAgreementController::class, 'syncActivities'])->name('gvv.activities');

            // Compliance-/Lueckenanalyse
            Route::get('luecken', [\App\Http\Controllers\Privacy\ComplianceController::class, 'index'])->name('compliance.index');
            Route::post('luecken/analyse', [\App\Http\Controllers\Privacy\ComplianceController::class, 'run'])->name('compliance.run');
            Route::put('luecken/{finding}', [\App\Http\Controllers\Privacy\ComplianceController::class, 'update'])->name('compliance.update');

            // Anhaenge an Fallakten (Anfragen/Vorfaelle)
            Route::post('anfragen/{dsr}/anhaenge', [\App\Http\Controllers\Privacy\PrivacyAttachmentController::class, 'storeForRequest'])->name('requests.attach');
            Route::post('vorfaelle/{incident}/anhaenge', [\App\Http\Controllers\Privacy\PrivacyAttachmentController::class, 'storeForIncident'])->name('incidents.attach');
            Route::get('anhaenge/{attachment}', [\App\Http\Controllers\Privacy\PrivacyAttachmentController::class, 'download'])->name('attachment.download');
            Route::delete('anhaenge/{attachment}', [\App\Http\Controllers\Privacy\PrivacyAttachmentController::class, 'destroy'])->name('attachment.destroy');

            // Meldungsentwuerfe (Vorfall) + TOM-Zuordnung zu AVV
            Route::get('vorfaelle/{incident}/meldung/{kind}', [\App\Http\Controllers\Privacy\IncidentController::class, 'reportDraft'])->name('incidents.draft');
            Route::post('avv/{agreement}/tom', [\App\Http\Controllers\Privacy\ProcessingAgreementController::class, 'assignMeasure'])->name('agreements.tom');
        });

        // ── ISMS / ISO-27001-Auditbereitschaft (Feature 044, MVP 1) ────────
        // Modul-Gate via module.isms (config/plans.routes, NUR Enterprise);
        // Autorisierung pro Aktion ueber die Isms-Policies (isms.viewAny/
        // view fuer Lesen+SoA, isms.manage fuer Pflege/Katalog-Import).
        Route::prefix('compliance/isms')->name('isms.')->group(function (): void {
            // Risikoregister
            Route::get('risiken', [\App\Http\Controllers\Isms\RiskController::class, 'index'])->name('risks.index');
            Route::get('risiken/neu', [\App\Http\Controllers\Isms\RiskController::class, 'create'])->name('risks.create');
            Route::post('risiken', [\App\Http\Controllers\Isms\RiskController::class, 'store'])->name('risks.store');
            Route::get('risiken/{risk}/bearbeiten', [\App\Http\Controllers\Isms\RiskController::class, 'edit'])->name('risks.edit');
            Route::put('risiken/{risk}', [\App\Http\Controllers\Isms\RiskController::class, 'update'])->name('risks.update');
            Route::post('risiken/{risk}/status', [\App\Http\Controllers\Isms\RiskController::class, 'transition'])->name('risks.transition');
            Route::delete('risiken/{risk}', [\App\Http\Controllers\Isms\RiskController::class, 'destroy'])->name('risks.destroy');

            // Massnahmenkatalog (Controls) inkl. Annex-A-Import
            Route::get('massnahmen', [\App\Http\Controllers\Isms\ControlController::class, 'index'])->name('controls.index');
            Route::get('massnahmen/neu', [\App\Http\Controllers\Isms\ControlController::class, 'create'])->name('controls.create');
            Route::post('massnahmen', [\App\Http\Controllers\Isms\ControlController::class, 'store'])->name('controls.store');
            Route::post('massnahmen/katalog', [\App\Http\Controllers\Isms\ControlController::class, 'import'])->name('controls.import');
            Route::get('massnahmen/{control}/bearbeiten', [\App\Http\Controllers\Isms\ControlController::class, 'edit'])->name('controls.edit');
            Route::put('massnahmen/{control}', [\App\Http\Controllers\Isms\ControlController::class, 'update'])->name('controls.update');
            Route::delete('massnahmen/{control}', [\App\Http\Controllers\Isms\ControlController::class, 'destroy'])->name('controls.destroy');

            // Statement of Applicability (druckbare Read-Only-Sicht)
            Route::get('soa', \App\Http\Controllers\Isms\SoaController::class)->name('soa');
        });

        Route::get('onboarding', [OnboardingController::class, '__invoke'])->name('onboarding.index');
        Route::post('onboarding/steps/{step}/skip', [OnboardingController::class, 'skipStep'])->name('onboarding.steps.skip');
        Route::post('onboarding/widget/dismiss', [OnboardingController::class, 'dismissWidget'])->name('onboarding.widget.dismiss');

        // In-App-Hilfe (MVP-051): topic-Code muss Punkte zulassen (z. B. "diary-entries.create").
        Route::get('help/search', [HelpController::class, 'search'])->name('help.search');
        Route::get('help/topics/{topic}', [HelpController::class, 'show'])
            ->where('topic', '[a-z0-9.\-]+')
            ->name('help.topics.show');
        Route::post('help/topics/{topic}/feedback', [HelpController::class, 'feedback'])
            ->where('topic', '[a-z0-9.\-]+')
            ->middleware('throttle:30,1')
            ->name('help.topics.feedback');

        // Diagnose-Seite (MVP-044)
        Route::get('admin/diagnostics', [DiagnosticsController::class, 'index'])->name('admin.diagnostics.index');
        Route::get('admin/diagnostics.json', [DiagnosticsController::class, 'json'])->name('admin.diagnostics.json');
        Route::post('admin/diagnostics/test-mail', [DiagnosticsController::class, 'testMail'])
            ->middleware('throttle:6,1')
            ->name('admin.diagnostics.test-mail');

        // Betriebsmetriken (Feature 036)
        Route::get('admin/metrics', [MetricsController::class, 'index'])->name('admin.metrics.index');

        // Supportbericht (MVP-045)
        Route::get('admin/support/report', [SupportReportController::class, 'index'])->name('admin.support.report.index');
        Route::post('admin/support/report', [SupportReportController::class, 'generate'])
            ->middleware('throttle:3,1')
            ->name('admin.support.report.generate');

        // Supportzugriffe-Audit (MVP-004)
        Route::get('admin/support/access-audit', [SupportAccessAuditController::class, 'index'])
            ->name('admin.support.access-audit.index');

        // Lizenz-Admin (MVP-047)
        Route::get('admin/license', [LicenseAdminController::class, 'index'])->name('admin.license.index');
        Route::post('admin/license/flags/{flag}/toggle', [LicenseAdminController::class, 'toggleFlag'])
            ->where('flag', '[A-Za-z0-9._-]+')
            ->name('admin.license.flags.toggle');
        Route::post('admin/license/org', [LicenseAdminController::class, 'installOrg'])->name('admin.license.org.install');
        Route::delete('admin/license/org', [LicenseAdminController::class, 'removeOrg'])->name('admin.license.org.remove');
        Route::post('admin/license/org/issue', [LicenseAdminController::class, 'issueOrg'])->name('admin.license.org.issue');
        Route::get('admin/license/issuer', [LicenseAdminController::class, 'issuer'])->name('admin.license.issuer');
        Route::post('admin/license/issuer', [LicenseAdminController::class, 'issueKey'])->name('admin.license.issuer.create');

        // Demo-Mandant (MVP-050)
        Route::get('admin/demo', [DemoTenantController::class, 'index'])->name('admin.demo.index');
        Route::post('admin/demo/seed', [DemoTenantController::class, 'seed'])
            ->middleware('throttle:3,1')
            ->name('admin.demo.seed');
        Route::post('admin/demo/reset', [DemoTenantController::class, 'reset'])
            ->middleware('throttle:3,1')
            ->name('admin.demo.reset');

        // Datenschutzseite (MVP-005)
        Route::get('admin/privacy', [PrivacyController::class, 'index'])->name('admin.privacy.index');
        Route::get('admin/privacy/export', [PrivacyController::class, 'export'])->name('admin.privacy.export');
        Route::delete('admin/privacy/sessions/{id}', [PrivacyController::class, 'destroySession'])
            ->where('id', '[A-Za-z0-9\-_]+')
            ->name('admin.privacy.sessions.destroy');
        Route::delete('admin/privacy/tokens/{id}', [PrivacyController::class, 'destroyToken'])
            ->where('id', '\d+')
            ->name('admin.privacy.tokens.destroy');

        Route::get('diary/export.csv', [DiaryExportController::class, 'csv'])->name('diary.export.csv');
        Route::get('diary/export.pdf', [DiaryExportController::class, 'pdf'])->name('diary.export.pdf');

        Route::resource('holidays', HolidayController::class)->except('show');

        Route::resource('diary', DiaryController::class)->parameters(['diary' => 'diary']);
        Route::post('diary/{diary}/archive', [DiaryController::class, 'archive'])->name('diary.archive');
        Route::post('diary/{diary}/restore', [DiaryController::class, 'restore'])->name('diary.restore');
        Route::get('diary/{diary}/case-file', DiaryCaseFileController::class)->name('diary.case-file');
        Route::post('diary/{diary}/comments', [CommentController::class, 'store'])->name('diary.comments.store');
        Route::post('time-entries/{timeEntry}/comments', [TimeEntryCommentController::class, 'store'])->name('time-entries.comments.store');
        Route::put('comments/{comment}', [CommentController::class, 'update'])->name('comments.update');
        Route::delete('comments/{comment}', [CommentController::class, 'destroy'])->name('comments.destroy');

        Route::post('attachments/{type}/{id}', [AttachmentController::class, 'store'])
            ->whereIn('type', ['diary', 'comment', 'shift', 'assignment', 'task', 'customer', 'organization', 'user', 'asset'])
            ->name('attachments.store');
        Route::get('attachments/{attachment}/download', [AttachmentController::class, 'download'])->name('attachments.download');
        Route::delete('attachments/{attachment}', [AttachmentController::class, 'destroy'])->name('attachments.destroy');
        Route::delete('attachments/{type}/{id}/meta/{meta}', [AttachmentController::class, 'destroyMeta'])
            ->whereIn('type', ['organization', 'user'])
            ->whereIn('meta', ['logo', 'logo_dark', 'avatar'])
            ->name('attachments.destroyMeta');

        Route::get('week', WeekController::class)->name('week.index');
        Route::get('calendar', [\App\Http\Controllers\CalendarController::class, 'index'])->name('calendar.index');
        Route::get('calendar/events', [\App\Http\Controllers\CalendarController::class, 'events'])->name('calendar.events');

        Route::get('kanban', [KanbanController::class, 'index'])->name('kanban.index');
        Route::patch('kanban/{entry}/status', [KanbanController::class, 'updateStatus'])->name('kanban.status');

        Route::get('duties', [DutyController::class, 'index'])->name('duties.index');

        Route::resource('duty-plans', DutyPlanController::class)->parameters(['duty-plans' => 'dutyPlan']);
        Route::patch('duty-plans/{dutyPlan}/publish', [DutyPlanController::class, 'publish'])->name('duty-plans.publish');
        Route::patch('duty-plans/{dutyPlan}/retract', [DutyPlanController::class, 'retract'])->name('duty-plans.retract');

        // ── Soll-Besetzung pro DutyPlan ──────────────────────────────────────────
        Route::resource('duty-plans.coverage', CoverageRequirementController::class)
            ->parameters(['duty-plans' => 'dutyPlan', 'coverage' => 'requirement'])
            ->except(['show']);

        // ── Druck-Layouts (HTML mit @media print, A4/A3) ────────────────────────
        Route::prefix('print')->name('print.')->group(function (): void {
            Route::get('duty-plans/{dutyPlan}/roster', [PrintController::class, 'dutyPlanRoster'])->name('duty-plan.roster');
            Route::get('duty-plans/{dutyPlan}/week', [PrintController::class, 'dutyPlanWeek'])->name('duty-plan.week');
            Route::get('duty-plans/{dutyPlan}/day-briefing', [PrintController::class, 'dutyPlanDayBriefing'])->name('duty-plan.day');
            Route::get('users/{user}/month', [PrintController::class, 'userMonth'])->name('user.month');
            Route::get('on-call', [PrintController::class, 'onCall'])->name('on-call');
            Route::get('vacations', [PrintController::class, 'vacationYear'])->name('vacations');
        });

        Route::get('shifts', fn() => redirect()->route('duties.index'))->name('shifts.index');
        Route::get('assignments', fn() => redirect()->route('duties.index', ['tab' => 'notdienst']))->name('assignments.index');
        Route::resource('shifts', OnCallShiftController::class)->except(['show', 'index'])->parameters(['shifts' => 'shift']);
        Route::resource('assignments', EmergencyAssignmentController::class)->except(['show', 'index'])->parameters(['assignments' => 'assignment']);

        Route::get('vacations', fn() => redirect()->route('duties.index', ['tab' => 'urlaub']))->name('vacations.index');
        Route::resource('vacations', VacationController::class)->except(['show', 'index']);
        Route::patch('vacations/{vacation}/approve', [VacationController::class, 'approve'])->name('vacations.approve');
        Route::patch('vacations/{vacation}/reject', [VacationController::class, 'reject'])->name('vacations.reject');
        Route::get('vacations/{vacation}/reject-form', [VacationController::class, 'rejectForm'])->name('vacations.reject-form');
        Route::patch('vacations/{vacation}/cancel', [VacationController::class, 'cancel'])->name('vacations.cancel');

        Route::get('sick-leaves', fn() => redirect()->route('duties.index', ['tab' => 'krank']))->name('sick-leaves.index');
        Route::resource('sick-leaves', SickLeaveController::class)->except(['show', 'index'])
            ->parameters(['sick-leaves' => 'sick_leave']);
        Route::patch('sick-leaves/{sick_leave}/cancel', [SickLeaveController::class, 'cancel'])->name('sick-leaves.cancel');
        Route::get('sick-leaves/{sick_leave}/attachments/{attachment}/download', [SickLeaveController::class, 'downloadAttachment'])
            ->name('sick-leaves.attachments.download');

        Route::resource('tags', TagController::class)->except('show');

        // ── Veranstaltungen / Schulungen ─────────────────────────────────────────
        Route::get('events/calendar', [EventController::class, 'calendar'])->name('events.calendar');
        Route::patch('events/{event}/cancel', [EventController::class, 'cancel'])->name('events.cancel');
        Route::resource('events', EventController::class);

        Route::post('events/{event}/respond', [EventParticipantController::class, 'respond'])->name('events.respond');
        Route::patch('events/{event}/participants/{user}/attended', [EventParticipantController::class, 'markAttended'])
            ->name('events.participants.attended');
        Route::patch('events/{event}/participants/{user}/no-show', [EventParticipantController::class, 'markNoShow'])
            ->name('events.participants.no-show');
        Route::patch('events/{event}/participants/{user}/status', [EventParticipantController::class, 'updateStatus'])
            ->name('events.participants.status');
        Route::post('events/{event}/participants/{user}/certificate', [EventParticipantController::class, 'issueCertificate'])
            ->name('events.participants.certificate');

        Route::resource('event-categories', EventCategoryController::class)
            ->except('show')
            ->parameters(['event-categories' => 'category']);
        Route::resource('rooms', RoomController::class)->except('show');

        // ── Liegenschaften (Standort → Gebäude → Geschoss) ──────────────────
        Route::resource('sites', \App\Http\Controllers\SiteController::class);
        Route::resource('buildings', \App\Http\Controllers\BuildingController::class);
        Route::resource('floors', \App\Http\Controllers\FloorController::class);

        Route::get('calendar/events.ics', [IcsFeedController::class, 'personal'])->name('events.ics.personal');

        // ── Kunden (Kimai-style customers) ──────────────────────────────────────
        Route::get('customers/export', [CustomerController::class, 'export'])->name('customers.export');
        Route::get('customers/import', [CustomerController::class, 'importForm'])->name('customers.import.form');
        Route::post('customers/import', [CustomerController::class, 'import'])->name('customers.import');
        Route::resource('customers', CustomerController::class);
        Route::post('customers/{customer}/archive', [CustomerController::class, 'archive'])->name('customers.archive');
        Route::post('customers/{customer}/restore', [CustomerController::class, 'restore'])->name('customers.restore');

        // ── Fremdkunden (Endkunden einer Firma) ──────────────────────────────────
        Route::resource('foreign-customers', ForeignCustomerController::class)->parameters(['foreign-customers' => 'foreignCustomer']);
        Route::post('foreign-customers/{foreignCustomer}/archive', [ForeignCustomerController::class, 'archive'])->name('foreign-customers.archive');
        Route::post('foreign-customers/{foreignCustomer}/restore', [ForeignCustomerController::class, 'restore'])->name('foreign-customers.restore');
        Route::post('foreign-customers/{foreignCustomer}/promote', [ForeignCustomerController::class, 'promote'])->name('foreign-customers.promote');

        // ── Lieferanten (Suppliers) ─────────────────────────────────────────────
        Route::get('suppliers/export', [SupplierController::class, 'export'])->name('suppliers.export');
        Route::resource('suppliers', SupplierController::class);
        Route::post('suppliers/{supplier}/archive', [SupplierController::class, 'archive'])->name('suppliers.archive');
        Route::post('suppliers/{supplier}/restore', [SupplierController::class, 'restore'])->name('suppliers.restore');

        // Plugin-spezifische Routen (z. B. Lexoffice customers.lexoffice.*) werden
        // vom jeweiligen Plugin-ServiceProvider geladen — siehe app/Plugins/*/routes.php.

        // ── Plugin-Übersicht (Admin) ────────────────────────────────────────────
        Route::get('admin/plugins', [AdminPluginController::class, 'index'])->name('admin.plugins.index');
        Route::get('admin/plugins/{plugin}', [AdminPluginController::class, 'edit'])->name('admin.plugins.edit');
        Route::put('admin/plugins/{plugin}', [AdminPluginController::class, 'update'])->name('admin.plugins.update');
        Route::post('admin/plugins/{plugin}/toggle', [AdminPluginController::class, 'toggle'])->name('admin.plugins.toggle');
        Route::post('admin/plugins/{plugin}/health-check', [AdminPluginController::class, 'healthCheck'])->name('admin.plugins.health-check');
        Route::post('admin/plugins/{plugin}/reset-errors', [AdminPluginErrorController::class, 'reset'])->name('admin.plugins.reset-errors');

        // ── Plugin-Fehler-Inbox (Admin) ─────────────────────────────────────────
        Route::get('admin/plugin-errors', [AdminPluginErrorController::class, 'index'])->name('admin.plugin-errors.index');
        Route::get('admin/plugin-errors/{pluginError}', [AdminPluginErrorController::class, 'show'])->name('admin.plugin-errors.show');
        Route::post('admin/plugin-errors/{pluginError}/acknowledge', [AdminPluginErrorController::class, 'acknowledge'])->name('admin.plugin-errors.acknowledge');

        // ── Rechnungs-Mail-Templates (Admin) ─────────────────────────────────────
        Route::resource('admin/invoice-mail-templates', InvoiceMailTemplateController::class)
            ->except(['show'])
            ->names('admin.invoice-mail-templates')
            ->parameters(['admin/invoice-mail-templates' => 'invoiceMailTemplate']);

        Route::resource('projects', ProjectController::class);
        Route::get('projects/{project}/planning', [ProjectController::class, 'planning'])->name('projects.planning');
        Route::resource('projects.milestones', MilestoneController::class)->except(['index', 'show']);
        Route::resource('projects.tasks', TaskController::class)->except(['index', 'show']);
        Route::get('teams/{team}/workload', [TeamController::class, 'workload'])->name('teams.workload');
        Route::patch('projects/{project}/tasks/{task}/schedule', [TaskController::class, 'schedule'])->name('projects.tasks.schedule');

        // Globale Aufgaben (Activities ohne Projekt)
        Route::get('tasks/global', [\App\Http\Controllers\GlobalTaskController::class, 'index'])->name('tasks.global.index');
        Route::get('tasks/global/create', [\App\Http\Controllers\GlobalTaskController::class, 'create'])->name('tasks.global.create');
        Route::post('tasks/global', [\App\Http\Controllers\GlobalTaskController::class, 'store'])->name('tasks.global.store');
        Route::get('tasks/global/{task}/edit', [\App\Http\Controllers\GlobalTaskController::class, 'edit'])->name('tasks.global.edit');
        Route::put('tasks/global/{task}', [\App\Http\Controllers\GlobalTaskController::class, 'update'])->name('tasks.global.update');
        Route::delete('tasks/global/{task}', [\App\Http\Controllers\GlobalTaskController::class, 'destroy'])->name('tasks.global.destroy');

        // ── Rechnungen / Invoicing ────────────────────────────────────
        Route::get('invoices', [InvoiceController::class, 'index'])->name('invoices.index');
        Route::get('invoices/create', [InvoiceController::class, 'create'])->name('invoices.create');
        Route::post('invoices', [InvoiceController::class, 'store'])->name('invoices.store');
        Route::get('invoices/{invoice}', [InvoiceController::class, 'show'])->name('invoices.show');
        Route::delete('invoices/{invoice}', [InvoiceController::class, 'destroy'])->name('invoices.destroy');
        Route::post('invoices/{invoice}/issue', [InvoiceController::class, 'issue'])->name('invoices.issue');
        Route::post('invoices/{invoice}/pay', [InvoiceController::class, 'pay'])->name('invoices.pay');
        Route::post('invoices/{invoice}/cancel', [InvoiceController::class, 'cancel'])->name('invoices.cancel');
        Route::post('invoices/{invoice}/credit-note', [InvoiceController::class, 'creditNote'])->name('invoices.credit-note');
        Route::get('invoices/{invoice}/send', [InvoiceController::class, 'sendForm'])->name('invoices.send.form');
        Route::post('invoices/{invoice}/send', [InvoiceController::class, 'send'])->name('invoices.send');
        Route::get('invoices/{invoice}/pdf', [InvoiceController::class, 'pdf'])->name('invoices.pdf');
        Route::get('invoices/{invoice}/expenses', [InvoiceController::class, 'expensesForm'])->name('invoices.expenses.form');
        Route::post('invoices/{invoice}/expenses', [InvoiceController::class, 'attachExpenses'])->name('invoices.expenses.attach');
        Route::get('invoices/{invoice}/items/create', [InvoiceController::class, 'itemForm'])->name('invoices.items.create');
        Route::post('invoices/{invoice}/items', [InvoiceController::class, 'addItem'])->name('invoices.items.store');
        Route::get('invoices/{invoice}/items/{item}/edit', [InvoiceController::class, 'itemForm'])->name('invoices.items.edit');
        Route::put('invoices/{invoice}/items/{item}', [InvoiceController::class, 'updateItem'])->name('invoices.items.update');
        Route::delete('invoices/{invoice}/items/{item}', [InvoiceController::class, 'removeItem'])->name('invoices.items.destroy');
        Route::resource('invoice-templates', \App\Http\Controllers\InvoiceTemplateController::class)
            ->except(['show'])
            ->parameters(['invoice-templates' => 'template']);

        // ── Faktura-Übergabe (Feature 045, Teil B) ──────────────────────────────
        // Routen MÜSSEN finance.* heißen (Plan-Gating 'finance.*' → module.finance
        // in config/plans.php). Autorisierung über BillingTransferPolicy.
        Route::prefix('finanzen/uebergaben')->name('finance.transfers.')->group(function (): void {
            Route::get('/', [\App\Http\Controllers\Finance\FinanceTransferController::class, 'index'])->name('index');
            Route::get('neu', [\App\Http\Controllers\Finance\FinanceTransferController::class, 'create'])->name('create');
            Route::post('/', [\App\Http\Controllers\Finance\FinanceTransferController::class, 'store'])->name('store');
            Route::get('{transfer}', [\App\Http\Controllers\Finance\FinanceTransferController::class, 'show'])->name('show');
            Route::post('{transfer}/bestaetigen', [\App\Http\Controllers\Finance\FinanceTransferController::class, 'confirm'])->name('confirm');
            Route::post('{transfer}/uebertragen', [\App\Http\Controllers\Finance\FinanceTransferController::class, 'execute'])->name('execute');
            Route::post('{transfer}/verwerfen', [\App\Http\Controllers\Finance\FinanceTransferController::class, 'void'])->name('void');
            Route::get('{transfer}/download', [\App\Http\Controllers\Finance\FinanceTransferController::class, 'download'])->name('download');
        });
        Route::patch('projects/{project}/tasks/{task}/complete', [TaskController::class, 'complete'])->name('projects.tasks.complete');
        Route::get('time-entries/create', [TimeEntryController::class, 'pick'])->name('time-entries.create');
        Route::resource('projects.time-entries', TimeEntryController::class)->except(['index', 'show']);
        Route::resource('projects.billing-rules', ProjectBillingRuleController::class)->except(['index', 'show', 'edit']);
        Route::patch('projects/{project}/billing-settings', [ProjectBillingRuleController::class, 'updateSettings'])->name('projects.billing-settings.update');

        Route::resource('projects.recurrence-rules', ProjectRecurrenceRuleController::class)
            ->except(['index', 'show']);
        Route::post('projects/{project}/recurrence-rules/{recurrence_rule}/run', [ProjectRecurrenceRuleController::class, 'run'])
            ->name('projects.recurrence-rules.run');

        // ── Stundenzettel (an Projekt gekoppelt) ────────────────────────────────
        Route::get('timesheets', [TimesheetController::class, 'index'])->name('timesheets.index');
        Route::get('timesheets/create', [TimesheetController::class, 'pick'])->name('timesheets.create');
        Route::post('timesheets/quick', [TimesheetController::class, 'storeQuick'])->name('timesheets.quick');
        Route::resource('projects.timesheets', TimesheetController::class)
            ->parameters(['timesheets' => 'timesheet'])
            ->except(['index']);
        Route::post('projects/{project}/timesheets/{timesheet}/submit', [TimesheetController::class, 'submit'])
            ->name('projects.timesheets.submit');

        Route::get('projects/{project}/timesheets/{timesheet}/entries/create', [TimesheetEntryController::class, 'create'])->name('projects.timesheets.entries.create');
        Route::post('projects/{project}/timesheets/{timesheet}/entries', [TimesheetEntryController::class, 'store'])->name('projects.timesheets.entries.store');
        Route::put('projects/{project}/timesheets/{timesheet}/entries/{entry}', [TimesheetEntryController::class, 'update'])->name('projects.timesheets.entries.update');
        Route::delete('projects/{project}/timesheets/{timesheet}/entries/{entry}', [TimesheetEntryController::class, 'destroy'])->name('projects.timesheets.entries.destroy');

        Route::get('projects/{project}/timesheets/{timesheet}/materials/create', [TimesheetMaterialController::class, 'create'])->name('projects.timesheets.materials.create');
        Route::post('projects/{project}/timesheets/{timesheet}/materials', [TimesheetMaterialController::class, 'store'])->name('projects.timesheets.materials.store');
        Route::put('projects/{project}/timesheets/{timesheet}/materials/{usage}', [TimesheetMaterialController::class, 'update'])->name('projects.timesheets.materials.update');
        Route::delete('projects/{project}/timesheets/{timesheet}/materials/{usage}', [TimesheetMaterialController::class, 'destroy'])->name('projects.timesheets.materials.destroy');

        Route::post('projects/{project}/timesheets/{timesheet}/sign', [TimesheetSignatureController::class, 'store'])->name('projects.timesheets.sign');
        Route::post('projects/{project}/timesheets/{timesheet}/lock', [TimesheetSignatureController::class, 'lock'])->name('projects.timesheets.lock');
        Route::post('projects/{project}/timesheets/{timesheet}/unlock', [TimesheetSignatureController::class, 'unlock'])->name('projects.timesheets.unlock');
        Route::get('projects/{project}/timesheets/{timesheet}/pdf', [TimesheetSignatureController::class, 'pdf'])->name('projects.timesheets.pdf');
        Route::post('projects/{project}/timesheets/{timesheet}/magic-link', [TimesheetSignatureController::class, 'magicLink'])->name('projects.timesheets.magic-link');

        // ── Stoppuhr ────────────────────────────────────────────────────────────
        Route::get('stopwatch', [StopwatchController::class, 'current'])->name('stopwatch.current');
        Route::post('stopwatch/start', [StopwatchController::class, 'start'])->name('stopwatch.start');
        Route::post('stopwatch/stop', [StopwatchController::class, 'stop'])->name('stopwatch.stop');
        // ── Stempeluhr / Anwesenheit ──────────────────────────────────────────────────────────
        Route::get('attendance', [AttendanceController::class, 'index'])->name('attendance.index');
        Route::get('attendance/current', [AttendanceController::class, 'current'])->name('attendance.current');
        Route::post('attendance/clock-in', [AttendanceController::class, 'clockIn'])->name('attendance.clock-in');
        Route::post('attendance/clock-out', [AttendanceController::class, 'clockOut'])->name('attendance.clock-out');
        Route::post('attendance/break', [AttendanceController::class, 'break'])->name('attendance.break');
        Route::post('attendance/cancel', [AttendanceController::class, 'cancel'])->name('attendance.cancel');
        Route::put('attendance/{attendance}', [AttendanceController::class, 'update'])->name('attendance.update');
        Route::delete('attendance/{attendance}', [AttendanceController::class, 'destroy'])->name('attendance.destroy');

        // ── Tages-Dashboard ───────────────────────────────────────────────────
        Route::get('today', [TodayController::class, 'show'])->name('today.show');

        // ── Verwaltungszeiten ( nicht-projektgebundene TimeEntries ) ───────────────────────
        Route::get('time-entries/admin/create', [AdminTimeEntryController::class, 'create'])->name('admin-time-entries.create');
        Route::post('time-entries/admin', [AdminTimeEntryController::class, 'store'])->name('admin-time-entries.store');
        Route::get('time-entries/admin/{timeEntry}/edit', [AdminTimeEntryController::class, 'edit'])->name('admin-time-entries.edit');
        Route::put('time-entries/admin/{timeEntry}', [AdminTimeEntryController::class, 'update'])->name('admin-time-entries.update');
        Route::delete('time-entries/admin/{timeEntry}', [AdminTimeEntryController::class, 'destroy'])->name('admin-time-entries.destroy');

        // ── Tätigkeitskategorien (Admin) ────────────────────────────────────────
        Route::get('activity-categories', [ActivityCategoryController::class, 'index'])->name('activity-categories.index');
        Route::get('activity-categories/create', [ActivityCategoryController::class, 'create'])->name('activity-categories.create');
        Route::post('activity-categories', [ActivityCategoryController::class, 'store'])->name('activity-categories.store');
        Route::get('activity-categories/{activityCategory}/edit', [ActivityCategoryController::class, 'edit'])->name('activity-categories.edit');
        Route::put('activity-categories/{activityCategory}', [ActivityCategoryController::class, 'update'])->name('activity-categories.update');
        Route::delete('activity-categories/{activityCategory}', [ActivityCategoryController::class, 'destroy'])->name('activity-categories.destroy');

        // ── Fahrtenbuch ────────────────────────────────────────────────────
        Route::get('travel-logs', [TravelLogController::class, 'index'])->name('travel-logs.index');
        Route::get('travel-logs/export', [TravelLogController::class, 'export'])->name('travel-logs.export');
        Route::get('travel-logs/create', [TravelLogController::class, 'create'])->name('travel-logs.create');
        Route::post('travel-logs', [TravelLogController::class, 'store'])->name('travel-logs.store');
        Route::get('travel-logs/{travelLog}/edit', [TravelLogController::class, 'edit'])->name('travel-logs.edit');
        Route::put('travel-logs/{travelLog}', [TravelLogController::class, 'update'])->name('travel-logs.update');
        Route::delete('travel-logs/{travelLog}', [TravelLogController::class, 'destroy'])->name('travel-logs.destroy');
        Route::post('travel-logs/{travelLog}/per-diem', [PerDiemTripController::class, 'fromTravelLog'])->name('travel-logs.per-diem.generate');

        // ── Spesen / Auslagen ──────────────────────────────────────────────
        Route::get('expenses', [ExpenseController::class, 'index'])->name('expenses.index');
        Route::get('expenses/export', [ExpenseController::class, 'export'])->name('expenses.export');
        Route::get('expenses/create', [ExpenseController::class, 'create'])->name('expenses.create');
        Route::post('expenses', [ExpenseController::class, 'store'])->name('expenses.store');
        Route::get('expenses/{expense}/edit', [ExpenseController::class, 'edit'])->name('expenses.edit');
        Route::put('expenses/{expense}', [ExpenseController::class, 'update'])->name('expenses.update');
        Route::delete('expenses/{expense}', [ExpenseController::class, 'destroy'])->name('expenses.destroy');
        Route::post('expenses/{expense}/submit', [ExpenseController::class, 'submit'])->name('expenses.submit');
        Route::post('expenses/{expense}/cancel', [ExpenseController::class, 'cancel'])->name('expenses.cancel');

        // ── Offene Punkte (Snagging / Restpunkte) ──────────────────────────
        Route::get('open-issues/create', [OpenIssueController::class, 'create'])->name('open-issues.create');
        Route::post('open-issues', [OpenIssueController::class, 'store'])->name('open-issues.store');
        Route::put('open-issues/{issue}', [OpenIssueController::class, 'update'])->name('open-issues.update');
        Route::delete('open-issues/{issue}', [OpenIssueController::class, 'destroy'])->name('open-issues.destroy');
        Route::put('open-issues/{issue}/assignee', [OpenIssueController::class, 'assign'])->name('open-issues.assign');
        Route::get('open-issues/{issue}/transitions/{action}', [OpenIssueController::class, 'transitionForm'])
            ->whereIn('action', ['block', 'complete', 'wontDo', 'reopen'])
            ->name('open-issues.transition.form');
        Route::post('open-issues/{issue}/transitions/{action}', [OpenIssueController::class, 'transition'])
            ->whereIn('action', ['start', 'block', 'unblock', 'complete', 'wontDo', 'reopen'])
            ->name('open-issues.transition');

        // ── Kommunikationsnotizen (MVP-012) ────────────────────────────────
        Route::get('communication-notes/create', [CommunicationNoteController::class, 'create'])->name('communication-notes.create');
        Route::post('communication-notes', [CommunicationNoteController::class, 'store'])->name('communication-notes.store');
        Route::get('communication-notes/{note}/edit', [CommunicationNoteController::class, 'edit'])->name('communication-notes.edit');
        Route::put('communication-notes/{note}', [CommunicationNoteController::class, 'update'])->name('communication-notes.update');
        Route::post('communication-notes/{note}/publish', [CommunicationNoteController::class, 'publish'])->name('communication-notes.publish');
        Route::post('communication-notes/{note}/confidential', [CommunicationNoteController::class, 'confidential'])->name('communication-notes.confidential');
        Route::post('communication-notes/{note}/followup-complete', [CommunicationNoteController::class, 'completeFollowup'])->name('communication-notes.followup-complete');
        Route::delete('communication-notes/{note}', [CommunicationNoteController::class, 'destroy'])->name('communication-notes.destroy');

        // ── Dokumentenmanagement (MVP-031) ─────────────────────────────────
        Route::get('documents', [\App\Http\Controllers\DocumentController::class, 'index'])->name('documents.index');
        Route::get('documents/create', [\App\Http\Controllers\DocumentController::class, 'create'])->name('documents.create');
        Route::post('documents', [\App\Http\Controllers\DocumentController::class, 'store'])->name('documents.store');
        Route::get('documents/{document}/edit', [\App\Http\Controllers\DocumentController::class, 'edit'])->name('documents.edit');
        Route::put('documents/{document}', [\App\Http\Controllers\DocumentController::class, 'update'])->name('documents.update');
        Route::get('documents/{document}/versions', [\App\Http\Controllers\DocumentController::class, 'versions'])->name('documents.versions');
        Route::post('documents/{document}/versions', [\App\Http\Controllers\DocumentController::class, 'addVersion'])->name('documents.versions.store');
        Route::get('documents/{document}/download/{version?}', [\App\Http\Controllers\DocumentController::class, 'download'])->name('documents.download');
        Route::post('documents/{document}/archive', [\App\Http\Controllers\DocumentController::class, 'archive'])->name('documents.archive');
        Route::delete('documents/{document}', [\App\Http\Controllers\DocumentController::class, 'destroy'])->name('documents.destroy');

        // ── Wissensbasis & Problemhistorie (Feature 011) ───────────────────
        Route::get('knowledge', [\App\Http\Controllers\KnowledgeArticleController::class, 'index'])->name('knowledge.index');
        Route::get('knowledge/create', [\App\Http\Controllers\KnowledgeArticleController::class, 'create'])->name('knowledge.create');
        Route::post('knowledge', [\App\Http\Controllers\KnowledgeArticleController::class, 'store'])->name('knowledge.store');
        Route::get('knowledge/{article}', [\App\Http\Controllers\KnowledgeArticleController::class, 'show'])->name('knowledge.show');
        Route::get('knowledge/{article}/edit', [\App\Http\Controllers\KnowledgeArticleController::class, 'edit'])->name('knowledge.edit');
        Route::put('knowledge/{article}', [\App\Http\Controllers\KnowledgeArticleController::class, 'update'])->name('knowledge.update');
        Route::post('knowledge/{article}/publish', [\App\Http\Controllers\KnowledgeArticleController::class, 'publish'])->name('knowledge.publish');
        Route::post('knowledge/{article}/archive', [\App\Http\Controllers\KnowledgeArticleController::class, 'archive'])->name('knowledge.archive');
        Route::post('knowledge/{article}/feedback', [\App\Http\Controllers\KnowledgeArticleController::class, 'feedback'])->name('knowledge.feedback');
        Route::post('knowledge/{article}/links', [\App\Http\Controllers\KnowledgeArticleController::class, 'storeLink'])->name('knowledge.links.store');
        Route::delete('knowledge/{article}/links/{link}', [\App\Http\Controllers\KnowledgeArticleController::class, 'destroyLink'])->name('knowledge.links.destroy');
        Route::delete('knowledge/{article}', [\App\Http\Controllers\KnowledgeArticleController::class, 'destroy'])->name('knowledge.destroy');

        // ── Vorlagen- & Formularsystem (Feature 032) ───────────────────────
        Route::get('form-templates', [\App\Http\Controllers\FormTemplateController::class, 'index'])->name('form-templates.index');
        Route::get('form-templates/create', [\App\Http\Controllers\FormTemplateController::class, 'create'])->name('form-templates.create');
        Route::post('form-templates', [\App\Http\Controllers\FormTemplateController::class, 'store'])->name('form-templates.store');
        Route::get('form-templates/{template}/edit', [\App\Http\Controllers\FormTemplateController::class, 'edit'])->name('form-templates.edit');
        Route::put('form-templates/{template}', [\App\Http\Controllers\FormTemplateController::class, 'update'])->name('form-templates.update');
        Route::post('form-templates/{template}/activate', [\App\Http\Controllers\FormTemplateController::class, 'activate'])->name('form-templates.activate');
        Route::post('form-templates/{template}/archive', [\App\Http\Controllers\FormTemplateController::class, 'archive'])->name('form-templates.archive');
        Route::delete('form-templates/{template}', [\App\Http\Controllers\FormTemplateController::class, 'destroy'])->name('form-templates.destroy');

        Route::get('form-submissions', [\App\Http\Controllers\FormSubmissionController::class, 'index'])->name('form-submissions.index');
        Route::get('form-submissions/create', [\App\Http\Controllers\FormSubmissionController::class, 'create'])->name('form-submissions.create');
        Route::post('form-submissions', [\App\Http\Controllers\FormSubmissionController::class, 'store'])->name('form-submissions.store');
        Route::get('form-submissions/{submission}', [\App\Http\Controllers\FormSubmissionController::class, 'show'])->name('form-submissions.show');

        // ── Protokolle (MVP-020) ───────────────────────────────────────────
        Route::post('protocols', [ProtocolController::class, 'store'])->name('protocols.store');
        Route::put('protocols/{protocol}', [ProtocolController::class, 'update'])->name('protocols.update');
        Route::delete('protocols/{protocol}', [ProtocolController::class, 'destroy'])->name('protocols.destroy');
        Route::post('protocols/{protocol}/transitions/{action}', [ProtocolController::class, 'transition'])
            ->whereIn('action', ['requestReview', 'returnToDraft', 'sign', 'archive', 'supersede'])
            ->name('protocols.transition');
        Route::post('protocols/{protocol}/items', [ProtocolController::class, 'addItem'])->name('protocols.items.store');
        Route::put('protocol-items/{item}', [ProtocolController::class, 'fillItem'])->name('protocols.items.fill');
        Route::delete('protocol-items/{item}', [ProtocolController::class, 'destroyItem'])->name('protocols.items.destroy');
        Route::post('protocol-items/{item}/photos', [ProtocolController::class, 'uploadPhoto'])->name('protocols.items.photos.store');
        Route::delete('protocol-item-photos/{photo}', [ProtocolController::class, 'destroyPhoto'])->name('protocols.items.photos.destroy');
        Route::post('protocols/{protocol}/signature-tokens', [ProtocolController::class, 'issueSignatureToken'])->name('protocols.signature-tokens.store');
        Route::get('protocols/{protocol}/pdf', [ProtocolController::class, 'pdf'])->name('protocols.pdf');

        // ── Verpflegungsmehraufwand (Per-Diem) ─────────────────────────────
        Route::get('per-diem-trips', [PerDiemTripController::class, 'index'])->name('per-diem-trips.index');
        Route::get('per-diem-trips/create', [PerDiemTripController::class, 'create'])->name('per-diem-trips.create');
        Route::post('per-diem-trips', [PerDiemTripController::class, 'store'])->name('per-diem-trips.store');
        Route::get('per-diem-trips/{perDiemTrip}', [PerDiemTripController::class, 'show'])->name('per-diem-trips.show');
        Route::get('per-diem-trips/{perDiemTrip}/pdf', [PerDiemTripController::class, 'pdf'])->name('per-diem-trips.pdf');
        Route::get('per-diem-trips/{perDiemTrip}/edit', [PerDiemTripController::class, 'edit'])->name('per-diem-trips.edit');
        Route::put('per-diem-trips/{perDiemTrip}', [PerDiemTripController::class, 'update'])->name('per-diem-trips.update');
        Route::delete('per-diem-trips/{perDiemTrip}', [PerDiemTripController::class, 'destroy'])->name('per-diem-trips.destroy');
        Route::put('per-diem-trips/{perDiemTrip}/days/{day}', [PerDiemTripController::class, 'updateDay'])->name('per-diem-trips.days.update');
        Route::post('per-diem-trips/{perDiemTrip}/convert', [PerDiemTripController::class, 'convert'])->name('per-diem-trips.convert');
        Route::post('per-diem-trips/{perDiemTrip}/cancel', [PerDiemTripController::class, 'cancel'])->name('per-diem-trips.cancel');

        // ── Spesen-Genehmigung (Inbox) ─────────────────────────────────────
        Route::get('expense-approvals', [ExpenseApprovalController::class, 'inbox'])->name('expense-approvals.inbox');
        Route::post('expense-approvals/{expense}/approve', [ExpenseApprovalController::class, 'approve'])->name('expense-approvals.approve');
        Route::get('expense-approvals/{expense}/reject', [ExpenseApprovalController::class, 'rejectForm'])->name('expense-approvals.reject-form');
        Route::post('expense-approvals/{expense}/reject', [ExpenseApprovalController::class, 'reject'])->name('expense-approvals.reject');
        Route::post('expense-approvals/{expense}/reimburse', [ExpenseApprovalController::class, 'markReimbursed'])->name('expense-approvals.reimburse');
        Route::post('expense-approvals/bulk-approve', [ExpenseApprovalController::class, 'bulkApprove'])->name('expense-approvals.bulk-approve');
        Route::post('expense-approvals/bulk-reject', [ExpenseApprovalController::class, 'bulkReject'])->name('expense-approvals.bulk-reject');

        // ── Touren ─────────────────────────────────────────────────────────
        Route::get('tours', [TourController::class, 'index'])->name('tours.index');
        Route::get('tours/create', [TourController::class, 'create'])->name('tours.create');
        Route::get('tours/map', [TourController::class, 'map'])->name('tours.map');
        Route::post('tours', [TourController::class, 'store'])->name('tours.store');
        Route::get('tours/{tour}', [TourController::class, 'show'])->name('tours.show');
        Route::get('tours/{tour}/edit', [TourController::class, 'edit'])->name('tours.edit');
        Route::put('tours/{tour}', [TourController::class, 'update'])->name('tours.update');
        Route::delete('tours/{tour}', [TourController::class, 'destroy'])->name('tours.destroy');
        Route::post('tours/{tour}/optimize', [TourController::class, 'optimize'])->name('tours.optimize');
        Route::post('tours/{tour}/start', [TourController::class, 'start'])->name('tours.start');
        Route::post('tours/{tour}/complete', [TourController::class, 'complete'])->name('tours.complete');
        Route::post('tours/{tour}/materialize', [TourController::class, 'materialize'])->name('tours.materialize');

        // ── Fuhrpark ───────────────────────────────────────────────────────
        Route::get('assets', [AssetController::class, 'index'])->name('assets.index');
        Route::get('assets/create', [AssetController::class, 'create'])->name('assets.create');
        Route::post('assets', [AssetController::class, 'store'])->name('assets.store');
        Route::get('assets/{asset}/edit', [AssetController::class, 'edit'])->name('assets.edit');
        Route::put('assets/{asset}', [AssetController::class, 'update'])->name('assets.update');
        Route::post('assets/{asset}/unblock', [AssetController::class, 'unblock'])->name('assets.unblock');
        Route::get('assets/{asset}', [AssetController::class, 'show'])->name('assets.show');

        Route::get('assets/{asset}/maintenance-plans/create', [MaintenancePlanController::class, 'create'])->name('assets.maintenance-plans.create');
        Route::post('assets/{asset}/maintenance-plans', [MaintenancePlanController::class, 'store'])->name('assets.maintenance-plans.store');
        Route::put('assets/{asset}/maintenance-plans/{plan}', [MaintenancePlanController::class, 'update'])->name('assets.maintenance-plans.update');
        Route::post('assets/{asset}/maintenance-plans/{plan}/complete', [MaintenancePlanController::class, 'complete'])->name('assets.maintenance-plans.complete');
        Route::post('assets/{asset}/maintenance-plans/{plan}/toggle', [MaintenancePlanController::class, 'toggle'])->name('assets.maintenance-plans.toggle');
        Route::delete('assets/{asset}/maintenance-plans/{plan}', [MaintenancePlanController::class, 'destroy'])->name('assets.maintenance-plans.destroy');

        Route::get('assets/{asset}/software-installations/create', [SoftwareInstallationController::class, 'create'])->name('assets.software-installations.create');
        Route::post('assets/{asset}/software-installations', [SoftwareInstallationController::class, 'store'])->name('assets.software-installations.store');
        Route::put('assets/{asset}/software-installations/{installation}', [SoftwareInstallationController::class, 'update'])->name('assets.software-installations.update');
        Route::delete('assets/{asset}/software-installations/{installation}', [SoftwareInstallationController::class, 'destroy'])->name('assets.software-installations.destroy');

        Route::get('software', [SoftwareController::class, 'index'])->name('software.index');
        Route::get('software/create', [SoftwareController::class, 'create'])->name('software.create');
        Route::post('software', [SoftwareController::class, 'store'])->name('software.store');
        Route::get('software/{software}/edit', [SoftwareController::class, 'edit'])->name('software.edit');
        Route::put('software/{software}', [SoftwareController::class, 'update'])->name('software.update');
        Route::delete('software/{software}', [SoftwareController::class, 'destroy'])->name('software.destroy');

        Route::get('service-tickets', [ServiceTicketController::class, 'index'])->name('service-tickets.index');
        Route::get('service-tickets/create', [ServiceTicketController::class, 'create'])->name('service-tickets.create');
        Route::post('service-tickets', [ServiceTicketController::class, 'store'])->name('service-tickets.store');
        Route::get('service-tickets/{ticket}', [ServiceTicketController::class, 'show'])->name('service-tickets.show');
        Route::post('service-tickets/{ticket}/transition', [ServiceTicketController::class, 'transition'])->name('service-tickets.transition');
        Route::post('service-tickets/{ticket}/assign', [ServiceTicketController::class, 'assign'])->name('service-tickets.assign');
        Route::delete('service-tickets/{ticket}', [ServiceTicketController::class, 'destroy'])->name('service-tickets.destroy');

        Route::get('key-handovers', [KeyHandoverController::class, 'index'])->name('key-handovers.index');
        Route::get('key-handovers/create', [KeyHandoverController::class, 'create'])->name('key-handovers.create');
        Route::post('key-handovers', [KeyHandoverController::class, 'store'])->name('key-handovers.store');

        Route::get('meter-readings', [MeterReadingController::class, 'index'])->name('meter-readings.index');
        Route::get('meter-readings/create', [MeterReadingController::class, 'create'])->name('meter-readings.create');
        Route::post('meter-readings', [MeterReadingController::class, 'store'])->name('meter-readings.store');

        Route::get('vehicles', [VehicleController::class, 'index'])->name('vehicles.index');
        Route::get('vehicles/create', [VehicleController::class, 'create'])->name('vehicles.create');
        Route::post('vehicles', [VehicleController::class, 'store'])->name('vehicles.store');
        Route::get('vehicles/{vehicle}/edit', [VehicleController::class, 'edit'])->name('vehicles.edit');
        Route::put('vehicles/{vehicle}', [VehicleController::class, 'update'])->name('vehicles.update');
        Route::delete('vehicles/{vehicle}', [VehicleController::class, 'destroy'])->name('vehicles.destroy');
        Route::post('vehicles/{vehicle}/restore', [VehicleController::class, 'restore'])->name('vehicles.restore');

        Route::get('energy-logs', [EnergyLogController::class, 'index'])->name('energy-logs.index');
        Route::get('energy-logs/create', [EnergyLogController::class, 'create'])->name('energy-logs.create');
        Route::post('energy-logs', [EnergyLogController::class, 'store'])->name('energy-logs.store');
        Route::get('energy-logs/{energyLog}/edit', [EnergyLogController::class, 'edit'])->name('energy-logs.edit');
        Route::put('energy-logs/{energyLog}', [EnergyLogController::class, 'update'])->name('energy-logs.update');
        Route::delete('energy-logs/{energyLog}', [EnergyLogController::class, 'destroy'])->name('energy-logs.destroy');

        // ── Geocoding (intern) ──────────────────────────────────────────────
        Route::post('api/internal/geocode', GeocodeController::class)->name('api.internal.geocode');

        // ── Globale Suche / Command-Palette ─────────────────────────────────
        Route::get('api/internal/search', GlobalSearchController::class)->name('api.internal.search');

        // ── Globale Zeitauswahl (Header-Widget) ─────────────────────────────────
        Route::post('ui/date-range', [DateRangeController::class, 'update'])->name('ui.date-range.update');
        Route::post('ui/date-range/shift', [DateRangeController::class, 'shift'])->name('ui.date-range.shift');

        // ── Gleitzeit ───────────────────────────────────────────────────────────
        Route::get('flex', [FlexController::class, 'index'])->name('flex.index');
        Route::get('flex/admin', [FlexController::class, 'admin'])->name('flex.admin');

        // ── Monatsfreigaben (MVP-016) ───────────────────────────────────────────
        Route::get('month-approval', [\App\Http\Controllers\MonthApprovalController::class, 'index'])
            ->name('month-approval.index');
        Route::get('month-approval/{year}/{month}', [\App\Http\Controllers\MonthApprovalController::class, 'show'])
            ->whereNumber(['year', 'month'])
            ->name('month-approval.show');
        Route::post('month-approval/{year}/{month}/submit', [\App\Http\Controllers\MonthApprovalController::class, 'submit'])
            ->whereNumber(['year', 'month'])
            ->name('month-approval.submit');
        Route::post('month-approval/{year}/{month}/reopen', [\App\Http\Controllers\MonthApprovalController::class, 'reopen'])
            ->whereNumber(['year', 'month'])
            ->name('month-approval.reopen');

        Route::get('admin/month-approval', [\App\Http\Controllers\Admin\MonthApprovalInboxController::class, 'index'])
            ->name('admin.month-approval.index');
        Route::post('admin/month-approval/{monthClosure}/approve', [\App\Http\Controllers\Admin\MonthApprovalInboxController::class, 'approve'])
            ->name('admin.month-approval.approve');
        Route::post('admin/month-approval/{monthClosure}/reject', [\App\Http\Controllers\Admin\MonthApprovalInboxController::class, 'reject'])
            ->name('admin.month-approval.reject');
        Route::post('admin/month-approval/{monthClosure}/reopen', [\App\Http\Controllers\Admin\MonthApprovalInboxController::class, 'reopen'])
            ->name('admin.month-approval.reopen');
        Route::post('admin/month-approval/{monthClosure}/lock', [\App\Http\Controllers\Admin\MonthApprovalInboxController::class, 'lock'])
            ->name('admin.month-approval.lock');

        // ── Zeit-Korrekturanträge (MVP-017) ─────────────────────────────────────
        Route::get('corrections', [\App\Http\Controllers\TimeCorrectionController::class, 'index'])
            ->name('corrections.index');
        Route::get('corrections/create', [\App\Http\Controllers\TimeCorrectionController::class, 'create'])
            ->name('corrections.create');
        Route::post('corrections', [\App\Http\Controllers\TimeCorrectionController::class, 'store'])
            ->name('corrections.store');
        Route::get('corrections/{correction}', [\App\Http\Controllers\TimeCorrectionController::class, 'show'])
            ->name('corrections.show');
        Route::post('corrections/{correction}/submit', [\App\Http\Controllers\TimeCorrectionController::class, 'submit'])
            ->name('corrections.submit');
        Route::post('corrections/{correction}/withdraw', [\App\Http\Controllers\TimeCorrectionController::class, 'withdraw'])
            ->name('corrections.withdraw');

        Route::get('admin/corrections', [\App\Http\Controllers\Admin\TimeCorrectionInboxController::class, 'index'])
            ->name('admin.corrections.index');
        Route::get('admin/corrections/{correction}', [\App\Http\Controllers\Admin\TimeCorrectionInboxController::class, 'show'])
            ->name('admin.corrections.show');
        Route::post('admin/corrections/{correction}/approve', [\App\Http\Controllers\Admin\TimeCorrectionInboxController::class, 'approve'])
            ->name('admin.corrections.approve');
        Route::post('admin/corrections/{correction}/reject', [\App\Http\Controllers\Admin\TimeCorrectionInboxController::class, 'reject'])
            ->name('admin.corrections.reject');
        Route::post('admin/corrections/{correction}/apply', [\App\Http\Controllers\Admin\TimeCorrectionInboxController::class, 'apply'])
            ->name('admin.corrections.apply');

        // ── Zeit-Export (MVP-019) ──────────────────────────────────────────────
        Route::get('exports', [\App\Http\Controllers\TimeExportController::class, 'index'])
            ->name('exports.index');
        Route::get('exports/create', [\App\Http\Controllers\TimeExportController::class, 'create'])
            ->name('exports.create');
        Route::post('exports', [\App\Http\Controllers\TimeExportController::class, 'store'])
            ->name('exports.store');
        Route::get('exports/{export}', [\App\Http\Controllers\TimeExportController::class, 'show'])
            ->name('exports.show');
        Route::get('exports/{export}/download', [\App\Http\Controllers\TimeExportController::class, 'download'])
            ->name('exports.download');
        Route::post('exports/{export}/deliver', [\App\Http\Controllers\TimeExportController::class, 'deliver'])
            ->name('exports.deliver');
        Route::post('exports/{export}/reject', [\App\Http\Controllers\TimeExportController::class, 'reject'])
            ->name('exports.reject');

        // ── Plan/Ist-Report (MVP-018) ──────────────────────────────────────────
        Route::get('reports/plan-ist/presence', [\App\Http\Controllers\Reporting\PlanIstReportController::class, 'presence'])
            ->name('reports.plan-ist.presence');

        // ── Auswertungen ────────────────────────────────────────────────────────
        Route::get('reports/my-year', [MyYearReportController::class, 'index'])->name('reports.my-year');
        Route::get('reports/my-month', [MyMonthReportController::class, 'index'])->name('reports.my-month');
        Route::get('reports/external-payouts', [ExternalPayoutReportController::class, 'index'])->name('reports.external-payouts');
        Route::get('reports/customers', [CustomerAnalysisReportController::class, 'index'])->name('reports.customers');
        Route::get('reports/customers/drilldown/open-issues', [\App\Http\Controllers\Reporting\CustomerDrilldownReportController::class, 'openIssues'])
            ->name('reports.customers.drilldown.open-issues');
        Route::get('reports/customers/drilldown/protocols', [\App\Http\Controllers\Reporting\CustomerDrilldownReportController::class, 'protocols'])
            ->name('reports.customers.drilldown.protocols');
        Route::get('reports/entry-types', [EntryTypeAnalysisReportController::class, 'index'])->name('reports.entry-types');
        Route::get('reports/entry-types/drilldown/open-issues', [EntryTypeDrilldownReportController::class, 'openIssues'])
            ->name('reports.entry-types.drilldown.open-issues');
        Route::get('reports/entry-types/drilldown/protocols', [EntryTypeDrilldownReportController::class, 'protocols'])
            ->name('reports.entry-types.drilldown.protocols');
        Route::get('reports/assets', [AssetAnalysisReportController::class, 'index'])->name('reports.assets');
        Route::get('reports/assets/drilldown/open-issues', [\App\Http\Controllers\Reporting\AssetDrilldownReportController::class, 'openIssues'])
            ->name('reports.assets.drilldown.open-issues');
        Route::get('reports/assets/drilldown/protocols', [\App\Http\Controllers\Reporting\AssetDrilldownReportController::class, 'protocols'])
            ->name('reports.assets.drilldown.protocols');
        Route::get('reports/customer-project', [CustomerProjectReportController::class, 'index'])->name('reports.customer-project');
        Route::get('reports/week-by-user', [WeekByUserReportController::class, 'index'])->name('reports.week-by-user');
        Route::get('reports/month-by-user-team', [MonthByUserTeamReportController::class, 'index'])->name('reports.month-by-user-team');
        Route::get('reports/project-inactive', [ProjectInactiveReportController::class, 'index'])->name('reports.project-inactive');
        Route::post('reports/project-inactive/archive', [ProjectInactiveReportController::class, 'archive'])->name('reports.project-inactive.archive');
        Route::get('reports/project-details', [ProjectDetailsReportController::class, 'index'])->name('reports.project-details');
        Route::get('reports/work-balance', [WorkBalanceReportController::class, 'index'])->name('reports.work-balance');
        Route::get('reports/fleet', [FleetReportController::class, 'index'])->name('reports.fleet');
        Route::get('reports/on-call', [OnCallReportController::class, 'index'])->name('reports.on-call');
        Route::get('reports/coverage', [CoverageReportController::class, 'index'])->name('reports.coverage');
        Route::get('reports/absences', [AbsencesReportController::class, 'index'])->name('reports.absences');
        Route::get('reports/sickness', [SicknessReportController::class, 'index'])->name('reports.sickness');
        Route::get('reports/operations', [OperationsReportController::class, 'index'])->name('reports.operations');
        Route::get('reports/materials', [MaterialReportController::class, 'index'])->name('reports.materials');
        Route::get('reports/billing', [BillingReportController::class, 'index'])->name('reports.billing');
        Route::get('reports/expenses', [ExpenseReportController::class, 'index'])->name('reports.expenses');
        Route::get('reports/qualifications', [QualificationReportController::class, 'index'])->name('reports.qualifications');
        Route::get('reports/attendance', [AttendanceReportController::class, 'index'])->name('reports.attendance');
        Route::get('reports/audit-activity', [AuditActivityReportController::class, 'index'])->name('reports.audit-activity');

        // ── Material-Stamm (Admin) ──────────────────────────────────────────────
        Route::resource('materials', MaterialController::class)->except('show');

        // ── Arbeitszeit-Modell ──────────────────────────────────────────────────
        Route::get('account/work-schedule', [WorkScheduleController::class, 'self'])->name('account.work-schedule');
        Route::get('users/{user}/work-schedule', [WorkScheduleController::class, 'edit'])->name('users.work-schedule.edit');
        Route::put('users/{user}/work-schedule', [WorkScheduleController::class, 'update'])->name('users.work-schedule.update');

        // ── Gleitzeit-Berechtigung pro Mitarbeiter (periodisch) ───────────────────
        Route::get('users/{user}/flex-eligibility', [FlexEligibilityController::class, 'index'])
            ->name('users.flex-eligibility.index');
        Route::post('users/{user}/flex-eligibility', [FlexEligibilityController::class, 'store'])
            ->name('users.flex-eligibility.store');
        Route::put('users/{user}/flex-eligibility/{eligibility}', [FlexEligibilityController::class, 'update'])
            ->name('users.flex-eligibility.update');
        Route::delete('users/{user}/flex-eligibility/{eligibility}', [FlexEligibilityController::class, 'destroy'])
            ->name('users.flex-eligibility.destroy');

        Route::get('archive', [ArchiveController::class, 'index'])->name('archive.index');
        Route::post('archive/run', [ArchiveController::class, 'run'])->name('archive.run');

        Route::get('audit', [AuditLogController::class, 'index'])->name('audit.index');

        Route::resource('admin/organizations', OrganizationController::class)
            ->names('admin.organizations')
            ->parameters(['organizations' => 'organization']);

        // Lifecycle-Aktionen einer Organisation: Deaktivieren / Reaktivieren
        // (reversibel), Daten-Export (DSGVO Art. 20) und endgültiges Löschen
        // (Purge, DSGVO Art. 17). destroy() bleibt als sicherer Fallback
        // bestehen, leitet aber bewusst auf die Deaktivierung um.
        Route::post('admin/organizations/{organization}/deactivate', [OrganizationController::class, 'deactivate'])
            ->name('admin.organizations.deactivate');
        Route::post('admin/organizations/{organization}/reactivate', [OrganizationController::class, 'reactivate'])
            ->name('admin.organizations.reactivate');
        Route::post('admin/organizations/{organization}/export', [OrganizationController::class, 'export'])
            ->name('admin.organizations.export');
        Route::delete('admin/organizations/{organization}/purge', [OrganizationController::class, 'purge'])
            ->name('admin.organizations.purge');

        // Org-Switcher: globalen Admins erlauben, den aktiven
        // Organisations-Kontext per Session umzuschalten.
        Route::post('admin/organizations/switch', [OrganizationSwitchController::class, 'update'])
            ->name('admin.organizations.switch');

        // Branding-Self-Service der eigenen Organisation (Logos, Farben,
        // Kontakt, PDF-Konfig). Logo-Uploads laufen über AttachmentController.
        Route::get('admin/branding', [BrandingController::class, 'edit'])->name('admin.branding.edit');
        Route::put('admin/branding', [BrandingController::class, 'update'])->name('admin.branding.update');

        // Konfigurierbare Nummernkreise (Tickets, Assets, Kunden, Rechnungen, Gutschriften).
        Route::get('admin/number-formats', [\App\Http\Controllers\Admin\NumberFormatController::class, 'index'])
            ->name('admin.number-formats.index');
        Route::put('admin/number-formats', [\App\Http\Controllers\Admin\NumberFormatController::class, 'update'])
            ->name('admin.number-formats.update');

        Route::resource('admin/entry-types', EntryTypeController::class)
            ->names('admin.entry-types')
            ->parameters(['entry-types' => 'entryType'])
            ->except('show');

        Route::resource('admin/classifications', ClassificationController::class)
            ->names('admin.classifications')
            ->parameters(['classifications' => 'classification'])
            ->except('show');
        Route::resource('admin/classification-requirements', ClassificationRequirementController::class)
            ->names('admin.classification-requirements')
            ->parameters(['classification-requirements' => 'classificationRequirement'])
            ->except('show');

        // MVP-049 — CSV-Import Wizard
        Route::get('admin/imports', [ImportController::class, 'index'])->name('admin.imports.index');
        Route::get('admin/imports/create', [ImportController::class, 'create'])->name('admin.imports.create');
        Route::post('admin/imports/preflight', [ImportController::class, 'preflight'])->name('admin.imports.preflight');
        Route::get('admin/imports/{import}', [ImportController::class, 'show'])->name('admin.imports.show');
        Route::post('admin/imports/{import}/confirm', [ImportController::class, 'confirm'])->name('admin.imports.confirm');
        Route::delete('admin/imports/{import}', [ImportController::class, 'destroy'])->name('admin.imports.destroy');
        Route::get('admin/imports/{import}/errors.csv', [ImportController::class, 'downloadErrors'])->name('admin.imports.errors');

        // Datentransfer — zentraler Im-/Export-Bereich
        Route::get('admin/data', [\App\Http\Controllers\Admin\DataTransferController::class, 'index'])->name('admin.data.index');
        Route::get('admin/data/history', [\App\Http\Controllers\Admin\DataTransferController::class, 'history'])->name('admin.data.history');
        Route::post('admin/data/export', [\App\Http\Controllers\Admin\DataTransferController::class, 'export'])->name('admin.data.export');
        Route::get('admin/data/{export}/download', [\App\Http\Controllers\Admin\DataTransferController::class, 'download'])->name('admin.data.download');
        Route::delete('admin/data/{export}', [\App\Http\Controllers\Admin\DataTransferController::class, 'destroy'])->name('admin.data.destroy');
        Route::get('admin/branch-profiles', [BranchProfileController::class, 'index'])
            ->name('admin.branch-profiles.index');
        Route::post('admin/branch-profiles/{profile}', [BranchProfileController::class, 'install'])
            ->name('admin.branch-profiles.install');
        Route::get('admin/classifications/import/form', [ClassificationController::class, 'importForm'])
            ->name('admin.classifications.import.form');
        Route::post('admin/classifications/import', [ClassificationController::class, 'import'])
            ->name('admin.classifications.import');
        Route::post('admin/classifications/reorder/{domain}', [ClassificationController::class, 'reorder'])
            ->name('admin.classifications.reorder');
        Route::post('admin/classifications/{classification}/deactivate-default', [ClassificationController::class, 'deactivateDefault'])
            ->name('admin.classifications.deactivate-default');

        Route::resource('admin/expense-categories', ExpenseCategoryController::class)
            ->names('admin.expense-categories')
            ->parameters(['expense-categories' => 'expenseCategory'])
            ->except('show');

        Route::resource('admin/per-diem-rates', PerDiemRateController::class)
            ->names('admin.per-diem-rates')
            ->parameters(['per-diem-rates' => 'perDiemRate'])
            ->except('show');

        // Benachrichtigungsregeln (MVP-018): pro Ereignistyp eine Regel,
        // Bearbeitung als Modal. {event} ist der Enum-Wert (z. B. openIssue.assigned).
        Route::get('admin/notification-rules', [\App\Http\Controllers\Admin\NotificationRuleController::class, 'index'])
            ->name('admin.notification-rules.index');
        Route::get('admin/notification-rules/{event}/edit', [\App\Http\Controllers\Admin\NotificationRuleController::class, 'edit'])
            ->name('admin.notification-rules.edit');
        Route::put('admin/notification-rules/{event}', [\App\Http\Controllers\Admin\NotificationRuleController::class, 'update'])
            ->name('admin.notification-rules.update');

        // Zuschlagsregeln (Feature 005): Listenseite + Modal-CRUD,
        // Pflege durch Admin/Buchhaltung (surchargeRule.manage).
        Route::resource('admin/surcharge-rules', \App\Http\Controllers\Admin\SurchargeRuleController::class)
            ->names('admin.surcharge-rules')
            ->parameters(['surcharge-rules' => 'surchargeRule'])
            ->except('show');

        // Workflow-Automatisierungen (Wenn-Dann-Regeln pro Org).
        Route::get('admin/automations', [AutomationRuleController::class, 'index'])
            ->name('admin.automations.index');
        Route::post('admin/automations', [AutomationRuleController::class, 'store'])
            ->name('admin.automations.store');
        Route::get('admin/automations/{automationRule}', [AutomationRuleController::class, 'show'])
            ->name('admin.automations.show');
        Route::post('admin/automations/{automationRule}/toggle', [AutomationRuleController::class, 'toggle'])
            ->name('admin.automations.toggle');
        Route::delete('admin/automations/{automationRule}', [AutomationRuleController::class, 'destroy'])
            ->name('admin.automations.destroy');

        Route::resource('org/members', OrgMemberController::class)
            ->names('org.members')
            ->parameters(['members' => 'member'])
            ->except('show');

        // ── Arbeits-Teams (operative Einheiten, getrennt von Rechte-Gruppen) ──
        Route::resource('teams', TeamController::class)
            ->parameters(['teams' => 'team']);
        Route::get('teams/{team}/members/attach', [TeamController::class, 'attachMemberForm'])
            ->name('teams.members.attach.form');
        Route::post('teams/{team}/members', [TeamController::class, 'attachMember'])
            ->name('teams.members.attach');
        Route::delete('teams/{team}/members/{user}', [TeamController::class, 'detachMember'])
            ->name('teams.members.detach');

        // ── Lohn & Sozialversicherung (Personalverwaltung/Geschäftsführung) ──
        Route::get('payroll', [PayrollController::class, 'index'])->name('payroll.index');
        Route::put('payroll/settings', [PayrollController::class, 'updateSettings'])->name('payroll.settings.update');
        Route::post('payroll/minimum-wages', [PayrollController::class, 'storeMinimumWage'])->name('payroll.minimum-wages.store');
        Route::post('payroll/minimum-wages/seed', [PayrollController::class, 'seedMinimumWages'])->name('payroll.minimum-wages.seed');
        Route::post('payroll/references/import', [PayrollController::class, 'importReferences'])->name('payroll.references.import');
        Route::delete('payroll/minimum-wages/{minimumWage}', [PayrollController::class, 'destroyMinimumWage'])->name('payroll.minimum-wages.destroy');
        Route::post('payroll/raise-to-minimum', [PayrollController::class, 'raiseToMinimum'])->name('payroll.raise-to-minimum');

        // ── Chat (Kanäle, Direktnachrichten, Threads, Reaktionen, Umfragen) ──
        Route::prefix('chat')->name('chat.')->group(function (): void {
            Route::get('/', [\App\Http\Controllers\Chat\ChannelController::class, 'index'])->name('index');
            Route::post('channels', [\App\Http\Controllers\Chat\ChannelController::class, 'store'])->name('channels.store');
            Route::post('direct', [\App\Http\Controllers\Chat\ChannelController::class, 'direct'])->name('direct');
            Route::get('search', [\App\Http\Controllers\Chat\MessageController::class, 'search'])->name('search');
            Route::get('unread-count', [\App\Http\Controllers\Chat\ChannelController::class, 'unreadCount'])->name('unread');
            Route::get('channel-list', [\App\Http\Controllers\Chat\ChannelController::class, 'channelList'])->name('channel-list');

            // Nachrichten-/Poll-Aktionen auf einzelnen Nachrichten (literal vor {channel}).
            Route::put('messages/{message}', [\App\Http\Controllers\Chat\MessageController::class, 'update'])->name('messages.update');
            Route::delete('messages/{message}', [\App\Http\Controllers\Chat\MessageController::class, 'destroy'])->name('messages.destroy');
            Route::get('messages/{message}', [\App\Http\Controllers\Chat\MessageController::class, 'show'])->name('messages.show');
            Route::get('messages/{message}/replies', [\App\Http\Controllers\Chat\MessageController::class, 'replies'])->name('messages.replies');
            // Schreib-Aktionen gedrosselt (Spam-/DoS-Schutz durch authentifizierte Nutzer).
            Route::middleware('throttle:240,1')->group(function (): void {
                Route::post('messages/{message}/pin', [\App\Http\Controllers\Chat\MessageController::class, 'pin'])->name('messages.pin');
                Route::post('messages/{message}/react', [\App\Http\Controllers\Chat\ReactionController::class, 'toggle'])->name('messages.react');
                Route::post('messages/{message}/forward', [\App\Http\Controllers\Chat\MessageController::class, 'forward'])->name('messages.forward');
                Route::post('messages/{message}/star', [\App\Http\Controllers\Chat\MessageController::class, 'star'])->name('messages.star');
                Route::post('messages/{message}/remind', [\App\Http\Controllers\Chat\MessageController::class, 'remind'])->name('messages.remind');
                Route::post('polls/{poll}/vote', [\App\Http\Controllers\Chat\PollController::class, 'vote'])->name('polls.vote');
            });

            // Kanalbezogen.
            Route::get('{channel}', [\App\Http\Controllers\Chat\ChannelController::class, 'index'])->name('show');
            Route::put('{channel}', [\App\Http\Controllers\Chat\ChannelController::class, 'update'])->name('channels.update');
            Route::delete('{channel}', [\App\Http\Controllers\Chat\ChannelController::class, 'destroy'])->name('channels.destroy');
            Route::post('{channel}/join', [\App\Http\Controllers\Chat\ChannelController::class, 'join'])->name('channels.join');
            Route::post('{channel}/leave', [\App\Http\Controllers\Chat\ChannelController::class, 'leave'])->name('channels.leave');
            Route::post('{channel}/invite', [\App\Http\Controllers\Chat\ChannelController::class, 'invite'])->name('channels.invite');
            Route::post('{channel}/read', [\App\Http\Controllers\Chat\ChannelController::class, 'markRead'])->name('channels.read');
            Route::get('{channel}/messages', [\App\Http\Controllers\Chat\MessageController::class, 'index'])->name('messages.index');
            Route::post('{channel}/messages', [\App\Http\Controllers\Chat\MessageController::class, 'store'])->middleware('throttle:120,1')->name('messages.store');
            Route::get('{channel}/pinned', [\App\Http\Controllers\Chat\MessageController::class, 'pinned'])->name('messages.pinned');
            Route::post('{channel}/polls', [\App\Http\Controllers\Chat\PollController::class, 'store'])->name('polls.store');
        });

        // ── Rechteverwaltung (Rollen, Gruppen, Permissions, Mitgliederzuweisung) ──
        // Alle Routen sind über das Gate 'manage-access' bzw. die UserGroupPolicy
        // gesichert. Sichtbar im Admin-Menü ist der Bereich nur für Nutzer, die
        // entweder Plattform-Admin sind oder die Permission `access.manage` haben.
        Route::prefix('admin/access')->name('admin.access.')->group(function (): void {
            Route::get('/', AccessHubController::class)->name('index');

            Route::get('permissions', [AccessPermissionController::class, 'index'])->name('permissions.index');

            Route::resource('roles', AccessRoleController::class)
                ->parameters(['roles' => 'role'])
                ->except('show');

            Route::resource('groups', AccessUserGroupController::class)
                ->parameters(['groups' => 'group']);
            Route::get('groups/{group}/members/attach', [AccessUserGroupController::class, 'attachMemberForm'])
                ->name('groups.members.attach.form');
            Route::post('groups/{group}/members', [AccessUserGroupController::class, 'attachMember'])
                ->name('groups.members.attach');
            Route::delete('groups/{group}/members/{user}', [AccessUserGroupController::class, 'detachMember'])
                ->name('groups.members.detach');

            Route::get('members', [AccessMemberController::class, 'index'])->name('members.index');
            Route::get('members/{member}/edit', [AccessMemberController::class, 'edit'])->name('members.edit');
            Route::put('members/{member}', [AccessMemberController::class, 'update'])->name('members.update');
        });

        Route::resource('qualifications', QualificationController::class)->except('show');

        // ── Schichttypen (HTML CRUD, Admin) ─────────────────────────────────────
        Route::get('shift-types', [ShiftTypeController::class, 'index'])->name('shift-types.index');
        Route::get('shift-types/create', [ShiftTypeController::class, 'create'])->name('shift-types.create');
        Route::post('shift-types', [ShiftTypeController::class, 'htmlStore'])->name('shift-types.store');
        Route::get('shift-types/{shiftType}/edit', [ShiftTypeController::class, 'edit'])->name('shift-types.edit');
        Route::put('shift-types/{shiftType}', [ShiftTypeController::class, 'htmlUpdate'])->name('shift-types.update');
        Route::delete('shift-types/{shiftType}', [ShiftTypeController::class, 'htmlDestroy'])->name('shift-types.destroy');

        // ── Geplante Schichten (HTML, Admin) ────────────────────────────────────
        Route::get('scheduled-shifts/{shift}', [ScheduledShiftController::class, 'show'])->name('scheduled-shifts.show');
        Route::get('scheduled-shifts/{shift}/edit', [ScheduledShiftController::class, 'edit'])->name('scheduled-shifts.edit');
        Route::put('scheduled-shifts/{shift}', [ScheduledShiftController::class, 'update'])->name('scheduled-shifts.update');
        Route::delete('scheduled-shifts/{shift}', [ScheduledShiftController::class, 'destroy'])->name('scheduled-shifts.destroy');

        // ── Schichtplan ─────────────────────────────────────────────────────────
        Route::prefix('schedule')->name('schedule.')->group(function () {
            Route::get('/', [ScheduleController::class, 'index'])->name('index');
            // JSON-API for Alpine.js (accepts ?view=week|month&date=YYYY-MM-DD&user=ID)
            Route::get('/api/shifts', [ScheduleController::class, 'apiIndex'])->name('api.index');
            Route::post('/shifts', [ScheduleController::class, 'store'])->name('shifts.store');
            Route::put('/shifts/{shift}', [ScheduleController::class, 'update'])->name('shifts.update');
            Route::delete('/shifts/{shift}', [ScheduleController::class, 'destroy'])->name('shifts.destroy');
            Route::patch('/shifts/{shift}/publish', [ScheduleController::class, 'publish'])->name('shifts.publish');
            Route::patch('/shifts/{shift}/confirm', [ScheduleController::class, 'confirm'])->name('shifts.confirm');
            // Shift types
            Route::post('/types', [ShiftTypeController::class, 'store'])->name('types.store');
            Route::put('/types/{shiftType}', [ShiftTypeController::class, 'update'])->name('types.update');
            Route::delete('/types/{shiftType}', [ShiftTypeController::class, 'destroy'])->name('types.destroy');
            // Import wizard
            Route::get('/import', [ScheduleImportController::class, 'show'])->name('import');
            Route::post('/import/preview', [ScheduleImportController::class, 'preview'])->name('import.preview');
            Route::post('/import/confirm', [ScheduleImportController::class, 'confirm'])->name('import.confirm');
        });

        Route::get('push/vapid', [PushSubscriptionController::class, 'vapid'])->name('push.vapid');
        Route::post('push/subscribe', [PushSubscriptionController::class, 'store'])->name('push.subscribe');
        Route::delete('push/unsubscribe', [PushSubscriptionController::class, 'destroy'])->name('push.unsubscribe');

        Route::get('profile/api-tokens', [ApiTokenController::class, 'index'])->name('profile.api-tokens.index');
        Route::post('profile/api-tokens', [ApiTokenController::class, 'store'])->name('profile.api-tokens.store');
        Route::delete('profile/api-tokens/{id}', [ApiTokenController::class, 'destroy'])
            ->whereNumber('id')
            ->name('profile.api-tokens.destroy');
    });
});
