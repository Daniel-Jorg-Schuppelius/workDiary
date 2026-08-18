<?php
/*
 * Created on   : Wed Apr 29 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : web.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

use App\Http\Controllers\{AccountPasswordController, ActivityCategoryController, AdminTimeEntryController, ApiTokenController, ArchiveController, AssetController, AttachmentController, AttendanceController, AuditLogController, AvailabilityController, BrandingController, CalendarFeedController, CashRegisterController, CommentController, CommunicationNoteController, CoverageRequirementController, CustomerController, CustomerMergeController, CustomerQueryController, DashboardController, DiaryCaseFileController, DiaryController, DiaryExportController, DiaryLifecycleController, DispatchBoardController, DispatchController, DutyController, DutyPlanController, EmergencyAssignmentController, EnergyLogController, EventCategoryController, EventController, EventParticipantController, ExpenseApprovalController, ExpenseController, ExternalParticipantController, FlexController, FlexEligibilityController, ForeignCustomerController, GeocodeController, GlobalSearchController, HelpController, HolidayController, HomeController, IcsFeedController, InvoiceController, InvoiceScheduleController, KanbanController, LicenseController, LocaleController, MaterialController, MilestoneController, OnCallShiftController, OnboardingController, OpenIssueController, OrgMemberController, OrganizationController, OrganizationSwitchController, PayrollController, PerDiemTripController, PrintController, ProductController, ProfileController, ProjectBillingRuleController, ProjectController, ProjectMergeController, ProjectRecurrenceRuleController, ProtocolController, PublicAuditPackageController, PublicExternalParticipantController, PublicProtocolSignatureController, PublicSignatureController, PushSubscriptionController, QualificationController, QuickBookController, RoomController, SafetyEventController, ScheduleController, ScheduleImportController, ScheduledShiftController, ShiftExchangeController, ShiftTypeController, SickLeaveController, SoftwareController, SoftwareInstallationController, StopwatchController, SupplierController, SyncCommandController, TagController, TaskController, TeamController, TimeEntryBarController, TimeEntryCommentController, TimeEntryController, TimesheetController, TimesheetEntryController, TimesheetMaterialController, TimesheetSignatureController, TodayController, TourController, TravelLogController, UserBookmarkController, VacationController, VacationEntitlementController, VehicleController, VehicleReservationController, WeekController, WorkScheduleController};
use App\Http\Controllers\Admin\Access\{AccessHubController, MemberController as AccessMemberController, PermissionController as AccessPermissionController, RoleController as AccessRoleController, UserGroupController as AccessUserGroupController};
use App\Http\Controllers\Admin\{AutomationRuleController, BackupHeartbeatController, BackupStatusController, BranchProfileController, ClassificationController, ClassificationRequirementController, ComponentsController, DemoTenantController, DiagnosticsController, EntryTypeController, ExpenseCategoryController, ImportController, InvoiceMailTemplateController, LicenseAdminController, MaintenanceWindowController, MetricsController, OperationsTaskController, PerDiemRateController, PluginController as AdminPluginController, PluginErrorController as AdminPluginErrorController, PrivacyController, ProblemReportInboxController, SchedulerController, SecurityController, SessionController, SettingsController, SupportAccessAuditController, SupportAccessGrantController, SupportImpersonationController, SupportReportController};
use App\Http\Controllers\Asset\{AssetCheckoutController, AssetDefectController, MaintenancePlanController};
use App\Http\Controllers\Auth\{LoginController, PasswordResetController, TenantRegistrationController, TwoFactorChallengeController};
use App\Http\Controllers\KeyHandover\KeyHandoverController;
use App\Http\Controllers\MeterReading\MeterReadingController;
use App\Http\Controllers\{ProblemReportController, TwoFactorController};
use App\Http\Controllers\Reporting\{AbsencesReportController, AssetAnalysisReportController, AttendanceReportController, AuditActivityReportController, BillingReportController, CoverageReportController, CustomerAnalysisReportController, CustomerProjectReportController, EconomicsReportController, EntryTypeAnalysisReportController, EntryTypeDrilldownReportController, ExpenseReportController, ExternalPayoutReportController, FleetReportController, MaterialReportController, MonthByUserTeamReportController, MyMonthReportController, MyYearReportController, OnCallReportController, OperationsReportController, ProjectDetailsReportController, ProjectInactiveReportController, QualificationReportController, SicknessReportController, WeekByUserReportController, WorkBalanceReportController};
use App\Http\Controllers\ServiceTicket\ServiceTicketController;
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

// Lizenz-Aktivierung (Key einspielen). EnsureValidLicense sperrt nur noch bei
// Seal-/Integritätsverletzung; ohne Key läuft die App ohnehin als free.
Route::get('/license', [LicenseController::class, 'show'])->name('license.show');
Route::post('/license', [LicenseController::class, 'store'])->middleware('throttle:6,1')->name('license.store');

// Startseite (öffentlich)
Route::get('/', HomeController::class)->name('home');

// Rechtstexte (öffentlich, MVP-326): Inhalte pflegt der Betreiber über
// die Settings-Registry (legal.imprint / legal.privacy).
Route::get('/impressum', [\App\Http\Controllers\LegalPageController::class, 'imprint'])->name('legal.imprint');
Route::get('/datenschutz', [\App\Http\Controllers\LegalPageController::class, 'privacy'])->name('legal.privacy');

// CVD-Meldekanal nach RFC 9116 (öffentlich, CRA-Welle 1): 404 solange
// SECURITY_TXT_CONTACT nicht gesetzt ist. Top-Level-Pfad ist der vom RFC
// empfohlene Legacy-Fallback.
Route::get('/.well-known/security.txt', \App\Http\Controllers\SecurityTxtController::class)->name('security.txt');
Route::redirect('/security.txt', '/.well-known/security.txt');

// Auth
Route::get('/login', [LoginController::class, 'showLoginForm'])->name('login')->middleware('guest');
Route::post('/login', [LoginController::class, 'login'])->middleware(['guest', 'throttle:login']);
Route::post('/logout', [LoginController::class, 'logout'])->name('logout');

// Passwortloser Passkey-Primär-Login (MS365-Plan G3): Discoverable
// Credentials + User-Verification-Pflicht ersetzen Passwort UND 2FA.
Route::post('/login/passkey/options', [\App\Http\Controllers\Auth\PasskeyLoginController::class, 'options'])->middleware('guest')->name('login.passkey.options');
Route::post('/login/passkey', [\App\Http\Controllers\Auth\PasskeyLoginController::class, 'verify'])->middleware(['guest', 'throttle:login'])->name('login.passkey');

// Zweiter Login-Schritt (Zwei-Faktor): session-basiert (auth.2fa.id), daher
// weder guest- noch auth-Middleware. Der Controller prüft die Session selbst.
Route::get('/two-factor-challenge', [TwoFactorChallengeController::class, 'create'])->name('two-factor.login');
Route::post('/two-factor-challenge', [TwoFactorChallengeController::class, 'store'])->middleware('throttle:login')->name('two-factor.login.attempt');
Route::post('/two-factor-challenge/email', [TwoFactorChallengeController::class, 'email'])->middleware('throttle:login')->name('two-factor.login.email');
Route::post('/two-factor-challenge/webauthn/options', [TwoFactorChallengeController::class, 'webauthnOptions'])->name('two-factor.login.webauthn.options');
Route::post('/two-factor-challenge/webauthn', [TwoFactorChallengeController::class, 'webauthnVerify'])->middleware('throttle:login')->name('two-factor.login.webauthn');

// ── Single-Sign-on (Feature 057, MVP-120/121) ───────────────────────────────
// SP-initiierter Start je Organisation (Slug), OIDC-Callback (global, an der
// IdP-App registriert), SAML-ACS (POST vom IdP, CSRF-Ausnahme in
// bootstrap/app.php) und SP-Metadata. Modul-Gating im Controller.
Route::get('/sso', [\App\Http\Controllers\Auth\SsoController::class, 'discover'])->name('sso.discover')->middleware('guest');
Route::get('/sso/oidc/callback', [\App\Http\Controllers\Auth\SsoController::class, 'oidcCallback'])->name('sso.oidc.callback')->middleware('guest');
Route::get('/sso/{slug}/choose', [\App\Http\Controllers\Auth\SsoController::class, 'choose'])->name('sso.choose')->middleware('guest')->where('slug', '[a-z0-9-]+');
Route::get('/sso/{slug}/start', [\App\Http\Controllers\Auth\SsoController::class, 'start'])->name('sso.start')->middleware('guest')->where('slug', '[a-z0-9-]+');
Route::post('/sso/{slug}/saml/acs', [\App\Http\Controllers\Auth\SsoController::class, 'samlAcs'])->name('sso.saml.acs')->middleware('guest')->where('slug', '[a-z0-9-]+');
Route::get('/sso/{slug}/saml/metadata', [\App\Http\Controllers\Auth\SsoController::class, 'metadata'])->name('sso.saml.metadata')->where('slug', '[a-z0-9-]+');

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
// Öffentlicher Geräte-Pass (Feature 047/048, E2): opt-in pro Org, rate-limitiert,
// ohne personenbezogene Daten.
Route::get('serial-passport/{token}', [\App\Http\Controllers\PublicSerialController::class, 'show'])
    ->middleware('throttle:30,1')
    ->name('serials.public-passport');

// OCI-Punchout-Rücksprung (Feature 050, MVP-096): Der Shop POSTet den Warenkorb
// cross-site ohne Session-Cookie — Autorisierung über die beim Absprung erzeugte,
// zeitlich begrenzte signierte HOOK_URL (CSRF-Ausnahme in bootstrap/app.php).
Route::post('oci-carts/return', [\App\Http\Controllers\OciCartController::class, 'hookReturn'])
    ->middleware(['signed', 'throttle:12,1'])
    ->name('oci-carts.return');

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
// Ablehnung mit offenen Punkten + Kunden-Rückfrage (Feature 012)
Route::post('sign/protocol/{token}/reject', [PublicProtocolSignatureController::class, 'reject'])
    ->middleware('throttle:6,1')
    ->name('protocols.public-sign.reject');
Route::post('sign/protocol/{token}/query', [PublicProtocolSignatureController::class, 'query'])
    ->middleware('throttle:6,1')
    ->name('protocols.public-sign.query');

// Öffentlicher Prüfer-Download finalisierter ISMS-Auditpakete (Feature 046,
// Inkrement E): token-basiert ohne Login/Org-Session (nur Token-Hash wird
// gespeichert); widerrufen/abgelaufen/unbekannt ⇒ 404. Bewusst NICHT im
// isms.*-Namensraum, damit das Plan-Gating (module.isms) den anonymen
// Prüferzugriff nicht blockiert.
Route::get('audit-paket/{token}', [PublicAuditPackageController::class, 'download'])
    ->middleware('throttle:30,1')
    ->name('audit-packages.public-download');
// Feature 046 „Live-Prüferzugang": derselbe Token als navigierbare
// Read-Only-Webansicht des finalisierten (eingefrorenen) Pakets.
Route::get('audit-paket/{token}/ansicht', [PublicAuditPackageController::class, 'view'])
    ->middleware('throttle:30,1')
    ->name('audit-packages.public-view');

// Öffentlicher, login-freier Zugriff externer Beteiligter (Feature 033):
// kontextbezogene Read-Only-Seite eines Auftrags/Protokolls/Dokuments mit nur
// den per abilities erlaubten Aktionen. Token-basiert ohne Login/Org-Session
// (nur der SHA-256-Hash wird gespeichert); widerrufen/abgelaufen/unbekannt ⇒
// 404. Bewusst außerhalb von auth/EnforcePlanModules; abilities werden
// serverseitig je Aktion erzwungen (403). Throttle als Brute-Force-Schutz.
Route::get('extern/{token}', [PublicExternalParticipantController::class, 'show'])
    ->middleware('throttle:30,1')
    ->name('external.show');
Route::post('extern/{token}/kommentar', [PublicExternalParticipantController::class, 'comment'])
    ->middleware('throttle:12,1')
    ->name('external.comment');
Route::post('extern/{token}/upload', [PublicExternalParticipantController::class, 'upload'])
    ->middleware('throttle:12,1')
    ->name('external.upload');
Route::post('extern/{token}/bestaetigen', [PublicExternalParticipantController::class, 'confirm'])
    ->middleware('throttle:12,1')
    ->name('external.confirm');

// Kundenportal-Annahme eines Angebots (Feature 066, MVP-170): token-basiert
// ohne Login (nur der SHA-256-Hash ist gespeichert); ungültig/fehlend ⇒ 404.
// Bewusst außerhalb von auth/EnforcePlanModules; Throttle gegen Brute-Force.
Route::get('angebote/{quote}/annahme', [\App\Http\Controllers\QuoteController::class, 'portalShow'])
    ->middleware('throttle:30,1')
    ->name('quotes.portal.show');
Route::post('angebote/{quote}/annahme', [\App\Http\Controllers\QuoteController::class, 'portalDecide'])
    ->middleware('throttle:12,1')
    ->name('quotes.portal.decide');

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
    // Schnelle Theme-Persistenz für den Header-Umschalter (ohne ganzes Profil).
    Route::put('account/theme', [ProfileController::class, 'updateTheme'])->name('account.theme.update');

    // Notification-Center (MVP-018): eigene Benachrichtigungen, keine
    // Permission nötig — Controller arbeitet ausschließlich auf auth()->user().
    Route::get('notifications', [\App\Http\Controllers\NotificationCenterController::class, 'index'])->name('notifications.index');
    Route::post('notifications/read-all', [\App\Http\Controllers\NotificationCenterController::class, 'readAll'])->name('notifications.readAll');
    Route::post('notifications/{id}/read', [\App\Http\Controllers\NotificationCenterController::class, 'read'])->name('notifications.read');
    // destroyRead VOR destroy registrieren — sonst fängt {id} den Pfad "read" ab.
    Route::delete('notifications/read', [\App\Http\Controllers\NotificationCenterController::class, 'destroyRead'])->name('notifications.destroyRead');
    Route::delete('notifications/{id}', [\App\Http\Controllers\NotificationCenterController::class, 'destroy'])->name('notifications.destroy');

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

    // Per-User-Menüanpassung + Funktionskatalog (Feature 081, MVP-374/375).
    Route::get('me/navigation/customize', [\App\Http\Controllers\Me\NavigationCustomizationController::class, 'index'])
        ->name('me.navigation.customize');
    Route::post('me/navigation/customize', [\App\Http\Controllers\Me\NavigationCustomizationController::class, 'save'])
        ->name('me.navigation.customize.save');
    Route::post('me/navigation/unhide', [\App\Http\Controllers\Me\NavigationCustomizationController::class, 'unhide'])
        ->name('me.navigation.unhide');
    Route::get('me/functions', [\App\Http\Controllers\Me\FunctionCatalogController::class, 'index'])
        ->name('me.functions');

    // Arbeitsbereiche — schaltbare Fokus-Ansichten (Feature 082, MVP-378).
    Route::post('me/focus/{focus}', [\App\Http\Controllers\Me\FocusController::class, 'switch'])
        ->name('me.focus.switch');

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
                Route::get('/aushang', [\App\Http\Controllers\Whistleblowing\WhistleblowingPortalController::class, 'poster'])->name('poster');
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
            // Anlage aus Vorlagenkatalog (Feature 043 MVP 1; Vollaudit 2026-07, M17).
            Route::post('vvt/vorlage', [\App\Http\Controllers\Privacy\ProcessingActivityController::class, 'storeFromTemplate'])->name('activities.template');
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
            Route::delete('avv/{agreement}/subprozessoren/{subprocessor}', [\App\Http\Controllers\Privacy\ProcessingAgreementController::class, 'destroySubprocessor'])->name('agreements.subprocessor.destroy');

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
            // Geführter DSFA-Schritt-Workflow + PDF-Bericht (Nachtrag 043a)
            Route::post('vvt/{activity}/dsfa/schritt/{stepCode}', [\App\Http\Controllers\Privacy\DpiaController::class, 'completeStep'])->name('activities.dpia.step');
            Route::get('vvt/{activity}/dsfa/bericht', [\App\Http\Controllers\Privacy\DpiaController::class, 'report'])->name('activities.dpia.report');

            // TOM-Katalog (Art. 32)
            Route::get('tom', [\App\Http\Controllers\Privacy\TechnicalMeasureController::class, 'index'])->name('tom.index');
            Route::get('tom/neu', [\App\Http\Controllers\Privacy\TechnicalMeasureController::class, 'create'])->name('tom.create');
            Route::post('tom', [\App\Http\Controllers\Privacy\TechnicalMeasureController::class, 'store'])->name('tom.store');
            Route::get('tom/{measure}', [\App\Http\Controllers\Privacy\TechnicalMeasureController::class, 'show'])->name('tom.show');
            Route::post('tom/{measure}/version', [\App\Http\Controllers\Privacy\TechnicalMeasureController::class, 'addVersion'])->name('tom.version');
            Route::post('tom/{measure}/freigabe', [\App\Http\Controllers\Privacy\TechnicalMeasureController::class, 'approve'])->name('tom.approve');
            Route::post('tom/{measure}/zuordnung', [\App\Http\Controllers\Privacy\TechnicalMeasureController::class, 'assignActivity'])->name('tom.assign');
            Route::post('tom/{measure}/pruefung', [\App\Http\Controllers\Privacy\TechnicalMeasureController::class, 'review'])->name('tom.review');
            // Nachweisanhänge mit Gültig-bis (Nachtrag 043b)
            Route::post('tom/{measure}/anhang', [\App\Http\Controllers\Privacy\PrivacyAttachmentController::class, 'storeForMeasure'])->name('tom.attachment.store');

            // GVV – Gemeinsam Verantwortliche (Art. 26)
            Route::get('gvv', [\App\Http\Controllers\Privacy\JointControllerAgreementController::class, 'index'])->name('gvv.index');
            Route::post('gvv', [\App\Http\Controllers\Privacy\JointControllerAgreementController::class, 'store'])->name('gvv.store');
            Route::get('gvv/{gvv}', [\App\Http\Controllers\Privacy\JointControllerAgreementController::class, 'show'])->name('gvv.show');
            Route::put('gvv/{gvv}', [\App\Http\Controllers\Privacy\JointControllerAgreementController::class, 'update'])->name('gvv.update');
            Route::get('gvv/{gvv}/dokument', [\App\Http\Controllers\Privacy\JointControllerAgreementController::class, 'downloadDocument'])->name('gvv.document');
            Route::post('gvv/{gvv}/taetigkeiten', [\App\Http\Controllers\Privacy\JointControllerAgreementController::class, 'syncActivities'])->name('gvv.activities');

            // Compliance-/Lueckenanalyse
            // Aufbewahrungs-Review (Restpunkt 66): Vorschläge sichten + bestätigen.
            Route::get('aufbewahrung', [\App\Http\Controllers\Privacy\RetentionController::class, 'index'])->name('retention.index');
            Route::post('aufbewahrung/scan', [\App\Http\Controllers\Privacy\RetentionController::class, 'scan'])->name('retention.scan');
            Route::post('aufbewahrung/{proposal}', [\App\Http\Controllers\Privacy\RetentionController::class, 'decide'])->name('retention.decide');
            Route::post('aufbewahrung-bereich/loeschen', [\App\Http\Controllers\Privacy\RetentionController::class, 'purgeArea'])->name('retention.purge-area');

            Route::get('luecken', [\App\Http\Controllers\Privacy\ComplianceController::class, 'index'])->name('compliance.index');
            Route::post('luecken/analyse', [\App\Http\Controllers\Privacy\ComplianceController::class, 'run'])->name('compliance.run');
            Route::put('luecken/{finding}', [\App\Http\Controllers\Privacy\ComplianceController::class, 'update'])->name('compliance.update');
            // Konfigurierbarer Anforderungskatalog (Nachtrag 043c)
            Route::put('luecken/katalog/{requirement}', [\App\Http\Controllers\Privacy\ComplianceController::class, 'updateRequirement'])->name('compliance.requirement.update');

            // Anhaenge an Fallakten (Anfragen/Vorfaelle)
            Route::post('anfragen/{dsr}/anhaenge', [\App\Http\Controllers\Privacy\PrivacyAttachmentController::class, 'storeForRequest'])->name('requests.attach');
            Route::post('vorfaelle/{incident}/anhaenge', [\App\Http\Controllers\Privacy\PrivacyAttachmentController::class, 'storeForIncident'])->name('incidents.attach');
            Route::get('anhaenge/{attachment}', [\App\Http\Controllers\Privacy\PrivacyAttachmentController::class, 'download'])->name('attachment.download');
            Route::delete('anhaenge/{attachment}', [\App\Http\Controllers\Privacy\PrivacyAttachmentController::class, 'destroy'])->name('attachment.destroy');

            // Meldungsentwuerfe (Vorfall) + TOM-Zuordnung zu AVV
            Route::get('vorfaelle/{incident}/meldung/{kind}', [\App\Http\Controllers\Privacy\IncidentController::class, 'reportDraft'])->name('incidents.draft');
            Route::post('avv/{agreement}/tom', [\App\Http\Controllers\Privacy\ProcessingAgreementController::class, 'assignMeasure'])->name('agreements.tom');
        });

        // ── ISMS / ISO-27001-Auditbereitschaft (Feature 044 auf dem ───────
        // gemeinsamen Managementsystem-Kern aus Feature 046).
        // Modul-Gate via module.isms (config/plans.routes, NUR Enterprise);
        // Autorisierung pro Aktion ueber die Isms-Policies (isms.viewAny/
        // view fuer Lesen+SoA, isms.manage fuer Pflege/Katalog-Import).
        Route::prefix('compliance/isms')->name('isms.')->group(function (): void {
            // Auditbereitschafts-Dashboard (Feature 044, MVP 1): KPI-Kacheln
            // + Drill-down je Geltungsbereich (ReadinessService, Lesesicht).
            Route::get('auditbereitschaft', [\App\Http\Controllers\Isms\DashboardController::class, 'index'])->name('dashboard');

            // NIST-CSF-2.0-Sichten (Nachtrag NIST): Funktionsabdeckung je
            // Geltungsbereich (direkt aus NIST-SoA oder abgeleitet aus der
            // ISO-SoA via Crosswalk) + CSF→ISO/IEC-27001-Zuordnung. Lesesicht
            // (CsfReadinessService), Autorisierung wie übriger ISMS-Lesezugriff.
            Route::get('nist-csf', [\App\Http\Controllers\Isms\CsfController::class, 'dashboard'])->name('csf');
            Route::get('nist-csf/crosswalk', [\App\Http\Controllers\Isms\CsfController::class, 'crosswalk'])->name('csf.crosswalk');

            // Risikoregister (Export: Direkt-Export JSON/CSV, ?format=...)
            Route::get('risiken', [\App\Http\Controllers\Isms\RiskController::class, 'index'])->name('risks.index');
            Route::get('risiken/export', [\App\Http\Controllers\Isms\RiskController::class, 'export'])->name('risks.export');
            Route::get('risiken/neu', [\App\Http\Controllers\Isms\RiskController::class, 'create'])->name('risks.create');
            Route::post('risiken', [\App\Http\Controllers\Isms\RiskController::class, 'store'])->name('risks.store');
            Route::get('risiken/{risk}/bearbeiten', [\App\Http\Controllers\Isms\RiskController::class, 'edit'])->name('risks.edit');
            Route::put('risiken/{risk}', [\App\Http\Controllers\Isms\RiskController::class, 'update'])->name('risks.update');
            Route::post('risiken/{risk}/status', [\App\Http\Controllers\Isms\RiskController::class, 'transition'])->name('risks.transition');
            Route::delete('risiken/{risk}', [\App\Http\Controllers\Isms\RiskController::class, 'destroy'])->name('risks.destroy');

            // Bewertungshistorie (Feature 046, Inkrement D): neue Staende
            // statt Ueberschreiben; Freigabe friert ein (RiskService).
            Route::get('risiken/{risk}/bewertungen/neu', [\App\Http\Controllers\Isms\RiskController::class, 'createAssessment'])->name('risks.assessments.create');
            Route::post('risiken/{risk}/bewertungen', [\App\Http\Controllers\Isms\RiskController::class, 'storeAssessment'])->name('risks.assessments.store');
            Route::post('bewertungen/{assessment}/freigeben', [\App\Http\Controllers\Isms\RiskController::class, 'approveAssessment'])->name('risks.assessments.approve');
            Route::delete('bewertungen/{assessment}', [\App\Http\Controllers\Isms\RiskController::class, 'destroyAssessment'])->name('risks.assessments.destroy');

            // Sicherheitsvorfälle (Feature 044, MVP 2): Vorfallregister mit
            // Statusmaschine (closed erfordert Ursache + Lessons Learned) und
            // Rückführung in Risiken/Maßnahmen (SecurityIncidentService).
            Route::get('vorfaelle', [\App\Http\Controllers\Isms\SecurityIncidentController::class, 'index'])->name('incidents.index');
            Route::get('vorfaelle/neu', [\App\Http\Controllers\Isms\SecurityIncidentController::class, 'create'])->name('incidents.create');
            Route::post('vorfaelle', [\App\Http\Controllers\Isms\SecurityIncidentController::class, 'store'])->name('incidents.store');
            Route::get('vorfaelle/{incident}/bearbeiten', [\App\Http\Controllers\Isms\SecurityIncidentController::class, 'edit'])->name('incidents.edit');
            Route::put('vorfaelle/{incident}', [\App\Http\Controllers\Isms\SecurityIncidentController::class, 'update'])->name('incidents.update');
            Route::post('vorfaelle/{incident}/status', [\App\Http\Controllers\Isms\SecurityIncidentController::class, 'transition'])->name('incidents.transition');
            Route::delete('vorfaelle/{incident}', [\App\Http\Controllers\Isms\SecurityIncidentController::class, 'destroy'])->name('incidents.destroy');

            // Schwachstellenregister (Feature 044, MVP 2): CVSS-Severity,
            // Inventar-Bezug, Fristen und die bewusste, begründete
            // Ausnutzbarkeits-Entscheidung (VulnerabilityService).
            Route::get('schwachstellen', [\App\Http\Controllers\Isms\VulnerabilityController::class, 'index'])->name('vulnerabilities.index');
            Route::get('schwachstellen/neu', [\App\Http\Controllers\Isms\VulnerabilityController::class, 'create'])->name('vulnerabilities.create');
            Route::post('schwachstellen', [\App\Http\Controllers\Isms\VulnerabilityController::class, 'store'])->name('vulnerabilities.store');
            Route::get('schwachstellen/{vulnerability}/bearbeiten', [\App\Http\Controllers\Isms\VulnerabilityController::class, 'edit'])->name('vulnerabilities.edit');
            Route::put('schwachstellen/{vulnerability}', [\App\Http\Controllers\Isms\VulnerabilityController::class, 'update'])->name('vulnerabilities.update');
            Route::post('schwachstellen/{vulnerability}/status', [\App\Http\Controllers\Isms\VulnerabilityController::class, 'transition'])->name('vulnerabilities.transition');
            Route::get('schwachstellen/{vulnerability}/betroffenheit', [\App\Http\Controllers\Isms\VulnerabilityController::class, 'editDecision'])->name('vulnerabilities.decision');
            Route::post('schwachstellen/{vulnerability}/betroffenheit', [\App\Http\Controllers\Isms\VulnerabilityController::class, 'decide'])->name('vulnerabilities.decide');
            Route::delete('schwachstellen/{vulnerability}', [\App\Http\Controllers\Isms\VulnerabilityController::class, 'destroy'])->name('vulnerabilities.destroy');

            // Advisories (Feature 044, MVP 2): CSAF/VEX-Import + Nachweis-Ablage
            // (SHA-256), Inventar-/SBOM-Abgleich (AdvisoryImportService).
            // Mehrjähriges Auditprogramm (Nachtrag 044d)
            Route::get('auditprogramme', [\App\Http\Controllers\Isms\AuditProgramController::class, 'index'])->name('audit-programs.index');
            Route::post('auditprogramme', [\App\Http\Controllers\Isms\AuditProgramController::class, 'store'])->name('audit-programs.store');
            Route::put('auditprogramme/{program}', [\App\Http\Controllers\Isms\AuditProgramController::class, 'update'])->name('audit-programs.update');
            Route::delete('auditprogramme/{program}', [\App\Http\Controllers\Isms\AuditProgramController::class, 'destroy'])->name('audit-programs.destroy');

            Route::get('advisories', [\App\Http\Controllers\Isms\AdvisoryController::class, 'index'])->name('advisories.index');
            Route::get('advisories/import', [\App\Http\Controllers\Isms\AdvisoryController::class, 'create'])->name('advisories.create');
            Route::post('advisories/import', [\App\Http\Controllers\Isms\AdvisoryController::class, 'store'])->name('advisories.store');
            Route::post('advisories/feed-pull', [\App\Http\Controllers\Isms\AdvisoryController::class, 'pullFeed'])->middleware('throttle:6,1')->name('advisories.feed-pull');

            // Lieferantenbewertung (Feature 044, MVP 2/3 „Lieferanten und
            // Verträge"): Kritikalitäts-/Risikobewertung, Sicherheits-
            // anforderungen, Vertragsmerkmale (NDA/AVV/Prüfungsrecht) und
            // wiederkehrende Reviews (SupplierAssessmentService). Überfällige
            // Reviews fließen in die Auditbereitschaft (ungeprüfte Lieferanten).
            Route::get('lieferanten', [\App\Http\Controllers\Isms\SupplierAssessmentController::class, 'index'])->name('suppliers.index');
            Route::get('lieferanten/neu', [\App\Http\Controllers\Isms\SupplierAssessmentController::class, 'create'])->name('suppliers.create');
            Route::post('lieferanten', [\App\Http\Controllers\Isms\SupplierAssessmentController::class, 'store'])->name('suppliers.store');
            Route::get('lieferanten/{supplier}/bearbeiten', [\App\Http\Controllers\Isms\SupplierAssessmentController::class, 'edit'])->name('suppliers.edit');
            Route::put('lieferanten/{supplier}', [\App\Http\Controllers\Isms\SupplierAssessmentController::class, 'update'])->name('suppliers.update');
            Route::post('lieferanten/{supplier}/status', [\App\Http\Controllers\Isms\SupplierAssessmentController::class, 'transition'])->name('suppliers.transition');
            Route::delete('lieferanten/{supplier}', [\App\Http\Controllers\Isms\SupplierAssessmentController::class, 'destroy'])->name('suppliers.destroy');

            // Reifegrad-/Readiness-Assessment (Feature 044, MVP 3): begründete
            // SELBSTEINSCHÄTZUNG der Auditbereitschaft je Geltungsbereich aus
            // den ISMS-Registern (ReadinessAssessmentService). Nie automatische
            // Konformitätsbehauptung — explizit als Selbsteinschätzung markiert.
            Route::get('readiness', [\App\Http\Controllers\Isms\ReadinessController::class, 'index'])->name('readiness');

            // Anforderungen + SoA-Aussagen je Geltungsbereich inkl.
            // Normkatalog-Import (Normprofil-Registry, profile-Pflichtparameter)
            Route::get('anforderungen', [\App\Http\Controllers\Isms\RequirementController::class, 'index'])->name('requirements.index');
            Route::get('anforderungen/export', [\App\Http\Controllers\Isms\RequirementController::class, 'export'])->name('requirements.export');
            Route::get('anforderungen/neu', [\App\Http\Controllers\Isms\RequirementController::class, 'create'])->name('requirements.create');
            Route::post('anforderungen', [\App\Http\Controllers\Isms\RequirementController::class, 'store'])->name('requirements.store');
            Route::post('anforderungen/katalog', [\App\Http\Controllers\Isms\RequirementController::class, 'import'])->name('requirements.import');
            Route::post('anforderungen/oscal', [\App\Http\Controllers\Isms\RequirementController::class, 'importOscal'])->name('requirements.import-oscal');
            Route::get('anforderungen/{requirement}/bearbeiten', [\App\Http\Controllers\Isms\RequirementController::class, 'edit'])->name('requirements.edit');
            Route::put('anforderungen/{requirement}', [\App\Http\Controllers\Isms\RequirementController::class, 'update'])->name('requirements.update');
            Route::delete('anforderungen/{requirement}', [\App\Http\Controllers\Isms\RequirementController::class, 'destroy'])->name('requirements.destroy');
            Route::post('geltungsbereiche/{scope}/aussagen', [\App\Http\Controllers\Isms\RequirementController::class, 'ensureStatements'])->name('statements.ensure');
            Route::get('aussagen/{statement}/bearbeiten', [\App\Http\Controllers\Isms\RequirementController::class, 'editStatement'])->name('statements.edit');
            Route::put('aussagen/{statement}', [\App\Http\Controllers\Isms\RequirementController::class, 'updateStatement'])->name('statements.update');

            // Normneutrale Massnahmen (Mapping auf Anforderungen + Risiken)
            Route::get('massnahmen', [\App\Http\Controllers\Isms\ControlController::class, 'index'])->name('controls.index');
            Route::get('massnahmen/export', [\App\Http\Controllers\Isms\ControlController::class, 'export'])->name('controls.export');
            Route::get('massnahmen/neu', [\App\Http\Controllers\Isms\ControlController::class, 'create'])->name('controls.create');
            Route::post('massnahmen', [\App\Http\Controllers\Isms\ControlController::class, 'store'])->name('controls.store');
            Route::get('massnahmen/{control}/bearbeiten', [\App\Http\Controllers\Isms\ControlController::class, 'edit'])->name('controls.edit');
            Route::put('massnahmen/{control}', [\App\Http\Controllers\Isms\ControlController::class, 'update'])->name('controls.update');
            Route::delete('massnahmen/{control}', [\App\Http\Controllers\Isms\ControlController::class, 'destroy'])->name('controls.destroy');

            // Geltungsbereiche (nur isms.manage; Default-Scope nicht loeschbar)
            Route::get('geltungsbereiche', [\App\Http\Controllers\Isms\ScopeController::class, 'index'])->name('scopes.index');
            Route::get('geltungsbereiche/neu', [\App\Http\Controllers\Isms\ScopeController::class, 'create'])->name('scopes.create');
            Route::post('geltungsbereiche', [\App\Http\Controllers\Isms\ScopeController::class, 'store'])->name('scopes.store');
            Route::get('geltungsbereiche/{scope}/bearbeiten', [\App\Http\Controllers\Isms\ScopeController::class, 'edit'])->name('scopes.edit');
            Route::put('geltungsbereiche/{scope}', [\App\Http\Controllers\Isms\ScopeController::class, 'update'])->name('scopes.update');
            Route::delete('geltungsbereiche/{scope}', [\App\Http\Controllers\Isms\ScopeController::class, 'destroy'])->name('scopes.destroy');

            // Statement of Applicability (druckbare Read-Only-Sicht)
            Route::get('soa', \App\Http\Controllers\Isms\SoaController::class)->name('soa');

            // Zertifizierungen (Feature 046, Inkrement B): Konformitaetsstatus
            // je Scope + Norm/Ausgabe (Statuskette; certified NUR mit heute
            // gueltigem Zertifikat — ConformityService) + Zertifikatsregister.
            Route::get('zertifizierungen', [\App\Http\Controllers\Isms\ConformityController::class, 'index'])->name('conformity.index');
            Route::get('zertifizierungen/neu', [\App\Http\Controllers\Isms\ConformityController::class, 'create'])->name('conformity.create');
            Route::post('zertifizierungen', [\App\Http\Controllers\Isms\ConformityController::class, 'store'])->name('conformity.store');
            Route::post('zertifizierungen/{scope}/anlegen', [\App\Http\Controllers\Isms\ConformityController::class, 'ensure'])->name('conformity.ensure');
            Route::post('zertifizierungen/{normStatus}/status', [\App\Http\Controllers\Isms\ConformityController::class, 'transition'])->name('conformity.transition');
            Route::get('zertifizierungen/{normStatus}/zertifikat/neu', [\App\Http\Controllers\Isms\ConformityController::class, 'createCertificate'])->name('conformity.certificates.create');
            Route::post('zertifizierungen/{normStatus}/zertifikat', [\App\Http\Controllers\Isms\ConformityController::class, 'storeCertificate'])->name('conformity.certificates.store');

            // Audits inkl. Feststellungen + Korrekturmassnahmen (Feature 046,
            // Inkrement C): Statusketten + Abschluss-/Wirksamkeitsregeln
            // zentral im AuditService.
            Route::get('audits', [\App\Http\Controllers\Isms\AuditController::class, 'index'])->name('audits.index');
            Route::get('audits/neu', [\App\Http\Controllers\Isms\AuditController::class, 'create'])->name('audits.create');
            Route::post('audits', [\App\Http\Controllers\Isms\AuditController::class, 'store'])->name('audits.store');
            Route::get('audits/{audit}/bearbeiten', [\App\Http\Controllers\Isms\AuditController::class, 'edit'])->name('audits.edit');
            Route::put('audits/{audit}', [\App\Http\Controllers\Isms\AuditController::class, 'update'])->name('audits.update');
            Route::post('audits/{audit}/status', [\App\Http\Controllers\Isms\AuditController::class, 'transition'])->name('audits.transition');
            Route::delete('audits/{audit}', [\App\Http\Controllers\Isms\AuditController::class, 'destroy'])->name('audits.destroy');
            Route::get('audits/{audit}/feststellungen/neu', [\App\Http\Controllers\Isms\AuditController::class, 'createFinding'])->name('audits.findings.create');
            Route::post('audits/{audit}/feststellungen', [\App\Http\Controllers\Isms\AuditController::class, 'storeFinding'])->name('audits.findings.store');
            Route::get('feststellungen/{finding}/bearbeiten', [\App\Http\Controllers\Isms\AuditController::class, 'editFinding'])->name('audits.findings.edit');
            Route::put('feststellungen/{finding}', [\App\Http\Controllers\Isms\AuditController::class, 'updateFinding'])->name('audits.findings.update');
            Route::post('feststellungen/{finding}/status', [\App\Http\Controllers\Isms\AuditController::class, 'transitionFinding'])->name('audits.findings.transition');
            Route::delete('feststellungen/{finding}', [\App\Http\Controllers\Isms\AuditController::class, 'destroyFinding'])->name('audits.findings.destroy');
            Route::get('feststellungen/{finding}/massnahmen/neu', [\App\Http\Controllers\Isms\AuditController::class, 'createAction'])->name('audits.actions.create');
            Route::post('feststellungen/{finding}/massnahmen', [\App\Http\Controllers\Isms\AuditController::class, 'storeAction'])->name('audits.actions.store');
            Route::get('korrekturmassnahmen/{action}/bearbeiten', [\App\Http\Controllers\Isms\AuditController::class, 'editAction'])->name('audits.actions.edit');
            Route::put('korrekturmassnahmen/{action}', [\App\Http\Controllers\Isms\AuditController::class, 'updateAction'])->name('audits.actions.update');
            Route::post('korrekturmassnahmen/{action}/status', [\App\Http\Controllers\Isms\AuditController::class, 'transitionAction'])->name('audits.actions.transition');
            Route::delete('korrekturmassnahmen/{action}', [\App\Http\Controllers\Isms\AuditController::class, 'destroyAction'])->name('audits.actions.destroy');

            // Managementbewertung (Feature 046, Inkrement C): Freigabe setzt
            // Person + Zeitpunkt; freigegebene Protokolle unveraenderlich.
            Route::get('managementbewertungen', [\App\Http\Controllers\Isms\ManagementReviewController::class, 'index'])->name('reviews.index');
            Route::get('managementbewertungen/neu', [\App\Http\Controllers\Isms\ManagementReviewController::class, 'create'])->name('reviews.create');
            Route::post('managementbewertungen', [\App\Http\Controllers\Isms\ManagementReviewController::class, 'store'])->name('reviews.store');
            Route::get('managementbewertungen/{review}', [\App\Http\Controllers\Isms\ManagementReviewController::class, 'show'])->name('reviews.show');
            Route::get('managementbewertungen/{review}/bearbeiten', [\App\Http\Controllers\Isms\ManagementReviewController::class, 'edit'])->name('reviews.edit');
            Route::put('managementbewertungen/{review}', [\App\Http\Controllers\Isms\ManagementReviewController::class, 'update'])->name('reviews.update');
            Route::post('managementbewertungen/{review}/freigeben', [\App\Http\Controllers\Isms\ManagementReviewController::class, 'approve'])->name('reviews.approve');
            Route::delete('managementbewertungen/{review}', [\App\Http\Controllers\Isms\ManagementReviewController::class, 'destroy'])->name('reviews.destroy');

            // Auditpakete (Feature 046, Inkrement E): stichtagsbezogene,
            // integritätsgeschützte JSON-Exporte (finalize friert ein,
            // verify prüft den SHA-256) + zeitlich begrenzte Prüfer-Links
            // (Token-Erstellung/-Widerruf; der öffentliche Download liegt
            // als audit-packages.public-download außerhalb dieser Gruppe).
            Route::get('auditpakete', [\App\Http\Controllers\Isms\AuditPackageController::class, 'index'])->name('packages.index');
            Route::get('auditpakete/neu', [\App\Http\Controllers\Isms\AuditPackageController::class, 'create'])->name('packages.create');
            Route::post('auditpakete', [\App\Http\Controllers\Isms\AuditPackageController::class, 'store'])->name('packages.store');
            Route::post('auditpakete/{package}/finalisieren', [\App\Http\Controllers\Isms\AuditPackageController::class, 'finalize'])->name('packages.finalize');
            Route::post('auditpakete/{package}/pruefen', [\App\Http\Controllers\Isms\AuditPackageController::class, 'verify'])->name('packages.verify');
            Route::get('auditpakete/{package}/download', [\App\Http\Controllers\Isms\AuditPackageController::class, 'download'])->name('packages.download');
            Route::get('auditpakete/{package}/pruefer-links/neu', [\App\Http\Controllers\Isms\AuditPackageController::class, 'createToken'])->name('packages.tokens.create');
            Route::post('auditpakete/{package}/pruefer-links', [\App\Http\Controllers\Isms\AuditPackageController::class, 'storeToken'])->name('packages.tokens.store');
            Route::post('pruefer-links/{token}/widerrufen', [\App\Http\Controllers\Isms\AuditPackageController::class, 'revokeToken'])->name('packages.tokens.revoke');

            // Softwareinventar (Ebene 1): Produkte + Installationen
            Route::get('software', [\App\Http\Controllers\Isms\SoftwareController::class, 'index'])->name('software.index');
            Route::get('software/neu', [\App\Http\Controllers\Isms\SoftwareController::class, 'create'])->name('software.create');
            Route::post('software', [\App\Http\Controllers\Isms\SoftwareController::class, 'store'])->name('software.store');
            Route::get('software/{product}/bearbeiten', [\App\Http\Controllers\Isms\SoftwareController::class, 'edit'])->name('software.edit');
            Route::put('software/{product}', [\App\Http\Controllers\Isms\SoftwareController::class, 'update'])->name('software.update');
            Route::delete('software/{product}', [\App\Http\Controllers\Isms\SoftwareController::class, 'destroy'])->name('software.destroy');
            Route::get('software/{product}/installationen/neu', [\App\Http\Controllers\Isms\SoftwareController::class, 'createInstallation'])->name('software.installations.create');
            Route::post('software/{product}/installationen', [\App\Http\Controllers\Isms\SoftwareController::class, 'storeInstallation'])->name('software.installations.store');
            Route::get('installationen/{installation}/bearbeiten', [\App\Http\Controllers\Isms\SoftwareController::class, 'editInstallation'])->name('software.installations.edit');
            Route::put('installationen/{installation}', [\App\Http\Controllers\Isms\SoftwareController::class, 'updateInstallation'])->name('software.installations.update');
            Route::delete('installationen/{installation}', [\App\Http\Controllers\Isms\SoftwareController::class, 'destroyInstallation'])->name('software.installations.destroy');
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

        // Einstellungs-Registry (Feature 067, MVP-174)
        Route::get('admin/settings', [SettingsController::class, 'index'])->name('admin.settings.index');
        // Konfigurationsstand-Export (Feature 067 P5; Vollaudit 2026-07, N20).
        Route::get('admin/settings/export.json', [SettingsController::class, 'export'])->name('admin.settings.export');
        Route::get('admin/settings/{key}/history', [SettingsController::class, 'history'])
            ->where('key', '[A-Za-z0-9_.\-]+')
            ->name('admin.settings.history');
        Route::put('admin/settings/{key}', [SettingsController::class, 'update'])
            ->where('key', '[A-Za-z0-9_.\-]+')
            ->name('admin.settings.update');
        Route::delete('admin/settings/{key}', [SettingsController::class, 'reset'])
            ->where('key', '[A-Za-z0-9_.\-]+')
            ->name('admin.settings.reset');

        // Wartungsfenster (Feature 022/041, MVP-055)
        Route::get('admin/maintenance-windows', [MaintenanceWindowController::class, 'index'])->name('admin.maintenance-windows.index');
        Route::get('admin/maintenance-windows/create', [MaintenanceWindowController::class, 'create'])->name('admin.maintenance-windows.create');
        Route::post('admin/maintenance-windows', [MaintenanceWindowController::class, 'store'])->name('admin.maintenance-windows.store');
        Route::post('admin/maintenance-windows/{maintenanceWindow}/{action}', [MaintenanceWindowController::class, 'transition'])
            ->whereIn('action', ['announce', 'start', 'complete', 'extend', 'rollback', 'cancel'])
            ->name('admin.maintenance-windows.transition');

        // Admin-Aufgabencenter (Feature 041, MVP-058)
        Route::get('admin/operations', [OperationsTaskController::class, 'index'])->name('admin.operations.index');
        Route::post('admin/operations/{operationsTask}/done', [OperationsTaskController::class, 'done'])->name('admin.operations.done');
        Route::post('admin/operations/{operationsTask}/reopen', [OperationsTaskController::class, 'reopen'])->name('admin.operations.reopen');
        Route::post('admin/operations/{operationsTask}/snooze', [OperationsTaskController::class, 'snooze'])->name('admin.operations.snooze');
        Route::post('admin/operations/{operationsTask}/delegate', [OperationsTaskController::class, 'delegate'])->name('admin.operations.delegate');
        Route::post('admin/operations/{operationsTask}/ignore', [OperationsTaskController::class, 'ignore'])->name('admin.operations.ignore');

        // Fehlermeldesystem (Feature 041, MVP-053)
        Route::get('problem-reports', [ProblemReportController::class, 'index'])->name('problem-reports.index');
        Route::get('problem-reports/create', [ProblemReportController::class, 'create'])->name('problem-reports.create');
        Route::post('problem-reports', [ProblemReportController::class, 'store'])
            ->middleware('throttle:10,10')
            ->name('problem-reports.store');
        Route::get('admin/problem-reports', [ProblemReportInboxController::class, 'index'])->name('admin.problem-reports.index');
        Route::get('admin/problem-reports/{problemReport}', [ProblemReportInboxController::class, 'show'])->name('admin.problem-reports.show');
        Route::put('admin/problem-reports/{problemReport}/status', [ProblemReportInboxController::class, 'updateStatus'])->name('admin.problem-reports.status');
        Route::post('admin/problem-reports/{problemReport}/convert', [ProblemReportInboxController::class, 'convertToTicket'])->name('admin.problem-reports.convert');
        Route::get('admin/problem-reports/{problemReport}/download', [ProblemReportInboxController::class, 'download'])->name('admin.problem-reports.download');

        // Update-Verfügbarkeit (Feature 022, MVP-054)
        Route::post('admin/components/updates/check', [ComponentsController::class, 'checkUpdates'])
            ->middleware('throttle:4,10')
            ->name('admin.components.updates.check');
        Route::post('admin/components/updates/import', [ComponentsController::class, 'importUpdates'])->name('admin.components.updates.import');
        Route::post('admin/components/updates/{componentUpdate}/snooze', [ComponentsController::class, 'snoozeUpdate'])->name('admin.components.updates.snooze');
        Route::post('admin/components/updates/{componentUpdate}/acknowledge', [ComponentsController::class, 'acknowledgeUpdate'])->name('admin.components.updates.acknowledge');

        // Scheduler-Steuerung (Feature 067, MVP-176/177)
        Route::get('admin/scheduler', [SchedulerController::class, 'index'])->name('admin.scheduler.index');
        Route::get('admin/scheduler/{job}/edit', [SchedulerController::class, 'edit'])->name('admin.scheduler.edit');
        Route::put('admin/scheduler/{job}', [SchedulerController::class, 'update'])->name('admin.scheduler.update');
        Route::post('admin/scheduler/{job}/pause', [SchedulerController::class, 'pause'])->name('admin.scheduler.pause');
        Route::post('admin/scheduler/{job}/resume', [SchedulerController::class, 'resume'])->name('admin.scheduler.resume');
        Route::post('admin/scheduler/{job}/reset', [SchedulerController::class, 'reset'])->name('admin.scheduler.reset');
        Route::post('admin/scheduler/{job}/test-run', [SchedulerController::class, 'testRun'])
            ->middleware('throttle:6,1')
            ->name('admin.scheduler.test-run');

        // Betriebsmetriken (Feature 036)
        Route::get('admin/metrics', [MetricsController::class, 'index'])->name('admin.metrics.index');

        // Offline-Synchronisierung (Feature 004-Restpunkt): Welche Daten sind
        // mobil/offline entstanden, und sind sie angekommen?
        Route::get('admin/offline-sync', [\App\Http\Controllers\Admin\OfflineSyncController::class, 'index'])->name('admin.offline-sync.index');

        // Admin-Sicherheitsübersicht (Feature 016) — read-only Aggregation
        // sicherheitsrelevanter Zustände (Sessions, API-Tokens, Integrationen,
        // letzte Exporte/Supportzugriffe, 2FA-/Verschlüsselungs-Status).
        // Datenführerschaft-Matrix (Restpunkt 69).
        Route::get('admin/datenfuehrerschaft', [\App\Http\Controllers\Admin\DataOwnershipController::class, 'index'])->name('admin.data-ownership.index');
        Route::post('admin/datenfuehrerschaft', [\App\Http\Controllers\Admin\DataOwnershipController::class, 'update'])->name('admin.data-ownership.update');
        Route::get('admin/security', [SecurityController::class, 'index'])->name('admin.security.index');
        // Sicherheitslage der Abhängigkeiten (Rang 70): OSV-Abruf + VEX-Bewertung.
        Route::post('admin/security/advisories/pull', [SecurityController::class, 'pullAdvisories'])
            ->middleware('throttle:6,1')
            ->name('admin.security.advisories.pull');
        Route::put('admin/security/advisories/{advisory}/statement', [SecurityController::class, 'updateAdvisoryStatement'])
            ->name('admin.security.advisories.statement');

        // Quelltext-Integrität (Feature 095, MVP-442): Ampel + Befundliste,
        // Prüf-/Freeze-Läufe als Queue-Jobs — nur Plattform-Admin (Controller).
        Route::get('admin/integrity', [\App\Http\Controllers\Admin\IntegrityController::class, 'index'])->name('admin.integrity.index');
        Route::post('admin/integrity/verify', [\App\Http\Controllers\Admin\IntegrityController::class, 'verify'])
            ->middleware('throttle:6,1')
            ->name('admin.integrity.verify');
        Route::post('admin/integrity/freeze', [\App\Http\Controllers\Admin\IntegrityController::class, 'freeze'])
            ->middleware('throttle:6,1')
            ->name('admin.integrity.freeze');

        // Angriffserkennung (Feature 096, MVP-445): Security-Events-Dashboard —
        // nur Plattform-Admin (Controller), plattformweite Daten.
        Route::get('admin/security-events', [\App\Http\Controllers\Admin\SecurityEventsController::class, 'index'])->name('admin.security-events.index');

        // Angemeldete Nutzer / Sitzungen (Feature 085): auflisten + fernabmelden.
        Route::get('admin/sessions', [SessionController::class, 'index'])->name('admin.sessions.index');
        Route::get('admin/sessions/data', [SessionController::class, 'data'])->name('admin.sessions.data');
        Route::delete('admin/sessions/user/{userSqid}', [SessionController::class, 'destroyAllForUser'])
            ->name('admin.sessions.user.destroy');
        Route::delete('admin/sessions/tokens/{tokenSqid}', [SessionController::class, 'destroyToken'])
            ->name('admin.sessions.tokens.destroy');
        Route::delete('admin/sessions/devices/{deviceSqid}', [SessionController::class, 'destroyLocationDevice'])
            ->name('admin.sessions.devices.destroy');
        Route::delete('admin/sessions/terminals/{terminalSqid}', [SessionController::class, 'deactivateTerminal'])
            ->name('admin.sessions.terminals.deactivate');
        Route::delete('admin/sessions/{id}', [SessionController::class, 'destroySession'])->name('admin.sessions.destroy');

        // Backup- & Restore-Status (Feature 017) — plattformweite Admin-Sicht
        Route::get('admin/backup', [BackupStatusController::class, 'status'])->name('admin.backup.status');
        Route::get('admin/backup/restore-tests/create', [BackupStatusController::class, 'createRestoreTest'])
            ->name('admin.backup.restore-tests.create');
        Route::post('admin/backup/restore-tests', [BackupStatusController::class, 'storeRestoreTest'])
            ->name('admin.backup.restore-tests.store');

        // Komponenten- und Versionsübersicht inkl. Release-SBOM (Feature 044)
        Route::get('admin/components', [ComponentsController::class, 'index'])->name('admin.components.index');
        Route::post('admin/components/sbom', [ComponentsController::class, 'generate'])
            ->middleware('throttle:6,1')
            ->name('admin.components.sbom.generate');
        Route::get('admin/components/sbom/download', [ComponentsController::class, 'download'])->name('admin.components.sbom.download');
        Route::post('admin/components/vex', [ComponentsController::class, 'vex'])
            ->middleware('throttle:6,1')
            ->name('admin.components.vex');
        // Signiertes/integritätsgesichertes Release-Manifest (Feature 022)
        Route::post('admin/components/manifest', [ComponentsController::class, 'manifest'])
            ->middleware('throttle:6,1')
            ->name('admin.components.manifest.generate');
        Route::get('admin/components/manifest/download', [ComponentsController::class, 'manifestDownload'])->name('admin.components.manifest.download');

        // Supportbericht (MVP-045)
        Route::get('admin/support/report', [SupportReportController::class, 'index'])->name('admin.support.report.index');
        Route::post('admin/support/report', [SupportReportController::class, 'generate'])
            ->middleware('throttle:3,1')
            ->name('admin.support.report.generate');
        // JSON-Variante (Feature 041): reine JSON-Datei + Browser-Vorschau ohne ZIP.
        Route::get('admin/support/report/download', [SupportReportController::class, 'download'])
            ->middleware('throttle:6,1')
            ->name('admin.support.report.download');
        Route::get('admin/support/report/preview', [SupportReportController::class, 'preview'])
            ->middleware('throttle:6,1')
            ->name('admin.support.report.preview');

        // Supportzugriffe-Audit (MVP-004)
        Route::get('admin/support/access-audit', [SupportAccessAuditController::class, 'index'])
            ->name('admin.support.access-audit.index');

        // Temporäre Supportfreigabe + Impersonation (Rang 64). Der Stop-
        // Endpunkt liegt bewusst NICHT unter einem gesperrten Namensraum,
        // damit er in der Support-Sitzung erreichbar bleibt.
        Route::get('admin/support/grants', [SupportAccessGrantController::class, 'index'])
            ->name('admin.support.grants.index');
        Route::get('admin/support/grants/create', [SupportAccessGrantController::class, 'create'])
            ->name('admin.support.grants.create');
        Route::post('admin/support/grants', [SupportAccessGrantController::class, 'store'])
            ->name('admin.support.grants.store');
        Route::post('admin/support/grants/{grant}/revoke', [SupportAccessGrantController::class, 'revoke'])
            ->name('admin.support.grants.revoke');
        Route::post('admin/support/impersonate/{user}', [SupportImpersonationController::class, 'store'])
            ->name('admin.support.impersonate.start');
        Route::post('admin/support/impersonate-stop', [SupportImpersonationController::class, 'destroy'])
            ->name('admin.support.impersonate.stop');

        // Funktionsumfang der Organisation (Feature 081, MVP-373):
        // Presets + Modul-Checkliste, Recht organization.scope.manage.
        Route::get('admin/scope', [\App\Http\Controllers\Admin\ScopeAdminController::class, 'index'])->name('admin.scope.index');
        Route::post('admin/scope', [\App\Http\Controllers\Admin\ScopeAdminController::class, 'save'])->name('admin.scope.save');

        // Arbeitsbereiche kuratieren (Feature 082, MVP-379): welche Fokus-
        // Ansichten die Org anbietet, Default + Umbenennung. Recht wie Scope.
        Route::get('admin/workspaces', [\App\Http\Controllers\Admin\WorkspaceAdminController::class, 'index'])->name('admin.workspaces.index');
        Route::post('admin/workspaces', [\App\Http\Controllers\Admin\WorkspaceAdminController::class, 'save'])->name('admin.workspaces.save');

        // Lizenz-Admin (MVP-047)
        Route::get('admin/license', [LicenseAdminController::class, 'index'])->name('admin.license.index');
        Route::post('admin/license/flags/{flag}/toggle', [LicenseAdminController::class, 'toggleFlag'])
            ->where('flag', '[A-Za-z0-9._-]+')
            ->name('admin.license.flags.toggle');
        // MVP-052: org-bezogene Modulkonfiguration (lizenzierte Module
        // aktivieren/deaktivieren). Org-Admin (platform.featureFlag.override).
        Route::post('admin/license/modules/disable', [LicenseAdminController::class, 'disableModule'])->name('admin.license.modules.disable');
        Route::post('admin/license/modules/enable', [LicenseAdminController::class, 'enableModule'])->name('admin.license.modules.enable');
        Route::post('admin/license/org', [LicenseAdminController::class, 'installOrg'])->name('admin.license.org.install');
        Route::delete('admin/license/org', [LicenseAdminController::class, 'removeOrg'])->name('admin.license.org.remove');
        Route::post('admin/license/org/issue', [LicenseAdminController::class, 'issueOrg'])->name('admin.license.org.issue');
        Route::post('admin/license/tenant-status', [LicenseAdminController::class, 'setTenantStatus'])->name('admin.license.tenantStatus');
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
        // freshDemoOrg (MVP-349): neue, isolierte Demo-Org aus Musterbranche —
        // NUR Plattform-Admin (OrganizationPolicy::create im Controller).
        Route::get('admin/demo/fresh-org', [DemoTenantController::class, 'createFreshOrg'])
            ->name('admin.demo.fresh-org.create');
        Route::post('admin/demo/fresh-org', [DemoTenantController::class, 'storeFreshOrg'])
            ->middleware('throttle:3,1')
            ->name('admin.demo.fresh-org.store');

        // Datenschutzseite (MVP-005; §3.5/§3.9 via MVP-327)
        Route::get('admin/privacy', [PrivacyController::class, 'index'])->name('admin.privacy.index');
        Route::get('admin/privacy/export', [PrivacyController::class, 'export'])->name('admin.privacy.export');
        // §3.9: Datenschutzbericht als PDF (Name/Pfad gemäß Konzept §2.1,
        // Permission privacy.report.export wird im Controller geprüft).
        Route::get('admin/privacy/report.pdf', [PrivacyController::class, 'report'])->name('admin.privacy.report');
        Route::delete('admin/privacy/sessions/{id}', [PrivacyController::class, 'destroySession'])
            ->where('id', '[A-Za-z0-9\-_]+')
            ->name('admin.privacy.sessions.destroy');
        Route::delete('admin/privacy/tokens/{id}', [PrivacyController::class, 'destroyToken'])
            ->where('id', '[A-Za-z0-9]+')
            ->name('admin.privacy.tokens.destroy');

        Route::get('diary/export.csv', [DiaryExportController::class, 'csv'])->name('diary.export.csv');
        Route::get('diary/export.pdf', [DiaryExportController::class, 'pdf'])->name('diary.export.pdf');

        Route::resource('holidays', HolidayController::class)->except('show');

        Route::resource('diary', DiaryController::class)->parameters(['diary' => 'diary']);
        Route::post('diary/{diary}/archive', [DiaryController::class, 'archive'])->name('diary.archive');
        Route::post('diary/{diary}/restore', [DiaryController::class, 'restore'])->name('diary.restore');
        Route::post('diary/{diary}/lifecycle/{action}', DiaryLifecycleController::class)
            ->whereIn('action', ['accept', 'start', 'pause', 'resume', 'complete', 'handover', 'markInvoiced', 'cancel'])
            ->name('diary.lifecycle');
        Route::get('diary/{diary}/case-file', DiaryCaseFileController::class)->name('diary.case-file');
        // Interne Fallakte als serverseitiges PDF (MVP-349): gleicher Daten-
        // umfang und gleiches Recht wie die HTML-Fallakte (inkl. interner
        // Einträge — Unterschied zum kundensichtbaren Portal-PDF).
        Route::get('diary/{diary}/case-file.pdf', [DiaryCaseFileController::class, 'pdf'])->name('diary.case-file.pdf');

        // Disposition / Einsatzplanung (Feature 028): Konfliktvorschau + Status.
        Route::get('dispatch/{diary}/conflicts', [DispatchController::class, 'conflicts'])->name('dispatch.conflicts');
        Route::post('dispatch/{diary}/transition', [DispatchController::class, 'transition'])->name('dispatch.transition');

        // Leitstelle (Feature 029): Dispatch-Board + Karten-Sicht (SLA-Risiko).
        // Route-Namen dispatch.* → Plan-Gating module.planung (config/plans.php).
        // Leerzeit-/Lückenfüller-Vorschläge (Epic 14.2, MVP-245).
        Route::get('dispatch-board/vorschlaege', [\App\Http\Controllers\DispatchSuggestionController::class, 'index'])->name('dispatch.suggestions');
        Route::post('dispatch-board/vorschlaege/{entry}/uebernehmen', [\App\Http\Controllers\DispatchSuggestionController::class, 'apply'])->name('dispatch.suggestions.apply');
        Route::post('dispatch-board/vorschlaege/{entry}/ablehnen', [\App\Http\Controllers\DispatchSuggestionController::class, 'dismiss'])->name('dispatch.suggestions.dismiss');
        Route::get('dispatch-board', [DispatchBoardController::class, 'board'])->name('dispatch.board');
        Route::get('dispatch-board/map', [DispatchBoardController::class, 'map'])->name('dispatch.map');
        // Kalender-/Tagesansicht (Rang 52) + Auftrags-Qualifikationsmatrix (Rang 53).
        Route::get('dispatch-board/calendar', [DispatchBoardController::class, 'calendar'])->name('dispatch.calendar');
        Route::get('dispatch-board/qualifications/{diary}', [DispatchBoardController::class, 'qualifications'])->name('dispatch.qualifications');
        Route::put('diary/{diary}/qualifications', [\App\Http\Controllers\DiaryQualificationController::class, 'update'])->name('diary.qualifications.update');
        Route::post('diary/{diary}/comments', [CommentController::class, 'store'])->name('diary.comments.store');
        Route::post('time-entries/{timeEntry}/comments', [TimeEntryCommentController::class, 'store'])->name('time-entries.comments.store');

        // Externe Beteiligte (Feature 033): interne Verwaltung — Einladen,
        // Link-Anzeige (einmalig), Widerruf. Subject = type+Sqid
        // (diary|protocol|document). Autorisierung über die
        // ExternalParticipantPolicy (manage-Permission + update am Subject).
        Route::get('extern/{type}/{id}/einladen', [ExternalParticipantController::class, 'create'])->name('external.create');
        Route::post('extern/{type}/{id}/einladen', [ExternalParticipantController::class, 'store'])->name('external.store');
        Route::post('extern/{participant}/widerrufen', [ExternalParticipantController::class, 'revoke'])->name('external.revoke');
        // Wiederverwendbare externe Kontaktprofile (Feature 033, Rang 30).
        Route::get('extern-kontakte', [\App\Http\Controllers\ExternalContactController::class, 'index'])->name('external-contacts.index');
        Route::get('extern-kontakte/neu', [\App\Http\Controllers\ExternalContactController::class, 'create'])->name('external-contacts.create');
        Route::post('extern-kontakte', [\App\Http\Controllers\ExternalContactController::class, 'store'])->name('external-contacts.store');
        Route::get('extern-kontakte/{externalContact}/bearbeiten', [\App\Http\Controllers\ExternalContactController::class, 'edit'])->name('external-contacts.edit');
        Route::put('extern-kontakte/{externalContact}', [\App\Http\Controllers\ExternalContactController::class, 'update'])->name('external-contacts.update');
        Route::delete('extern-kontakte/{externalContact}', [\App\Http\Controllers\ExternalContactController::class, 'destroy'])->name('external-contacts.destroy');
        Route::put('comments/{comment}', [CommentController::class, 'update'])->name('comments.update');
        Route::delete('comments/{comment}', [CommentController::class, 'destroy'])->name('comments.destroy');

        Route::post('attachments/{type}/{id}', [AttachmentController::class, 'store'])
            ->whereIn('type', array_keys(AttachmentController::TYPE_MAP))
            ->name('attachments.store');
        Route::get('attachments/{attachment}/download', [AttachmentController::class, 'download'])->name('attachments.download');
        // Kundenfreigabe je Anhang fürs Portal (Rang 54, Toggle).
        Route::patch('attachments/{attachment}/customer-visibility', [AttachmentController::class, 'toggleCustomerVisibility'])->name('attachments.customer-visibility');
        Route::delete('attachments/{attachment}', [AttachmentController::class, 'destroy'])->name('attachments.destroy');
        Route::delete('attachments/{type}/{id}/meta/{meta}', [AttachmentController::class, 'destroyMeta'])
            ->whereIn('type', ['organization', 'user'])
            ->whereIn('meta', ['logo', 'logo_dark', 'avatar'])
            ->name('attachments.destroyMeta');

        Route::get('week', WeekController::class)->name('week.index');
        Route::get('calendar', [\App\Http\Controllers\CalendarController::class, 'index'])->name('calendar.index');
        Route::get('calendar/events', [\App\Http\Controllers\CalendarController::class, 'events'])->name('calendar.events');

        // Agiles Projektmanagement (Feature 064) — eigenes Präfix agile.*
        // (module.agile_projects; projects.* ist auf module.vertrieb gemappt).
        // Org-weite Management-Übersicht (P10) — Projektfilter via Policy.
        Route::get('agile/berichte', [\App\Http\Controllers\Reporting\AgileManagementReportController::class, 'index'])->name('agile.reports.overview');
        Route::prefix('agile/projects/{project}')->name('agile.')->group(function (): void {
            Route::get('board', [\App\Http\Controllers\Agile\AgileBoardController::class, 'board'])->name('board');
            Route::post('aktivieren', [\App\Http\Controllers\Agile\AgileBoardController::class, 'activate'])->name('activate');
            Route::patch('einstellungen', [\App\Http\Controllers\Agile\AgileBoardController::class, 'updateSettings'])->name('settings.update');
            // Produkt-Backlog (P2/MVP-140).
            Route::get('backlog', [\App\Http\Controllers\Agile\AgileBacklogController::class, 'index'])->name('backlog');
            Route::post('backlog', [\App\Http\Controllers\Agile\AgileBacklogController::class, 'store'])->name('items.store');
            Route::post('backlog/uebernehmen', [\App\Http\Controllers\Agile\AgileBacklogController::class, 'adopt'])->name('items.adopt');
            Route::patch('backlog/{item}/rang', [\App\Http\Controllers\Agile\AgileBacklogController::class, 'rerank'])->name('items.rerank');
            Route::patch('backlog/{item}', [\App\Http\Controllers\Agile\AgileBacklogController::class, 'updateItem'])->name('items.update');
            // Epic-Zuordnung über task.parent_task_id (Vollaudit 2026-07, M25).
            Route::patch('backlog/{item}/epic', [\App\Http\Controllers\Agile\AgileBacklogController::class, 'assignEpic'])->name('items.epic');
            Route::post('backlog/{item}/kriterien', [\App\Http\Controllers\Agile\AgileBacklogController::class, 'storeCriterion'])->name('criteria.store');
            Route::patch('backlog/{item}/kriterien/{criterion}', [\App\Http\Controllers\Agile\AgileBacklogController::class, 'toggleCriterion'])->name('criteria.toggle');
            Route::delete('backlog/{item}/kriterien/{criterion}', [\App\Http\Controllers\Agile\AgileBacklogController::class, 'destroyCriterion'])->name('criteria.destroy');
            // Board-Flussregeln (P3/MVP-141).
            Route::patch('items/{item}/spalte', [\App\Http\Controllers\Agile\AgileBoardController::class, 'moveItem'])->name('items.move');
            Route::post('items/{item}/blockieren', [\App\Http\Controllers\Agile\AgileBoardController::class, 'blockItem'])->name('items.block');
            Route::post('items/{item}/entblocken', [\App\Http\Controllers\Agile\AgileBoardController::class, 'unblockItem'])->name('items.unblock');
            // Berichtszentrum (P8/MVP-146).
            Route::get('berichte/sprint', [\App\Http\Controllers\Reporting\AgileSprintReportController::class, 'index'])->name('reports.sprint');
            Route::get('berichte/fluss', [\App\Http\Controllers\Reporting\AgileFlowReportController::class, 'index'])->name('reports.flow');
            // Drilldown/Exporte (P11/MVP-149) — Drilldown nur signiert.
            Route::get('berichte/drilldown', [\App\Http\Controllers\Reporting\AgileReportExportController::class, 'drilldown'])->name('reports.drilldown');
            Route::get('berichte/export/{metric}.csv', [\App\Http\Controllers\Reporting\AgileReportExportController::class, 'csv'])->name('reports.export.csv');
            Route::get('berichte/export/cockpit.pdf', [\App\Http\Controllers\Reporting\AgileReportExportController::class, 'pdf'])->name('reports.export.pdf');
            // Sprints (P4/MVP-142).
            Route::get('sprints', [\App\Http\Controllers\Agile\AgileSprintController::class, 'index'])->name('sprints');
            Route::post('sprints', [\App\Http\Controllers\Agile\AgileSprintController::class, 'store'])->name('sprints.store');
            Route::post('sprints/{sprint}/items', [\App\Http\Controllers\Agile\AgileSprintController::class, 'assignItem'])->name('sprints.items.assign');
            Route::delete('sprints/{sprint}/items/{item}', [\App\Http\Controllers\Agile\AgileSprintController::class, 'removeItem'])->name('sprints.items.remove');
            Route::post('sprints/{sprint}/start', [\App\Http\Controllers\Agile\AgileSprintController::class, 'start'])->name('sprints.start');
            Route::post('sprints/{sprint}/abschliessen', [\App\Http\Controllers\Agile\AgileSprintController::class, 'complete'])->name('sprints.complete');
            Route::post('sprints/{sprint}/abbrechen', [\App\Http\Controllers\Agile\AgileSprintController::class, 'cancel'])->name('sprints.cancel');
            // Spalten-Verwaltung (P3).
            Route::post('spalten', [\App\Http\Controllers\Agile\AgileBoardController::class, 'saveColumn'])->name('columns.store');
            Route::patch('spalten/{column}', [\App\Http\Controllers\Agile\AgileBoardController::class, 'saveColumn'])->name('columns.update');
            Route::delete('spalten/{column}', [\App\Http\Controllers\Agile\AgileBoardController::class, 'destroyColumn'])->name('columns.destroy');
        });

        Route::get('kanban', [KanbanController::class, 'index'])->name('kanban.index');

        Route::get('duties', [DutyController::class, 'index'])->name('duties.index');

        Route::resource('duty-plans', DutyPlanController::class)->parameters(['duty-plans' => 'dutyPlan']);
        Route::patch('duty-plans/{dutyPlan}/publish', [DutyPlanController::class, 'publish'])->name('duty-plans.publish');
        Route::patch('duty-plans/{dutyPlan}/retract', [DutyPlanController::class, 'retract'])->name('duty-plans.retract');
        // Genehmigungsworkflow (MVP-525): beantragen + genehmigen.
        Route::patch('duty-plans/{dutyPlan}/submit', [DutyPlanController::class, 'submit'])->name('duty-plans.submit');
        Route::patch('duty-plans/{dutyPlan}/approve', [DutyPlanController::class, 'approve'])->name('duty-plans.approve');

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
            Route::get('schedule', [PrintController::class, 'schedule'])->name('schedule');
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

        // Urlaubskonto (MVP-413): Jahresansprüche, nur mit vacation.entitlements.manage
        Route::resource('vacation-entitlements', VacationEntitlementController::class)->except(['show'])
            ->parameters(['vacation-entitlements' => 'vacation_entitlement']);
        Route::post('vacation-entitlements-bulk', [VacationEntitlementController::class, 'bulk'])->name('vacation-entitlements.bulk');

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

        // ── Raumbezogene Anforderungen (Feature 027) ────────────────────────
        Route::post('rooms/{room}/requirements', [\App\Http\Controllers\RoomRequirementController::class, 'store'])
            ->name('rooms.requirements.store');
        Route::put('rooms/{room}/requirements/{requirement}', [\App\Http\Controllers\RoomRequirementController::class, 'update'])
            ->name('rooms.requirements.update');
        Route::delete('rooms/{room}/requirements/{requirement}', [\App\Http\Controllers\RoomRequirementController::class, 'destroy'])
            ->name('rooms.requirements.destroy');

        // ── Liegenschaften (Standort → Gebäude → Geschoss) ──────────────────
        Route::resource('sites', \App\Http\Controllers\SiteController::class);
        Route::resource('buildings', \App\Http\Controllers\BuildingController::class);
        Route::resource('floors', \App\Http\Controllers\FloorController::class);

        // ── Standortbasierte Zeiterfassung (gegated: module.standorterfassung) ──
        Route::resource('geofences', \App\Http\Controllers\Location\GeofenceController::class)
            ->except('show');
        Route::get('location/review', [\App\Http\Controllers\Location\LocationReviewController::class, 'index'])->name('location.review.index');
        Route::post('location/review/{entry}/confirm', [\App\Http\Controllers\Location\LocationReviewController::class, 'confirm'])->name('location.review.confirm');
        Route::post('location/review/{entry}/dismiss', [\App\Http\Controllers\Location\LocationReviewController::class, 'dismiss'])->name('location.review.dismiss');
        Route::get('location/devices', [\App\Http\Controllers\Location\LocationDeviceController::class, 'index'])->name('location.devices.index');
        Route::get('location/devices/create', [\App\Http\Controllers\Location\LocationDeviceController::class, 'create'])->name('location.devices.create');
        Route::post('location/devices', [\App\Http\Controllers\Location\LocationDeviceController::class, 'store'])->name('location.devices.store');
        Route::post('location/consent', [\App\Http\Controllers\Location\LocationDeviceController::class, 'consent'])->name('location.devices.consent');
        Route::get('location/import/google', [\App\Http\Controllers\Location\LocationDeviceController::class, 'importGoogleForm'])->name('location.devices.import-google.form');
        Route::post('location/import/google', [\App\Http\Controllers\Location\LocationDeviceController::class, 'importGoogle'])->name('location.devices.import-google');
        Route::delete('location/devices/{device}', [\App\Http\Controllers\Location\LocationDeviceController::class, 'destroy'])->name('location.devices.destroy');

        Route::get('calendar/events.ics', [IcsFeedController::class, 'personal'])->name('events.ics.personal');

        // ── Kunden (Kimai-style customers) ──────────────────────────────────────
        Route::get('customers/export', [CustomerController::class, 'export'])->name('customers.export');
        Route::get('customers/import', [CustomerController::class, 'importForm'])->name('customers.import.form');
        Route::post('customers/import', [CustomerController::class, 'import'])->name('customers.import');
        // Kunden-Abgleich (Dubletten-Bereinigung) — VOR der Resource-Route, damit
        // "customers/duplicates" nicht als customers/{customer} interpretiert wird.
        Route::get('customers/duplicates', [CustomerMergeController::class, 'index'])->name('customers.duplicates.index');
        Route::get('customers/duplicates/compare', [CustomerMergeController::class, 'compare'])->name('customers.duplicates.compare');
        Route::post('customers/duplicates/merge', [CustomerMergeController::class, 'merge'])->name('customers.duplicates.merge');
        Route::post('customers/duplicates/bulk-merge', [CustomerMergeController::class, 'bulkMerge'])->name('customers.duplicates.bulk-merge');
        Route::post('customers/duplicates/dismiss', [CustomerMergeController::class, 'dismiss'])->name('customers.duplicates.dismiss');
        Route::resource('customers', CustomerController::class);
        Route::post('customers/{customer}/archive', [CustomerController::class, 'archive'])->name('customers.archive');
        Route::post('customers/{customer}/restore', [CustomerController::class, 'restore'])->name('customers.restore');
        // Kundenportal-Zugänge (MVP-510): Verwaltung an der Kundenakte.
        Route::get('customers/{customer}/portal-access/create', [\App\Http\Controllers\CustomerPortalAccessController::class, 'createDialog'])->name('customers.portal-access.create');
        Route::post('customers/{customer}/portal-access', [\App\Http\Controllers\CustomerPortalAccessController::class, 'store'])->name('customers.portal-access.store');
        Route::post('customers/{customer}/portal-access/{portalUser}/resend', [\App\Http\Controllers\CustomerPortalAccessController::class, 'resend'])->name('customers.portal-access.resend');
        Route::post('customers/{customer}/portal-access/{portalUser}/deactivate', [\App\Http\Controllers\CustomerPortalAccessController::class, 'deactivate'])->name('customers.portal-access.deactivate');
        Route::post('customers/{customer}/portal-access/{portalUser}/reactivate', [\App\Http\Controllers\CustomerPortalAccessController::class, 'reactivate'])->name('customers.portal-access.reactivate');
        // Portal-Sichtbarkeiten je Kunde (MVP-511).
        Route::put('customers/{customer}/portal-visibility', [\App\Http\Controllers\CustomerPortalVisibilityController::class, 'update'])->name('customers.portal-visibility.update');

        // ── Kunden-Sonderkonditionen & Abrechnungskonto (Feature 098) ───────────
        // Kunden-Sonderdesign (MVP-651, vormals invoice_template-Zuordnung).
        Route::post('customers/{customer}/design-profile', \App\Http\Controllers\Customers\CustomerDesignProfileController::class)->name('customers.design-profile');
        Route::get('customers/{customer}/billing/agreement/edit', [\App\Http\Controllers\Customers\BillingAgreementController::class, 'edit'])->name('customers.billing.agreement.edit');
        Route::get('customers/{customer}/billing/payments/create', [\App\Http\Controllers\Customers\AccountPaymentController::class, 'create'])->name('customers.billing.payments.create');
        Route::post('customers/{customer}/billing/agreement', [\App\Http\Controllers\Customers\BillingAgreementController::class, 'save'])->name('customers.billing.agreement.save');
        Route::post('customers/{customer}/billing/recalculate', [\App\Http\Controllers\Customers\BillingStatementController::class, 'recalculate'])->name('customers.billing.recalculate');
        Route::get('customers/{customer}/billing/statements/{statement}', [\App\Http\Controllers\Customers\BillingStatementController::class, 'show'])->name('customers.billing.statements.show');
        Route::post('customers/{customer}/billing/statements/{statement}/close', [\App\Http\Controllers\Customers\BillingStatementController::class, 'close'])->name('customers.billing.statements.close');
        Route::post('customers/{customer}/billing/statements/{statement}/reopen', [\App\Http\Controllers\Customers\BillingStatementController::class, 'reopen'])->name('customers.billing.statements.reopen');
        Route::post('customers/{customer}/billing/payments', [\App\Http\Controllers\Customers\AccountPaymentController::class, 'store'])->name('customers.billing.payments.store');
        Route::delete('customers/{customer}/billing/payments/{payment}', [\App\Http\Controllers\Customers\AccountPaymentController::class, 'destroy'])->name('customers.billing.payments.destroy');
        // Retainer-Modus (Feature 098): Pauschale an Lexoffice senden + Spitzabrechnung.
        Route::post('customers/{customer}/billing/retainer/push', [\App\Http\Controllers\Customers\RetainerBillingController::class, 'pushMonth'])->name('customers.billing.retainer.push');
        Route::post('customers/{customer}/billing/retainer/trueup', [\App\Http\Controllers\Customers\RetainerBillingController::class, 'trueUp'])->name('customers.billing.retainer.trueup');
        // Bereits in Lexoffice geführte Pauschalrechnung von Hand an einen Monat hängen.
        Route::get('customers/{customer}/billing/statements/{statement}/voucher/edit', [\App\Http\Controllers\Customers\RetainerBillingController::class, 'editVoucher'])->name('customers.billing.retainer.voucher.edit');
        Route::post('customers/{customer}/billing/statements/{statement}/voucher', [\App\Http\Controllers\Customers\RetainerBillingController::class, 'linkVoucher'])->name('customers.billing.retainer.voucher.link');
        Route::delete('customers/{customer}/billing/statements/{statement}/voucher', [\App\Http\Controllers\Customers\RetainerBillingController::class, 'unlinkVoucher'])->name('customers.billing.retainer.voucher.unlink');

        // ── Materialkosten-Zuordnung & Gewinn (Umsatz − Material) ────────────────
        Route::get('customers/{customer}/material-costs/create', [\App\Http\Controllers\Customers\MaterialCostAllocationController::class, 'create'])->name('customers.material-costs.create');
        Route::post('customers/{customer}/material-costs', [\App\Http\Controllers\Customers\MaterialCostAllocationController::class, 'store'])->name('customers.material-costs.store');
        Route::get('customers/{customer}/material-costs/stock/create', [\App\Http\Controllers\Customers\MaterialCostAllocationController::class, 'createStock'])->name('customers.material-costs.stock.create');
        Route::post('customers/{customer}/material-costs/stock', [\App\Http\Controllers\Customers\MaterialCostAllocationController::class, 'storeStock'])->name('customers.material-costs.stock.store');
        Route::delete('customers/{customer}/material-costs/{allocation}', [\App\Http\Controllers\Customers\MaterialCostAllocationController::class, 'destroy'])->name('customers.material-costs.destroy');

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

        // ── Produktstamm (Typ-Ebene Hersteller-Modell, MVP-370) — Kern-
        // Stammdaten (Artikel UND Assets verweisen), bewusst OHNE Modul-Gate.
        Route::resource('products', ProductController::class)->except('show');

        // ── Artikelstamm (Feature 048, MVP-060) ─ Modul-Gate articles.* → module.lager
        Route::get('articles/export/datanorm', [\App\Http\Controllers\ArticleExportController::class, 'datanorm'])->name('articles.export.datanorm'); // Feature 107 W5 — vor der Resource (Kollision mit articles/{article})
        // Feature 107 W9: Verkaufs-Rabattgruppen (ebenfalls vor der Resource).
        Route::get('articles/sales-discount-groups', [\App\Http\Controllers\SalesDiscountGroupController::class, 'index'])->name('articles.sales-discount-groups.index');
        Route::post('articles/sales-discount-groups', [\App\Http\Controllers\SalesDiscountGroupController::class, 'store'])->name('articles.sales-discount-groups.store');
        Route::delete('articles/sales-discount-groups/{salesDiscountGroup}', [\App\Http\Controllers\SalesDiscountGroupController::class, 'destroy'])->name('articles.sales-discount-groups.destroy');
        // Feature 107 MVP-567: Kunden-Overrides je Verkaufs-Rabattgruppe.
        Route::post('articles/sales-discount-groups/overrides', [\App\Http\Controllers\SalesDiscountGroupController::class, 'storeOverride'])->name('articles.sales-discount-groups.overrides.store');
        Route::delete('articles/sales-discount-groups/overrides/{override}', [\App\Http\Controllers\SalesDiscountGroupController::class, 'destroyOverride'])->name('articles.sales-discount-groups.overrides.destroy');
        Route::resource('articles', \App\Http\Controllers\ArticleController::class);
        Route::post('articles/{article}/retire', [\App\Http\Controllers\ArticleController::class, 'retire'])->name('articles.retire');
        Route::post('articles/{article}/supplies/{supply}/prefer', [\App\Http\Controllers\ArticleController::class, 'setPreferredSupply'])->name('articles.supplies.prefer'); // Feature 050 Lieferantenvergleich
        Route::post('articles/{article}/tiers', [\App\Http\Controllers\ArticleController::class, 'storeTier'])->name('articles.tiers.store'); // Feature 107, MVP-605
        Route::delete('articles/{article}/tiers/{tier}', [\App\Http\Controllers\ArticleController::class, 'destroyTier'])->name('articles.tiers.destroy');
        Route::post('articles/{article}/options', [\App\Http\Controllers\ArticleController::class, 'storeOption'])->name('articles.options.store');
        Route::post('articles/{article}/options/{option}/values', [\App\Http\Controllers\ArticleController::class, 'storeOptionValue'])->name('articles.options.values.store');
        Route::post('articles/{article}/units', [\App\Http\Controllers\ArticleController::class, 'storeUnit'])->name('articles.units.store');
        Route::post('articles/{article}/variants', [\App\Http\Controllers\ArticleController::class, 'storeVariant'])->name('articles.variants.store');
        Route::post('articles/{article}/variants/{variant}/retire', [\App\Http\Controllers\ArticleController::class, 'retireVariant'])->name('articles.variants.retire');

        // ── Lagerwirtschaft (Feature 048, MVP-067) ─ Gate warehouses.*/inventory.* → module.lager
        Route::resource('warehouses', \App\Http\Controllers\WarehouseController::class)->except(['show']);
        Route::get('inventory/stock', [\App\Http\Controllers\StockController::class, 'index'])->name('inventory.stock');
        Route::post('inventory/movements', [\App\Http\Controllers\StockController::class, 'storeMovement'])->name('inventory.movements.store');
        Route::get('inventory/counts', [\App\Http\Controllers\StocktakeController::class, 'index'])->name('inventory.counts.index');
        Route::post('inventory/counts', [\App\Http\Controllers\StocktakeController::class, 'open'])->name('inventory.counts.open');
        Route::post('inventory/counts/cycle', [\App\Http\Controllers\StocktakeController::class, 'openCycle'])->name('inventory.counts.cycle'); // E6 zyklisch
        Route::get('inventory/counts/{count}', [\App\Http\Controllers\StocktakeController::class, 'show'])->name('inventory.counts.show');
        Route::post('inventory/counts/{count}/record', [\App\Http\Controllers\StocktakeController::class, 'record'])->name('inventory.counts.record');
        Route::post('inventory/counts/{count}/scan', [\App\Http\Controllers\StocktakeController::class, 'recordScan'])->name('inventory.counts.scan'); // E6 Scan-Erfassung
        Route::post('inventory/counts/{count}/apply', [\App\Http\Controllers\StocktakeController::class, 'apply'])->name('inventory.counts.apply');

        // ── Scan-/Buchungs-UI (Feature 048, E5)
        Route::get('inventory/scan', [\App\Http\Controllers\ScanController::class, 'index'])->name('inventory.scan');
        Route::post('inventory/scan', [\App\Http\Controllers\ScanController::class, 'book'])->name('inventory.scan.book');

        // ── Chargenverwaltung / Los-Split-Merge (Feature 048, E2/E7)
        Route::get('inventory/lots', [\App\Http\Controllers\LotController::class, 'index'])->name('inventory.lots');
        Route::post('inventory/lots/split', [\App\Http\Controllers\LotController::class, 'splitLot'])->name('inventory.lots.split');
        Route::post('inventory/lots/merge', [\App\Http\Controllers\LotController::class, 'mergeLot'])->name('inventory.lots.merge');

        // ── Etikettendruck (Feature 048, E5)
        Route::get('inventory/labels/variant/{variant}', [\App\Http\Controllers\LabelController::class, 'variant'])->name('inventory.labels.variant');
        Route::get('inventory/labels/serial/{stockSerial}', [\App\Http\Controllers\LabelController::class, 'serial'])->name('inventory.labels.serial');
        Route::get('inventory/labels/lot/{stockLot}', [\App\Http\Controllers\LabelController::class, 'lot'])->name('inventory.labels.lot');

        // Konflikt-Inbox externer Bestandsspiegelung (Feature 048, MVP-072)
        Route::get('inventory/conflicts', [\App\Http\Controllers\InventoryConflictController::class, 'index'])->name('inventory.conflicts.index');
        Route::post('inventory/conflicts/{conflict}/keep-local', [\App\Http\Controllers\InventoryConflictController::class, 'keepLocal'])->name('inventory.conflicts.keep-local');
        Route::post('inventory/conflicts/{conflict}/compensate', [\App\Http\Controllers\InventoryConflictController::class, 'compensate'])->name('inventory.conflicts.compensate');

        // Etiketten-Layout-Designer (Feature 048, E5)
        Route::get('inventory/label-templates', [\App\Http\Controllers\LabelTemplateController::class, 'index'])->name('inventory.label-templates.index');
        Route::get('inventory/label-templates/create', [\App\Http\Controllers\LabelTemplateController::class, 'create'])->name('inventory.label-templates.create');
        Route::post('inventory/label-templates', [\App\Http\Controllers\LabelTemplateController::class, 'store'])->name('inventory.label-templates.store');
        Route::get('inventory/label-templates/{labelTemplate}/edit', [\App\Http\Controllers\LabelTemplateController::class, 'edit'])->name('inventory.label-templates.edit');
        Route::put('inventory/label-templates/{labelTemplate}', [\App\Http\Controllers\LabelTemplateController::class, 'update'])->name('inventory.label-templates.update');
        Route::delete('inventory/label-templates/{labelTemplate}', [\App\Http\Controllers\LabelTemplateController::class, 'destroy'])->name('inventory.label-templates.destroy');
        Route::post('inventory/reservations/{reservation}/release', [\App\Http\Controllers\StockController::class, 'releaseReservation'])->name('inventory.reservations.release');
        Route::post('inventory/levels', [\App\Http\Controllers\StockController::class, 'setLevels'])->name('inventory.levels.set');

        // ── Fertigungsaufträge (Feature 047) ─ Gate manufacturing-orders.* → module.lager
        Route::get('manufacturing-orders', [\App\Http\Controllers\ManufacturingOrderController::class, 'index'])->name('manufacturing-orders.index');
        Route::get('manufacturing-orders/create', [\App\Http\Controllers\ManufacturingOrderController::class, 'create'])->name('manufacturing-orders.create');
        Route::post('manufacturing-orders', [\App\Http\Controllers\ManufacturingOrderController::class, 'store'])->name('manufacturing-orders.store');
        Route::get('manufacturing-orders/{order}', [\App\Http\Controllers\ManufacturingOrderController::class, 'show'])->name('manufacturing-orders.show');
        Route::post('manufacturing-orders/{order}/release', [\App\Http\Controllers\ManufacturingOrderController::class, 'release'])->name('manufacturing-orders.release');
        Route::post('manufacturing-orders/{order}/start', [\App\Http\Controllers\ManufacturingOrderController::class, 'start'])->name('manufacturing-orders.start');
        Route::post('manufacturing-orders/{order}/reserve', [\App\Http\Controllers\ManufacturingOrderController::class, 'reserve'])->name('manufacturing-orders.reserve');
        Route::post('manufacturing-orders/{order}/report', [\App\Http\Controllers\ManufacturingOrderController::class, 'report'])->name('manufacturing-orders.report');
        Route::post('manufacturing-orders/{order}/materials/{material}/consume', [\App\Http\Controllers\ManufacturingOrderController::class, 'consumeMaterial'])->name('manufacturing-orders.materials.consume'); // MVP-065 Ist-Verbrauch
        // Vollaudit 2026-07 (M20): Fehlmaterial-/Ersatzmaterialprozess (MVP-068).
        Route::post('manufacturing-orders/{order}/substitutes', [\App\Http\Controllers\ManufacturingOrderController::class, 'requestSubstitute'])->name('manufacturing-orders.substitutes.request');
        Route::post('manufacturing-orders/{order}/substitutes/{substitute}/decide', [\App\Http\Controllers\ManufacturingOrderController::class, 'decideSubstitute'])->name('manufacturing-orders.substitutes.decide');
        Route::get('manufacturing-orders/{order}/record.pdf', [\App\Http\Controllers\ManufacturingOrderController::class, 'recordPdf'])->name('manufacturing-orders.record.pdf'); // MVP-065 Fertigungsnachweis
        Route::post('manufacturing-orders/{order}/deliver', [\App\Http\Controllers\ManufacturingOrderController::class, 'deliver'])->name('manufacturing-orders.deliver');
        Route::post('manufacturing-orders/{order}/deliveries/{delivery}/lexoffice', [\App\Http\Controllers\ManufacturingOrderController::class, 'pushDeliveryNote'])->name('manufacturing-orders.deliveries.lexoffice'); // E4/045 Lieferschein an Lexoffice
        Route::get('manufacturing-orders/{order}/deliveries/{delivery}/delivery-note.pdf', [\App\Http\Controllers\ManufacturingOrderController::class, 'deliveryNotePdf'])->name('manufacturing-orders.deliveries.pdf'); // MVP-074 Lieferschein-PDF
        Route::post('manufacturing-orders/{order}/deliveries/{delivery}/shipment', [\App\Http\Controllers\ManufacturingOrderController::class, 'createShipment'])->name('manufacturing-orders.deliveries.shipment'); // 059/MVP-128 Rang 20 Versandauftrag
        Route::post('manufacturing-orders/{order}/order-confirmation/lexoffice', [\App\Http\Controllers\ManufacturingOrderController::class, 'pushOrderConfirmation'])->name('manufacturing-orders.order-confirmation.lexoffice'); // 045 Auftragsbestätigung an Lexoffice
        Route::post('manufacturing-orders/{order}/quotation/lexoffice', [\App\Http\Controllers\ManufacturingOrderController::class, 'pushQuotation'])->name('manufacturing-orders.quotation.lexoffice'); // 045 Angebot an Lexoffice
        Route::post('manufacturing-orders/{order}/cancel', [\App\Http\Controllers\ManufacturingOrderController::class, 'cancel'])->name('manufacturing-orders.cancel');
        Route::post('manufacturing-orders/{order}/subcontract', [\App\Http\Controllers\ManufacturingOrderController::class, 'subcontract'])->name('manufacturing-orders.subcontract'); // E7 Fremdfertigung

        Route::post('manufacturing-orders/{order}/work-center', [\App\Http\Controllers\ManufacturingOrderController::class, 'assignWorkCenter'])->name('manufacturing-orders.work-center'); // E7 Kapazität

        // Fertigungsplanung MRP/SPC (Feature 047/048, E7) → module.lager
        Route::get('manufacturing-planning', [\App\Http\Controllers\ManufacturingPlanningController::class, 'index'])->name('manufacturing-planning.index');

        // Kapazitätsboard / Arbeitsplätze (Feature 047/048, E7) → module.lager
        Route::get('work-centers', [\App\Http\Controllers\WorkCenterController::class, 'index'])->name('work-centers.index');
        Route::get('work-centers/create', [\App\Http\Controllers\WorkCenterController::class, 'create'])->name('work-centers.create');
        Route::post('work-centers', [\App\Http\Controllers\WorkCenterController::class, 'store'])->name('work-centers.store');

        // ── Seriennummern (Feature 047/048, E2) ─ Gate serials.* → module.lager
        Route::get('serials', [\App\Http\Controllers\SerialController::class, 'index'])->name('serials.index');
        Route::get('serials/verify', [\App\Http\Controllers\SerialController::class, 'verify'])->name('serials.verify');
        Route::get('serials/{serial}', [\App\Http\Controllers\SerialController::class, 'show'])->name('serials.show');
        Route::post('serials/{serial}/block', [\App\Http\Controllers\SerialController::class, 'block'])->name('serials.block');
        Route::post('serials/{serial}/unblock', [\App\Http\Controllers\SerialController::class, 'unblock'])->name('serials.unblock');
        Route::post('serials/{serial}/scrap', [\App\Http\Controllers\SerialController::class, 'scrap'])->name('serials.scrap');

        // ── Beschaffung / Bestellungen (Feature 048, E4) ─ Gate purchase-orders.* → module.lager
        Route::get('purchase-orders', [\App\Http\Controllers\PurchaseOrderController::class, 'index'])->name('purchase-orders.index');
        Route::get('purchase-orders/suggestions', [\App\Http\Controllers\PurchaseOrderController::class, 'suggestions'])->name('purchase-orders.suggestions');
        Route::get('purchase-orders/incoming', [\App\Http\Controllers\PurchaseOrderController::class, 'incoming'])->name('purchase-orders.incoming'); // E4 erwartete Wareneingänge
        Route::post('purchase-orders/suggestions/apply', [\App\Http\Controllers\PurchaseOrderController::class, 'applySuggestions'])->name('purchase-orders.suggestions.apply');
        Route::get('purchase-orders/create', [\App\Http\Controllers\PurchaseOrderController::class, 'create'])->name('purchase-orders.create');
        Route::post('purchase-orders', [\App\Http\Controllers\PurchaseOrderController::class, 'store'])->name('purchase-orders.store');
        Route::get('purchase-orders/{purchaseOrder}', [\App\Http\Controllers\PurchaseOrderController::class, 'show'])->name('purchase-orders.show');
        Route::get('purchase-orders/{purchaseOrder}/order-xml', [\App\Http\Controllers\PurchaseOrderController::class, 'downloadOrder'])->name('purchase-orders.order-xml'); // E4 XBestellung/Order-X Export
        Route::get('purchase-orders/{purchaseOrder}/order.pdf', [\App\Http\Controllers\PurchaseOrderController::class, 'downloadPdf'])->name('purchase-orders.pdf'); // E4 Bestellung-PDF
        Route::post('purchase-orders/{purchaseOrder}/lines', [\App\Http\Controllers\PurchaseOrderController::class, 'addLine'])->name('purchase-orders.lines.add');
        Route::post('purchase-orders/{purchaseOrder}/conditions', [\App\Http\Controllers\PurchaseOrderController::class, 'updateConditions'])->name('purchase-orders.conditions'); // Frachtkosten (UGL POZ)
        Route::post('purchase-orders/{purchaseOrder}/submit', [\App\Http\Controllers\PurchaseOrderController::class, 'submit'])->name('purchase-orders.submit');
        Route::post('purchase-orders/{purchaseOrder}/receive', [\App\Http\Controllers\PurchaseOrderController::class, 'receive'])->name('purchase-orders.receive');
        Route::post('purchase-orders/{purchaseOrder}/advices', [\App\Http\Controllers\PurchaseOrderController::class, 'announceAdvice'])->name('purchase-orders.advices.announce'); // E4 Lieferavis
        Route::post('purchase-orders/{purchaseOrder}/advices/import', [\App\Http\Controllers\PurchaseOrderController::class, 'importAdvice'])->name('purchase-orders.advices.import'); // E4 Lieferschein-Import (Despatch Advice)
        Route::post('purchase-orders/{purchaseOrder}/reconcile-invoice', [\App\Http\Controllers\PurchaseOrderController::class, 'reconcileInvoice'])->name('purchase-orders.reconcile-invoice'); // UGL-Rechnungsabgleich
        Route::post('purchase-orders/advices/{advice}/receive', [\App\Http\Controllers\PurchaseOrderController::class, 'receiveAdvice'])->name('purchase-orders.advices.receive');
        Route::post('purchase-orders/advices/{advice}/cancel', [\App\Http\Controllers\PurchaseOrderController::class, 'cancelAdvice'])->name('purchase-orders.advices.cancel');
        Route::post('purchase-orders/{purchaseOrder}/cancel', [\App\Http\Controllers\PurchaseOrderController::class, 'cancel'])->name('purchase-orders.cancel');

        // ── Lieferantenperformance-Scorecards (Bauturbo Welle D) ─ Gate supplier-scorecards.* → module.lager
        // Termintreue/Reklamationsquote/Preisentwicklung/ISMS-Qualität je Lieferant,
        // aggregiert aus Einkauf/Lager/Claims/ISMS; signierte Beleg-Drilldowns.
        Route::get('supplier-scorecards', [\App\Http\Controllers\Reporting\SupplierScorecardController::class, 'index'])->name('supplier-scorecards.index');
        Route::get('supplier-scorecards/{supplier}/drilldown', [\App\Http\Controllers\Reporting\SupplierScorecardController::class, 'drilldown'])->name('supplier-scorecards.drilldown');
        Route::get('supplier-scorecards/{supplier}', [\App\Http\Controllers\Reporting\SupplierScorecardController::class, 'show'])->name('supplier-scorecards.show');

        // ── Lieferantenkataloge (Feature 050, MVP-091/092) ─ Gate supplier-catalogs.* → module.lager
        // Feature 107 MVP-564: Metallnotierungen (vor den {supplierCatalog}-Routen).
        Route::get('supplier-catalogs/metal-quotations', [\App\Http\Controllers\MetalQuotationController::class, 'index'])->name('supplier-catalogs.metal-quotations.index');
        Route::post('supplier-catalogs/metal-quotations', [\App\Http\Controllers\MetalQuotationController::class, 'store'])->name('supplier-catalogs.metal-quotations.store');
        Route::delete('supplier-catalogs/metal-quotations/{metalQuotation}', [\App\Http\Controllers\MetalQuotationController::class, 'destroy'])->name('supplier-catalogs.metal-quotations.destroy');
        Route::get('supplier-catalogs', [\App\Http\Controllers\SupplierCatalogController::class, 'index'])->name('supplier-catalogs.index');
        Route::get('supplier-catalogs/create', [\App\Http\Controllers\SupplierCatalogController::class, 'create'])->name('supplier-catalogs.create');
        Route::get('supplier-catalogs/alerts', [\App\Http\Controllers\SupplierCatalogController::class, 'alerts'])->name('supplier-catalogs.alerts'); // MVP-094 (vor show!)
        Route::post('supplier-catalogs/alerts/{alert}/acknowledge', [\App\Http\Controllers\SupplierCatalogController::class, 'acknowledgeAlert'])->name('supplier-catalogs.alerts.acknowledge');
        Route::post('supplier-catalogs', [\App\Http\Controllers\SupplierCatalogController::class, 'store'])->name('supplier-catalogs.store');
        Route::get('supplier-catalogs/{supplierCatalog}/edit', [\App\Http\Controllers\SupplierCatalogController::class, 'edit'])->name('supplier-catalogs.edit');
        Route::put('supplier-catalogs/{supplierCatalog}', [\App\Http\Controllers\SupplierCatalogController::class, 'update'])->name('supplier-catalogs.update');
        Route::delete('supplier-catalogs/{supplierCatalog}', [\App\Http\Controllers\SupplierCatalogController::class, 'destroy'])->name('supplier-catalogs.destroy');
        Route::post('supplier-catalogs/{supplierCatalog}/toggle', [\App\Http\Controllers\SupplierCatalogController::class, 'toggleActive'])->name('supplier-catalogs.toggle');
        Route::get('supplier-catalogs/{supplierCatalog}', [\App\Http\Controllers\SupplierCatalogController::class, 'show'])->name('supplier-catalogs.show');
        Route::post('supplier-catalogs/{supplierCatalog}/import', [\App\Http\Controllers\SupplierCatalogController::class, 'import'])->name('supplier-catalogs.import');
        Route::post('supplier-catalogs/{supplierCatalog}/shopinfo', [\App\Http\Controllers\SupplierCatalogController::class, 'discoverShopinfo'])->name('supplier-catalogs.shopinfo'); // MVP-092 Discovery
        Route::post('supplier-catalogs/{supplierCatalog}/fetch', [\App\Http\Controllers\SupplierCatalogController::class, 'fetchRemote'])->name('supplier-catalogs.fetch'); // Remote-Abruf (HTTP/FTP)
        // Verknüpfung Katalogartikel ↔ interner Artikelstamm (MVP-093)
        Route::get('supplier-catalogs/items/{catalogItem}/link', [\App\Http\Controllers\SupplierCatalogController::class, 'linkForm'])->name('supplier-catalogs.items.link-form');
        Route::post('supplier-catalogs/items/{catalogItem}/link', [\App\Http\Controllers\SupplierCatalogController::class, 'link'])->name('supplier-catalogs.items.link');
        Route::post('supplier-catalogs/items/{catalogItem}/unlink', [\App\Http\Controllers\SupplierCatalogController::class, 'unlink'])->name('supplier-catalogs.items.unlink');
        Route::post('supplier-catalogs/items/{catalogItem}/propose', [\App\Http\Controllers\SupplierCatalogController::class, 'propose'])->name('supplier-catalogs.items.propose');
        Route::post('supplier-catalogs/items/{catalogItem}/apply-price', [\App\Http\Controllers\SupplierCatalogController::class, 'applyPrice'])->name('supplier-catalogs.items.apply-price'); // MVP-095 Verkaufspreis-Freigabe
        // Übernahme in den Artikelstamm: Artikel + Varianten aus Tarif-Gruppen (MVP-541)
        Route::get('supplier-catalogs/{supplierCatalog}/adopt', [\App\Http\Controllers\SupplierCatalogController::class, 'adoptForm'])->name('supplier-catalogs.adopt-form');
        Route::post('supplier-catalogs/{supplierCatalog}/adopt', [\App\Http\Controllers\SupplierCatalogController::class, 'adopt'])->name('supplier-catalogs.adopt');
        Route::post('supplier-catalogs/items/{catalogItem}/adopt', [\App\Http\Controllers\SupplierCatalogController::class, 'adoptItem'])->name('supplier-catalogs.items.adopt');
        Route::get('supplier-catalogs/{supplierCatalog}/punchout', [\App\Http\Controllers\SupplierCatalogController::class, 'punchout'])->name('supplier-catalogs.punchout'); // MVP-096 aktiver Punchout-Absprung

        // ── Margenregeln (Feature 050, MVP-095) ─ Gate pricing-margin-rules.* → module.lager
        Route::get('pricing-margin-rules', [\App\Http\Controllers\PricingMarginRuleController::class, 'index'])->name('pricing-margin-rules.index');
        Route::get('pricing-margin-rules/create', [\App\Http\Controllers\PricingMarginRuleController::class, 'create'])->name('pricing-margin-rules.create');
        Route::get('pricing-margin-rules/approvals', [\App\Http\Controllers\PricingMarginRuleController::class, 'approvals'])->name('pricing-margin-rules.approvals'); // MVP-095 Vier-Augen-Anträge
        Route::post('pricing-margin-rules/approvals/{priceRequest}/approve', [\App\Http\Controllers\PricingMarginRuleController::class, 'approveRequest'])->name('pricing-margin-rules.approvals.approve');
        Route::post('pricing-margin-rules/approvals/{priceRequest}/reject', [\App\Http\Controllers\PricingMarginRuleController::class, 'rejectRequest'])->name('pricing-margin-rules.approvals.reject');
        Route::post('pricing-margin-rules/approval-mode', [\App\Http\Controllers\PricingMarginRuleController::class, 'saveApprovalMode'])->name('pricing-margin-rules.approval-mode'); // MVP-095 Freigabemodus
        Route::post('pricing-margin-rules', [\App\Http\Controllers\PricingMarginRuleController::class, 'store'])->name('pricing-margin-rules.store');
        Route::delete('pricing-margin-rules/{pricingMarginRule}', [\App\Http\Controllers\PricingMarginRuleController::class, 'destroy'])->name('pricing-margin-rules.destroy');

        // ── OCI-/IDS-Warenkorb-Hook (Feature 050, MVP-096) ─ Gate oci-carts.* → module.lager
        Route::post('oci-carts/import', [\App\Http\Controllers\OciCartController::class, 'import'])->name('oci-carts.import');

        // ── GAEB-Leistungsverzeichnisse (Feature 049, MVP-081/082) ─ Gate bill-of-quantities.* → module.bau
        Route::get('bill-of-quantities', [\App\Http\Controllers\BillOfQuantityController::class, 'index'])->name('bill-of-quantities.index');
        Route::get('bill-of-quantities/import', [\App\Http\Controllers\BillOfQuantityController::class, 'importForm'])->name('bill-of-quantities.import-form'); // vor show!
        Route::post('bill-of-quantities/import', [\App\Http\Controllers\BillOfQuantityController::class, 'import'])->name('bill-of-quantities.import');
        // Paketeingang (Feature 108, MVP-627): ZIP der Vergabestelle zerlegen,
        // GAEB-Dateien als Vorschlag ablegen, Restdokumente an die Akte.
        // Vor `{billOfQuantity}` — sonst frisst die Show-Route den Pfad.
        Route::get('bill-of-quantities/pakete', [\App\Http\Controllers\Gaeb\GaebPackageController::class, 'index'])->name('bill-of-quantities.packages');
        Route::post('bill-of-quantities/pakete', [\App\Http\Controllers\Gaeb\GaebPackageController::class, 'store'])->name('bill-of-quantities.packages.store');
        Route::post('bill-of-quantities/pakete/{import}/importieren', [\App\Http\Controllers\Gaeb\GaebPackageController::class, 'accept'])->name('bill-of-quantities.packages.accept');
        Route::delete('bill-of-quantities/pakete/{import}', [\App\Http\Controllers\Gaeb\GaebPackageController::class, 'discard'])->name('bill-of-quantities.packages.discard');
        Route::get('bill-of-quantities/{billOfQuantity}', [\App\Http\Controllers\BillOfQuantityController::class, 'show'])->name('bill-of-quantities.show');
        // MVP-084/085: LV-Workflow und GAEB-Export
        Route::get('bill-of-quantities/{billOfQuantity}/preisspiegel', [\App\Http\Controllers\BillOfQuantityController::class, 'priceComparison'])->name('bill-of-quantities.price-comparison');
        Route::get('bill-of-quantities/{billOfQuantity}/kostengruppen', [\App\Http\Controllers\BillOfQuantityController::class, 'costGroups'])->name('bill-of-quantities.cost-groups');
        // Kalkulationsdaten (Feature 109, MVP-647): EKT/GKT je Kostenart.
        Route::get('bill-of-quantities/{billOfQuantity}/kalkulation', [\App\Http\Controllers\BillOfQuantityController::class, 'calculationData'])->name('bill-of-quantities.calculation-data');
        // Zuordnungs-Oberfläche (Feature 109, MVP-639).
        Route::get('bill-of-quantities/{billOfQuantity}/zuordnung', [\App\Http\Controllers\BillOfQuantityController::class, 'catalogAssignment'])->name('bill-of-quantities.catalog-assignment');
        Route::post('bill-of-quantities/{billOfQuantity}/zuordnung', [\App\Http\Controllers\BillOfQuantityController::class, 'assignCatalogBulk'])->name('bill-of-quantities.catalog-assignment.bulk');
        Route::post('bill-of-quantities/items/{boqItem}/zuordnung', [\App\Http\Controllers\BillOfQuantityController::class, 'assignCatalog'])->name('bill-of-quantities.items.catalog-assignment');
        Route::post('bill-of-quantities/teilmengen/{split}/zuordnung', [\App\Http\Controllers\BillOfQuantityController::class, 'assignSplitCatalog'])->name('bill-of-quantities.splits.catalog-assignment');
        Route::post('bill-of-quantities/{billOfQuantity}/zuordnung/regeln', [\App\Http\Controllers\BillOfQuantityController::class, 'applyCatalogRules'])->name('bill-of-quantities.catalog-rules.apply');
        Route::match(['get', 'post'], 'bill-of-quantities/{billOfQuantity}/zuordnung/ausgabe', [\App\Http\Controllers\BillOfQuantityController::class, 'catalogEdition'])->name('bill-of-quantities.catalog-edition');
        // Kostenermittlung (Feature 109, MVP-646): X51 rein als Budget, raus
        // als Kostenanschlag bzw. Kostenfeststellung.
        Route::post('bill-of-quantities/kostenermittlung', [\App\Http\Controllers\BillOfQuantityController::class, 'costEstimateImport'])->name('bill-of-quantities.cost-estimate.import');
        Route::get('bill-of-quantities/{billOfQuantity}/kostenermittlung', [\App\Http\Controllers\BillOfQuantityController::class, 'costEstimateExport'])->name('bill-of-quantities.cost-estimate.export');
        // Baukostenkataloge (Feature 109, MVP-645): Kennwerte als
        // Nachschlagewerk, X50 rein und raus.
        // Kostenermittlung nach HOAI-Stufen (Feature 109, MVP-644).
        Route::get('projekte/{project}/kostenermittlung', [\App\Http\Controllers\Gaeb\HoaiCostReportController::class, 'show'])->name('projects.hoai-report');
        Route::get('baukostenkataloge', [\App\Http\Controllers\Gaeb\CostElementCatalogController::class, 'index'])->name('cost-catalogs.index');
        Route::post('baukostenkataloge', [\App\Http\Controllers\Gaeb\CostElementCatalogController::class, 'store'])->name('cost-catalogs.store');
        Route::get('baukostenkataloge/{catalog}', [\App\Http\Controllers\Gaeb\CostElementCatalogController::class, 'show'])->name('cost-catalogs.show');
        Route::get('baukostenkataloge/{catalog}/export', [\App\Http\Controllers\Gaeb\CostElementCatalogController::class, 'export'])->name('cost-catalogs.export');
        Route::post('baukostenkataloge/{catalog}/elemente/{element}/artikel', [\App\Http\Controllers\Gaeb\CostElementCatalogController::class, 'linkArticle'])->name('cost-catalogs.link-article');
        Route::delete('baukostenkataloge/{catalog}', [\App\Http\Controllers\Gaeb\CostElementCatalogController::class, 'destroy'])->name('cost-catalogs.destroy');
        // Regelwerk je Organisation (Feature 109, MVP-640).
        Route::get('zuordnungsregeln', [\App\Http\Controllers\Gaeb\CatalogRuleController::class, 'index'])->name('catalog-rules.index');
        Route::get('zuordnungsregeln/neu', [\App\Http\Controllers\Gaeb\CatalogRuleController::class, 'create'])->name('catalog-rules.create');
        Route::post('zuordnungsregeln', [\App\Http\Controllers\Gaeb\CatalogRuleController::class, 'store'])->name('catalog-rules.store');
        Route::get('zuordnungsregeln/{rule}/bearbeiten', [\App\Http\Controllers\Gaeb\CatalogRuleController::class, 'edit'])->name('catalog-rules.edit');
        Route::put('zuordnungsregeln/{rule}', [\App\Http\Controllers\Gaeb\CatalogRuleController::class, 'update'])->name('catalog-rules.update');
        Route::delete('zuordnungsregeln/{rule}', [\App\Http\Controllers\Gaeb\CatalogRuleController::class, 'destroy'])->name('catalog-rules.destroy');
        Route::get('bill-of-quantities/{billOfQuantity}/export', [\App\Http\Controllers\BillOfQuantityController::class, 'export'])->name('bill-of-quantities.export');
        Route::post('bill-of-quantities/{billOfQuantity}/transition', [\App\Http\Controllers\BillOfQuantityController::class, 'transition'])->name('bill-of-quantities.transition');
        Route::post('bill-of-quantities/{billOfQuantity}/addenda', [\App\Http\Controllers\BillOfQuantityController::class, 'addAddendum'])->name('bill-of-quantities.addenda.add');
        // MVP-083/084: Positionen — Aufmaß, Verknüpfung, Status
        Route::post('bill-of-quantities/items/{boqItem}/progress', [\App\Http\Controllers\BillOfQuantityController::class, 'recordProgress'])->name('bill-of-quantities.items.progress');
        Route::post('bill-of-quantities/items/{boqItem}/mappings', [\App\Http\Controllers\BillOfQuantityController::class, 'addMapping'])->name('bill-of-quantities.items.mappings.add');
        Route::post('bill-of-quantities/items/{boqItem}/transition', [\App\Http\Controllers\BillOfQuantityController::class, 'transitionItem'])->name('bill-of-quantities.items.transition');

        // Plugin-spezifische Routen (z. B. Lexoffice customers.lexoffice.*) werden
        // vom jeweiligen Plugin-ServiceProvider geladen — siehe app/Plugins/*/routes.php.

        // ── Plugin-Übersicht (Admin) ────────────────────────────────────────────
        // Autorisierung zentral über das Gate `manage-plugins` (Review 2026-08, W1c).
        Route::middleware('can:manage-plugins')->group(function (): void {
            Route::get('admin/plugins', [AdminPluginController::class, 'index'])->name('admin.plugins.index');
            Route::get('admin/plugins/{plugin}', [AdminPluginController::class, 'edit'])->name('admin.plugins.edit');
            Route::put('admin/plugins/{plugin}', [AdminPluginController::class, 'update'])->name('admin.plugins.update');
            Route::post('admin/plugins/{plugin}/toggle', [AdminPluginController::class, 'toggle'])->name('admin.plugins.toggle');
            Route::post('admin/plugins/{plugin}/health-check', [AdminPluginController::class, 'healthCheck'])
                ->middleware('throttle:6,1')
                ->name('admin.plugins.health-check');
            Route::post('admin/plugins/{plugin}/upgrade', [AdminPluginController::class, 'upgrade'])->name('admin.plugins.upgrade');
            Route::post('admin/plugins/{plugin}/reset-errors', [AdminPluginErrorController::class, 'reset'])->name('admin.plugins.reset-errors');
        });

        // ── SSO & Verzeichnisdienste (Admin, Feature 057) ───────────────────────
        // Enterprise-gegatet über config/plans.php (admin.sso.* = module.sso).
        Route::get('admin/sso', [\App\Http\Controllers\Admin\SsoAdminController::class, 'index'])->name('admin.sso.index');
        Route::post('admin/sso/tokens', [\App\Http\Controllers\Admin\SsoAdminController::class, 'issueToken'])->name('admin.sso.tokens.issue');
        Route::post('admin/sso/tokens/{token}/revoke', [\App\Http\Controllers\Admin\SsoAdminController::class, 'revokeToken'])->name('admin.sso.tokens.revoke');
        // SCIM-Gruppe → Team (bewusster Admin-Schritt; SCIM selbst vergibt kein Team/Rollen).
        Route::post('admin/sso/groups/{group}/team', [\App\Http\Controllers\Admin\SsoAdminController::class, 'mapGroupTeam'])->name('admin.sso.groups.map');
        // OIDC-/SAML-Verbindungen (MVP-120/121) + Break-Glass-Konten.
        Route::post('admin/sso/connections', [\App\Http\Controllers\Admin\SsoAdminController::class, 'saveConnection'])->name('admin.sso.connections.save');
        Route::post('admin/sso/connections/{connection}/test', [\App\Http\Controllers\Admin\SsoAdminController::class, 'testConnection'])->name('admin.sso.connections.test');
        Route::delete('admin/sso/connections/{connection}', [\App\Http\Controllers\Admin\SsoAdminController::class, 'destroyConnection'])->name('admin.sso.connections.destroy');
        Route::post('admin/sso/domains', [\App\Http\Controllers\Admin\SsoAdminController::class, 'addDomain'])->name('admin.sso.domains.add');
        Route::delete('admin/sso/domains/{domain}', [\App\Http\Controllers\Admin\SsoAdminController::class, 'removeDomain'])->name('admin.sso.domains.remove');
        Route::post('admin/sso/break-glass', [\App\Http\Controllers\Admin\SsoAdminController::class, 'toggleBreakGlass'])->name('admin.sso.break-glass.toggle');

        // ── B2B-Katalogzugang (Admin, Feature 099, module.b2b_katalog) ──
        // Punchout-Zugänge, Artikel-Freigaben und openTRANS-Bestell-Upload.
        Route::get('admin/b2b-katalog', [\App\Http\Controllers\Admin\B2bCatalogAdminController::class, 'index'])->name('b2b-catalog.index');
        Route::post('admin/b2b-katalog/zugaenge', [\App\Http\Controllers\Admin\B2bCatalogAdminController::class, 'store'])->name('b2b-catalog.store');
        Route::get('admin/b2b-katalog/zugaenge/{access}', [\App\Http\Controllers\Admin\B2bCatalogAdminController::class, 'show'])->name('b2b-catalog.show');
        Route::post('admin/b2b-katalog/zugaenge/{access}/rotate', [\App\Http\Controllers\Admin\B2bCatalogAdminController::class, 'rotate'])->name('b2b-catalog.rotate');
        Route::post('admin/b2b-katalog/zugaenge/{access}/revoke', [\App\Http\Controllers\Admin\B2bCatalogAdminController::class, 'revoke'])->name('b2b-catalog.revoke');
        Route::get('admin/b2b-katalog/zugaenge/{access}/datanorm', [\App\Http\Controllers\Admin\B2bCatalogAdminController::class, 'exportDatanorm'])->name('b2b-catalog.datanorm'); // Feature 107 W6
        Route::post('admin/b2b-katalog/zugaenge/{access}/artikel', [\App\Http\Controllers\Admin\B2bCatalogAdminController::class, 'storeItem'])->name('b2b-catalog.items.store');
        Route::delete('admin/b2b-katalog/zugaenge/{access}/artikel/{item}', [\App\Http\Controllers\Admin\B2bCatalogAdminController::class, 'destroyItem'])->name('b2b-catalog.items.destroy');
        Route::post('admin/b2b-katalog/bestellungen/upload', [\App\Http\Controllers\Admin\B2bCatalogAdminController::class, 'uploadOrder'])->name('b2b-catalog.orders.upload');

        // ── PDF-Dokumentdesign / Firmenbogen (Admin, Feature 076, module.dokumentdesign) ──
        Route::get('admin/document-design', [\App\Http\Controllers\Admin\DocumentDesignController::class, 'index'])->name('admin.document-design.index');
        Route::get('admin/document-design/assets/create', [\App\Http\Controllers\Admin\DocumentDesignController::class, 'createAsset'])->name('admin.document-design.assets.create');
        Route::post('admin/document-design/assets', [\App\Http\Controllers\Admin\DocumentDesignController::class, 'storeAsset'])->name('admin.document-design.assets.store');
        Route::get('admin/document-design/assets/{asset}/preview', [\App\Http\Controllers\Admin\DocumentDesignController::class, 'assetPreview'])->name('admin.document-design.assets.preview');
        Route::post('admin/document-design/assets/{asset}/archive', [\App\Http\Controllers\Admin\DocumentDesignController::class, 'archiveAsset'])->name('admin.document-design.assets.archive');
        Route::get('admin/document-design/profiles/create', [\App\Http\Controllers\Admin\DocumentDesignController::class, 'createProfile'])->name('admin.document-design.profiles.create');
        Route::post('admin/document-design/profiles', [\App\Http\Controllers\Admin\DocumentDesignController::class, 'storeProfile'])->name('admin.document-design.profiles.store');
        Route::get('admin/document-design/{profile}/editor', [\App\Http\Controllers\Admin\DocumentDesignController::class, 'editor'])->name('admin.document-design.editor');
        Route::put('admin/document-design/{profile}/draft', [\App\Http\Controllers\Admin\DocumentDesignController::class, 'updateDraft'])->name('admin.document-design.draft.update');
        Route::post('admin/document-design/{profile}/draft', [\App\Http\Controllers\Admin\DocumentDesignController::class, 'newDraft'])->name('admin.document-design.draft.new');
        Route::post('admin/document-design/{profile}/activate', [\App\Http\Controllers\Admin\DocumentDesignController::class, 'activate'])->name('admin.document-design.activate');
        Route::post('admin/document-design/{profile}/assign', [\App\Http\Controllers\Admin\DocumentDesignController::class, 'assign'])->name('admin.document-design.assign');
        Route::post('admin/document-design/{profile}/archive', [\App\Http\Controllers\Admin\DocumentDesignController::class, 'archiveProfile'])->name('admin.document-design.archive');
        Route::get('admin/document-design/{profile}/test-pdf', [\App\Http\Controllers\Admin\DocumentDesignController::class, 'testPdf'])->name('admin.document-design.test-pdf');
        // Eingebettete Editor-Vorschau (#83): inline-PDF, rate-limited (echtes Rendering je Aufruf).
        Route::get('admin/document-design/{profile}/preview-pdf', [\App\Http\Controllers\Admin\DocumentDesignController::class, 'previewPdf'])->middleware('throttle:30,1')->name('admin.document-design.preview-pdf');

        // ── E-Mail-Eingang (Admin, Feature 056) ─────────────────────────────────
        Route::get('admin/mail', [\App\Http\Controllers\Admin\MailAdminController::class, 'index'])->name('admin.mail.index');
        Route::post('admin/mail/connection', [\App\Http\Controllers\Admin\MailAdminController::class, 'store'])->name('admin.mail.connection.store');
        Route::post('admin/mail/disconnect', [\App\Http\Controllers\Admin\MailAdminController::class, 'disconnect'])->name('admin.mail.disconnect');
        Route::post('admin/mail/poll', [\App\Http\Controllers\Admin\MailAdminController::class, 'poll'])->name('admin.mail.poll');
        Route::post('admin/mail/inbox/book', [\App\Http\Controllers\Admin\MailAdminController::class, 'book'])->name('admin.mail.inbox.book');
        Route::post('admin/mail/inbox/book-ticket', [\App\Http\Controllers\Admin\MailAdminController::class, 'bookTicket'])->name('admin.mail.inbox.book-ticket');
        Route::post('admin/mail/inbox/import-dms', [\App\Http\Controllers\Admin\MailAdminController::class, 'importDms'])->name('admin.mail.inbox.import-dms');

        // ── Telefonie / CTI (Admin, Feature 056) ────────────────────────────────
        Route::get('admin/cti', [\App\Http\Controllers\Admin\CtiAdminController::class, 'index'])->name('admin.cti.index');
        Route::post('admin/cti/connection', [\App\Http\Controllers\Admin\CtiAdminController::class, 'store'])->name('admin.cti.connection.store');
        Route::post('admin/cti/disconnect', [\App\Http\Controllers\Admin\CtiAdminController::class, 'disconnect'])->name('admin.cti.disconnect');

        // ── Versand-/Carrier-Anbindungen (Admin, Feature 059, module.versand) ────
        Route::get('admin/versand', [\App\Http\Controllers\Admin\ShipmentAdminController::class, 'index'])->name('admin.shipments.connections.index');
        Route::post('admin/versand/connection', [\App\Http\Controllers\Admin\ShipmentAdminController::class, 'store'])->name('admin.shipments.connections.store');
        Route::post('admin/versand/disconnect', [\App\Http\Controllers\Admin\ShipmentAdminController::class, 'disconnect'])->name('admin.shipments.connections.disconnect');

        // ── Team-Messenger-Kanäle (Admin, Feature 056) ──────────────────────────
        Route::get('admin/chat', [\App\Http\Controllers\Admin\ChatAdminController::class, 'index'])->name('admin.chat.index');
        Route::post('admin/chat/connection', [\App\Http\Controllers\Admin\ChatAdminController::class, 'store'])->name('admin.chat.connection.store');
        Route::post('admin/chat/test', [\App\Http\Controllers\Admin\ChatAdminController::class, 'test'])->name('admin.chat.test');
        Route::post('admin/chat/disconnect', [\App\Http\Controllers\Admin\ChatAdminController::class, 'disconnect'])->name('admin.chat.disconnect');

        // ── Hardware-Stempelterminals (Admin, Feature 061) ──────────────────────
        Route::get('admin/terminals', [\App\Http\Controllers\Admin\TerminalAdminController::class, 'index'])->name('admin.terminals.index');
        Route::post('admin/terminals', [\App\Http\Controllers\Admin\TerminalAdminController::class, 'storeTerminal'])->name('admin.terminals.store');
        Route::post('admin/terminals/disconnect', [\App\Http\Controllers\Admin\TerminalAdminController::class, 'disconnectTerminal'])->name('admin.terminals.disconnect');
        Route::post('admin/terminals/rotate', [\App\Http\Controllers\Admin\TerminalAdminController::class, 'rotateTerminal'])->name('admin.terminals.rotate');
        Route::post('admin/terminals/toggle-status', [\App\Http\Controllers\Admin\TerminalAdminController::class, 'toggleStatus'])->name('admin.terminals.toggle-status');
        Route::post('admin/terminals/badges', [\App\Http\Controllers\Admin\TerminalAdminController::class, 'storeBadge'])->name('admin.terminals.badges.store');
        Route::post('admin/terminals/badges/revoke', [\App\Http\Controllers\Admin\TerminalAdminController::class, 'revokeBadge'])->name('admin.terminals.badges.revoke');

        // ── Freie Mandanten-Dimensionen (Feature 103, MVP-514 P2) ───────────────
        // ── Gespeicherte Report-Ansichten (MVP-529) ─────────────────────────────
        Route::get('auswertungen/ansichten', [\App\Http\Controllers\SavedReportViewController::class, 'index'])->name('report-views.index');
        Route::post('auswertungen/ansichten', [\App\Http\Controllers\SavedReportViewController::class, 'store'])->name('report-views.store');
        Route::post('auswertungen/ansichten/{view}/teilen', [\App\Http\Controllers\SavedReportViewController::class, 'toggleShare'])->name('report-views.toggle-share');
        Route::delete('auswertungen/ansichten/{view}', [\App\Http\Controllers\SavedReportViewController::class, 'destroy'])->name('report-views.destroy');

        // ── Änderungsverlauf/Versionsvergleich (MVP-528) ────────────────────────
        Route::get('admin/aenderungsverlauf', [\App\Http\Controllers\Admin\AuditDiffController::class, 'index'])->name('admin.audit-diff.index');

        // ── Zeitkonten (MVP-526) ────────────────────────────────────────────────
        Route::get('zeitkonten', [\App\Http\Controllers\TimeAccountController::class, 'index'])->name('time-accounts.index');
        Route::get('admin/zeitkonten', [\App\Http\Controllers\Admin\TimeAccountAdminController::class, 'index'])->name('admin.time-accounts.index');
        Route::get('admin/zeitkonten/neu', [\App\Http\Controllers\Admin\TimeAccountAdminController::class, 'create'])->name('admin.time-accounts.create');
        Route::post('admin/zeitkonten', [\App\Http\Controllers\Admin\TimeAccountAdminController::class, 'store'])->name('admin.time-accounts.store');
        Route::post('admin/zeitkonten/lauf', [\App\Http\Controllers\Admin\TimeAccountAdminController::class, 'post'])->name('admin.time-accounts.post');
        Route::post('admin/zeitkonten/{account}/umschalten', [\App\Http\Controllers\Admin\TimeAccountAdminController::class, 'toggle'])->name('admin.time-accounts.toggle');
        Route::post('admin/zeitkonten/{account}/regeln', [\App\Http\Controllers\Admin\TimeAccountAdminController::class, 'storeRule'])->name('admin.time-accounts.rules.store');
        Route::delete('admin/zeitkonten/{account}/regeln/{rule}', [\App\Http\Controllers\Admin\TimeAccountAdminController::class, 'destroyRule'])->name('admin.time-accounts.rules.destroy');
        Route::post('admin/zeitkonten/{account}/buchung', [\App\Http\Controllers\Admin\TimeAccountAdminController::class, 'manualEntry'])->name('admin.time-accounts.manual');
        Route::get('reports/time-accounts', [\App\Http\Controllers\Reporting\TimeAccountsReportController::class, 'index'])->name('reports.time-accounts');
        Route::get('reports/zeitkonten-periodenvergleich', [\App\Http\Controllers\Reporting\TimeAccountComparisonReportController::class, 'index'])->name('reports.time-account-comparison');

        // ── Rollpläne (MVP-522) ─────────────────────────────────────────────────
        Route::get('admin/rollplaene', [\App\Http\Controllers\Admin\ShiftRotationController::class, 'index'])->name('admin.shift-rotations.index');
        Route::get('admin/rollplaene/neu', [\App\Http\Controllers\Admin\ShiftRotationController::class, 'create'])->name('admin.shift-rotations.create');
        Route::post('admin/rollplaene', [\App\Http\Controllers\Admin\ShiftRotationController::class, 'store'])->name('admin.shift-rotations.store');
        Route::put('admin/rollplaene/{rotation}/raster', [\App\Http\Controllers\Admin\ShiftRotationController::class, 'updateEntries'])->name('admin.shift-rotations.entries');
        Route::post('admin/rollplaene/{rotation}/umschalten', [\App\Http\Controllers\Admin\ShiftRotationController::class, 'toggle'])->name('admin.shift-rotations.toggle');
        Route::post('admin/rollplaene/{rotation}/zuweisungen', [\App\Http\Controllers\Admin\ShiftRotationController::class, 'storeAssignment'])->name('admin.shift-rotations.assignments.store');
        Route::delete('admin/rollplaene/zuweisungen/{assignment}', [\App\Http\Controllers\Admin\ShiftRotationController::class, 'destroyAssignment'])->name('admin.shift-rotations.assignments.destroy');
        Route::post('admin/rollplaene/fortschreiben', [\App\Http\Controllers\Admin\ShiftRotationController::class, 'roll'])->name('admin.shift-rotations.roll');

        Route::get('admin/time-dimensions', [\App\Http\Controllers\Admin\TimeDimensionAdminController::class, 'index'])->name('admin.time-dimensions.index');
        Route::post('admin/time-dimensions/types', [\App\Http\Controllers\Admin\TimeDimensionAdminController::class, 'storeType'])->name('admin.time-dimensions.types.store');
        Route::post('admin/time-dimensions/types/{type}/toggle', [\App\Http\Controllers\Admin\TimeDimensionAdminController::class, 'toggleType'])->name('admin.time-dimensions.types.toggle');
        Route::post('admin/time-dimensions/types/{type}/values', [\App\Http\Controllers\Admin\TimeDimensionAdminController::class, 'storeValue'])->name('admin.time-dimensions.values.store');
        Route::delete('admin/time-dimensions/values/{value}', [\App\Http\Controllers\Admin\TimeDimensionAdminController::class, 'destroyValue'])->name('admin.time-dimensions.values.destroy');

        // ── Plugin-Fehler-Inbox (Admin) ─────────────────────────────────────────
        Route::middleware('can:manage-plugins')->group(function (): void {
            Route::get('admin/plugin-errors', [AdminPluginErrorController::class, 'index'])->name('admin.plugin-errors.index');
            Route::post('admin/plugin-errors/bulk-acknowledge', [AdminPluginErrorController::class, 'bulkAcknowledge'])->name('admin.plugin-errors.bulk-acknowledge');
            Route::get('admin/plugin-errors/{pluginError}', [AdminPluginErrorController::class, 'show'])->name('admin.plugin-errors.show');
            Route::post('admin/plugin-errors/{pluginError}/acknowledge', [AdminPluginErrorController::class, 'acknowledge'])->name('admin.plugin-errors.acknowledge');
            Route::post('admin/plugin-errors/{pluginError}/reopen', [AdminPluginErrorController::class, 'reopen'])->name('admin.plugin-errors.reopen');
        });

        // ── Rechnungs-Mail-Templates (Admin) ─────────────────────────────────────
        Route::resource('admin/invoice-mail-templates', InvoiceMailTemplateController::class)
            ->except(['show'])
            ->names('admin.invoice-mail-templates')
            ->parameters(['admin/invoice-mail-templates' => 'invoiceMailTemplate']);

        // Projekt-Abgleich (Dubletten-Bereinigung) — VOR der Resource-Route, damit
        // "projects/duplicates" nicht als projects/{project} interpretiert wird.
        Route::get('projects/duplicates', [ProjectMergeController::class, 'index'])->name('projects.duplicates.index');
        Route::get('projects/duplicates/compare', [ProjectMergeController::class, 'compare'])->name('projects.duplicates.compare');
        Route::post('projects/duplicates/merge', [ProjectMergeController::class, 'merge'])->name('projects.duplicates.merge');
        Route::post('projects/duplicates/bulk-merge', [ProjectMergeController::class, 'bulkMerge'])->name('projects.duplicates.bulk-merge');
        Route::post('projects/duplicates/dismiss', [ProjectMergeController::class, 'dismiss'])->name('projects.duplicates.dismiss');
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

        // ── Belegfluss (Feature 105, MVP-543/546) ─────────────────────
        // Eine Liste über Angebote, Rechnungen, Belege, Eingangsrechnungen und
        // Auslagen; die früheren drei Seiten sind Filterzustände davon.
        Route::get('finanzen/belege', [\App\Http\Controllers\Billing\DocumentFeedController::class, 'index'])->name('billing.feed');

        // ── Rechnungen / Invoicing ────────────────────────────────────
        // MVP-549: Bestandsroute → Feed mit vorgesetztem Tab.
        Route::get('invoices', [\App\Http\Controllers\Billing\DocumentFeedController::class, 'fromInvoices'])->name('invoices.index');
        Route::get('invoices/create', [InvoiceController::class, 'create'])->name('invoices.create');
        Route::get('invoices/datei-import', [\App\Http\Controllers\InvoicePdfImportController::class, 'create'])->name('invoices.pdf-import.create');
        Route::post('invoices/datei-import', [\App\Http\Controllers\InvoicePdfImportController::class, 'store'])->name('invoices.pdf-import.store');
        // Vorschau (MVP-462): POST + Literal-Segment VOR invoices/{invoice},
        // damit das Sqid-Binding nicht greift (Muster admin/ai/vorschau).
        Route::post('invoices/vorschau', [InvoiceController::class, 'preview'])->name('invoices.preview');
        Route::post('invoices', [InvoiceController::class, 'store'])->name('invoices.store');
        Route::get('invoices/{invoice}', [InvoiceController::class, 'show'])->name('invoices.show');
        Route::delete('invoices/{invoice}', [InvoiceController::class, 'destroy'])->name('invoices.destroy');
        Route::post('invoices/{invoice}/issue', [InvoiceController::class, 'issue'])->name('invoices.issue');
        Route::post('invoices/{invoice}/approve', [InvoiceController::class, 'approve'])->name('invoices.approve');
        Route::get('invoices/{invoice}/dun', [InvoiceController::class, 'dunForm'])->name('invoices.dun.form');
        Route::post('invoices/{invoice}/dun', [InvoiceController::class, 'dun'])->name('invoices.dun');
        // Vollaudit 2026-07 (M27): § 14 Abs. 2 UStG — Widerspruch dokumentieren.
        Route::post('invoices/{invoice}/widerspruch', [InvoiceController::class, 'documentObjection'])->name('invoices.objection');
        Route::post('invoices/{invoice}/proforma-umwandeln', [InvoiceController::class, 'proformaConvert'])->name('invoices.proforma-convert');
        Route::post('invoices/{invoice}/final', [InvoiceController::class, 'makeFinal'])->name('invoices.final');
        Route::post('invoices/{invoice}/pay', [InvoiceController::class, 'pay'])->name('invoices.pay');
        Route::post('invoices/{invoice}/cancel', [InvoiceController::class, 'cancel'])->name('invoices.cancel');
        Route::post('invoices/{invoice}/credit-note', [InvoiceController::class, 'creditNote'])->name('invoices.credit-note');
        Route::get('invoices/{invoice}/send', [InvoiceController::class, 'sendForm'])->name('invoices.send.form');
        Route::post('invoices/{invoice}/send', [InvoiceController::class, 'send'])->name('invoices.send');
        Route::get('invoices/{invoice}/pdf', [InvoiceController::class, 'pdf'])->name('invoices.pdf');
        Route::get('invoices/{invoice}/einvoice', [InvoiceController::class, 'einvoiceDownload'])->name('invoices.einvoice');
        Route::get('invoices/{invoice}/gaeb', [InvoiceController::class, 'gaebDownload'])->name('invoices.gaeb');
        Route::get('invoices/{invoice}/einvoice-validierung', [\App\Http\Controllers\InvoiceController::class, 'einvoiceValidation'])->name('invoices.einvoice-validation');
        Route::get('invoices/{invoice}/zugferd', [InvoiceController::class, 'zugferdDownload'])->name('invoices.zugferd');
        Route::get('invoices/{invoice}/e-rechnungsoptionen', [\App\Http\Controllers\InvoicePdfImportController::class, 'edit'])->name('invoices.einvoice-options.edit');
        Route::patch('invoices/{invoice}/e-rechnungsoptionen', [\App\Http\Controllers\InvoicePdfImportController::class, 'update'])->name('invoices.einvoice-options.update');
        Route::get('invoices/{invoice}/originaldatei', [\App\Http\Controllers\InvoicePdfImportController::class, 'source'])->name('invoices.pdf-import.source');
        Route::get('invoices/{invoice}/originaldatei-vorschau', [\App\Http\Controllers\InvoicePdfImportController::class, 'sourcePreview'])->name('invoices.pdf-import.preview');
        Route::get('invoices/{invoice}/import-pruefung', [\App\Http\Controllers\InvoicePdfImportController::class, 'review'])->name('invoices.import-review');
        Route::post('invoices/{invoice}/import-pruefung', [\App\Http\Controllers\InvoicePdfImportController::class, 'confirmReview'])->name('invoices.import-review.confirm');
        Route::get('invoices/{invoice}/expenses', [InvoiceController::class, 'expensesForm'])->name('invoices.expenses.form');
        Route::post('invoices/{invoice}/expenses', [InvoiceController::class, 'attachExpenses'])->name('invoices.expenses.attach');
        // MVP-416: Rabatt-/Skonto-Konditionen am Entwurf
        Route::get('invoices/{invoice}/conditions', [InvoiceController::class, 'conditionsForm'])->name('invoices.conditions.form');
        Route::patch('invoices/{invoice}/conditions', [InvoiceController::class, 'updateConditions'])->name('invoices.conditions.update');

        // MVP-415: Abrechnungspläne für wiederkehrende Rechnungen (Scheduler erzeugt nur Entwürfe)
        Route::resource('invoice-schedules', InvoiceScheduleController::class)
            ->parameters(['invoice-schedules' => 'invoice_schedule']);
        Route::patch('invoice-schedules/{invoice_schedule}/status', [InvoiceScheduleController::class, 'setStatus'])->name('invoice-schedules.status');
        Route::get('invoice-schedules/{invoice_schedule}/items/create', [InvoiceScheduleController::class, 'itemForm'])->name('invoice-schedules.items.create');
        Route::post('invoice-schedules/{invoice_schedule}/items', [InvoiceScheduleController::class, 'addItem'])->name('invoice-schedules.items.store');
        Route::get('invoice-schedules/{invoice_schedule}/items/{item}/edit', [InvoiceScheduleController::class, 'itemForm'])->name('invoice-schedules.items.edit');
        Route::put('invoice-schedules/{invoice_schedule}/items/{item}', [InvoiceScheduleController::class, 'updateItem'])->name('invoice-schedules.items.update');
        Route::delete('invoice-schedules/{invoice_schedule}/items/{item}', [InvoiceScheduleController::class, 'removeItem'])->name('invoice-schedules.items.destroy');

        // MVP-414: Kassenbuch — append-only, Storno statt Löschen, Tagesabschluss
        Route::get('cash-registers', [CashRegisterController::class, 'index'])->name('cash-registers.index');
        Route::get('cash-registers/create', [CashRegisterController::class, 'create'])->name('cash-registers.create');
        Route::post('cash-registers', [CashRegisterController::class, 'store'])->name('cash-registers.store');
        Route::get('cash-registers/{cash_register}', [CashRegisterController::class, 'show'])->name('cash-registers.show');
        Route::get('cash-registers/{cash_register}/entries/create', [CashRegisterController::class, 'entryForm'])->name('cash-registers.entries.create');
        Route::post('cash-registers/{cash_register}/entries', [CashRegisterController::class, 'storeEntry'])->name('cash-registers.entries.store');
        Route::get('cash-registers/{cash_register}/entries/{entry}/reverse', [CashRegisterController::class, 'reverseForm'])->name('cash-registers.entries.reverse-form');
        Route::post('cash-registers/{cash_register}/entries/{entry}/reverse', [CashRegisterController::class, 'reverseEntry'])->name('cash-registers.entries.reverse');
        Route::get('cash-registers/{cash_register}/close', [CashRegisterController::class, 'closeForm'])->name('cash-registers.close-form');
        Route::post('cash-registers/{cash_register}/close', [CashRegisterController::class, 'closeDay'])->name('cash-registers.close');
        Route::get('invoices/{invoice}/items/create', [InvoiceController::class, 'itemForm'])->name('invoices.items.create');
        Route::post('invoices/{invoice}/items', [InvoiceController::class, 'addItem'])->name('invoices.items.store');
        Route::get('invoices/{invoice}/items/{item}/edit', [InvoiceController::class, 'itemForm'])->name('invoices.items.edit');
        Route::put('invoices/{invoice}/items/{item}', [InvoiceController::class, 'updateItem'])->name('invoices.items.update');
        Route::delete('invoices/{invoice}/items/{item}', [InvoiceController::class, 'removeItem'])->name('invoices.items.destroy');

        // ── Bekanntmachungs-Radar (Feature 108, MVP-629/630) ──
        // Eigener Pfad, nicht in der tenders-Gruppe: dort fängt `{opportunity}`
        // jedes erste Segment ab.
        Route::prefix('ausschreibungs-radar')->name('tender-radar.')->group(function (): void {
            Route::get('/', [\App\Http\Controllers\Tenders\TenderNoticeController::class, 'index'])->name('index');
            Route::get('profile', [\App\Http\Controllers\Tenders\TenderNoticeController::class, 'profiles'])->name('profiles');
            Route::get('profile/neu', [\App\Http\Controllers\Tenders\TenderNoticeController::class, 'createProfile'])->name('profiles.create');
            Route::post('profile', [\App\Http\Controllers\Tenders\TenderNoticeController::class, 'storeProfile'])->name('profiles.store');
            Route::get('profile/{profile}/bearbeiten', [\App\Http\Controllers\Tenders\TenderNoticeController::class, 'editProfile'])->name('profiles.edit');
            Route::put('profile/{profile}', [\App\Http\Controllers\Tenders\TenderNoticeController::class, 'updateProfile'])->name('profiles.update');
            Route::delete('profile/{profile}', [\App\Http\Controllers\Tenders\TenderNoticeController::class, 'destroyProfile'])->name('profiles.destroy');
            Route::post('{match}/ausblenden', [\App\Http\Controllers\Tenders\TenderNoticeController::class, 'mute'])->name('mute');
            Route::post('{match}/einblenden', [\App\Http\Controllers\Tenders\TenderNoticeController::class, 'restore'])->name('restore');
            Route::post('{match}/uebernehmen', [\App\Http\Controllers\Tenders\TenderNoticeController::class, 'convert'])->name('convert');
        });

        // ── Bewerbungen & Ausschreibungen (Feature 068, module.applications) ──
        Route::prefix('ausschreibungen')->name('tenders.')->group(function (): void {
            Route::get('/', [\App\Http\Controllers\Applications\TenderController::class, 'index'])->name('index');
            Route::get('neu', [\App\Http\Controllers\Applications\TenderController::class, 'create'])->name('create');
            Route::post('/', [\App\Http\Controllers\Applications\TenderController::class, 'store'])->name('store');
            Route::get('{opportunity}', [\App\Http\Controllers\Applications\TenderController::class, 'show'])->name('show');
            Route::get('{opportunity}/bearbeiten', [\App\Http\Controllers\Applications\TenderController::class, 'edit'])->name('edit');
            Route::put('{opportunity}', [\App\Http\Controllers\Applications\TenderController::class, 'update'])->name('update');
            Route::delete('{opportunity}', [\App\Http\Controllers\Applications\TenderController::class, 'destroy'])->name('destroy');
            Route::post('{opportunity}/status', [\App\Http\Controllers\Applications\TenderController::class, 'updateStatus'])->name('status');
            Route::post('{opportunity}/go', [\App\Http\Controllers\Applications\TenderController::class, 'decideGo'])->name('go');
            Route::get('{opportunity}/abgabe', [\App\Http\Controllers\Applications\TenderController::class, 'submitWizard'])->name('submit-wizard');
            Route::post('{opportunity}/einreichen', [\App\Http\Controllers\Applications\TenderController::class, 'submit'])->name('submit');
            // Submissionsergebnis (MVP-628): verlesene Angebote am Eröffnungstermin.
            Route::post('{opportunity}/submissionsergebnis', [\App\Http\Controllers\Applications\TenderController::class, 'addCompetitorBid'])->name('bids.store');
            Route::delete('{opportunity}/submissionsergebnis/{bid}', [\App\Http\Controllers\Applications\TenderController::class, 'removeCompetitorBid'])->name('bids.destroy');
            Route::post('{opportunity}/entscheiden', [\App\Http\Controllers\Applications\TenderController::class, 'decide'])->name('decide');
            Route::post('{opportunity}/ueberfuehren', [\App\Http\Controllers\Applications\TenderController::class, 'transfer'])->name('transfer');
            Route::post('{opportunity}/anforderungen', [\App\Http\Controllers\Applications\TenderController::class, 'addRequirement'])->name('requirements.store');
            Route::put('{opportunity}/anforderungen/{requirement}', [\App\Http\Controllers\Applications\TenderController::class, 'updateRequirement'])->name('requirements.update');
            Route::delete('{opportunity}/anforderungen/{requirement}', [\App\Http\Controllers\Applications\TenderController::class, 'removeRequirement'])->name('requirements.destroy');
            Route::post('{opportunity}/vertrag', [\App\Http\Controllers\Applications\ContractNegotiationController::class, 'storeForTender'])->name('negotiations.store');
        });
        Route::prefix('personal/stellen')->name('recruiting.requisitions.')->group(function (): void {
            Route::get('/', [\App\Http\Controllers\Applications\JobRequisitionController::class, 'index'])->name('index');
            Route::get('neu', [\App\Http\Controllers\Applications\JobRequisitionController::class, 'create'])->name('create');
            Route::post('/', [\App\Http\Controllers\Applications\JobRequisitionController::class, 'store'])->name('store');
            Route::get('{requisition}', [\App\Http\Controllers\Applications\JobRequisitionController::class, 'show'])->name('show');
            Route::get('{requisition}/bearbeiten', [\App\Http\Controllers\Applications\JobRequisitionController::class, 'edit'])->name('edit');
            Route::put('{requisition}', [\App\Http\Controllers\Applications\JobRequisitionController::class, 'update'])->name('update');
            Route::post('{requisition}/status', [\App\Http\Controllers\Applications\JobRequisitionController::class, 'updateStatus'])->name('status');
            Route::post('{requisition}/veroeffentlichungen', [\App\Http\Controllers\Applications\JobRequisitionController::class, 'addPosting'])->name('postings.store');
            Route::post('{requisition}/veroeffentlichungen/{posting}/schliessen', [\App\Http\Controllers\Applications\JobRequisitionController::class, 'closePosting'])->name('postings.close');
            // MVP-437: öffentlicher Karrierebereich — explizite Veröffentlichung/Pause.
            Route::post('{requisition}/karriere', [\App\Http\Controllers\Applications\JobRequisitionController::class, 'publishCareer'])->name('career.publish');
            Route::post('{requisition}/karriere/pausieren', [\App\Http\Controllers\Applications\JobRequisitionController::class, 'pauseCareer'])->name('career.pause');
        });
        Route::prefix('personal/bewerbungen')->name('recruiting.applications.')->group(function (): void {
            Route::get('/', [\App\Http\Controllers\Applications\JobApplicationController::class, 'index'])->name('index');
            Route::get('neu', [\App\Http\Controllers\Applications\JobApplicationController::class, 'create'])->name('create');
            Route::post('/', [\App\Http\Controllers\Applications\JobApplicationController::class, 'store'])->name('store');
            Route::get('{application}', [\App\Http\Controllers\Applications\JobApplicationController::class, 'show'])->name('show');
            Route::post('{application}/status', [\App\Http\Controllers\Applications\JobApplicationController::class, 'updateStatus'])->name('status');
            Route::post('{application}/gespraeche', [\App\Http\Controllers\Applications\JobApplicationController::class, 'addInterview'])->name('interviews.store');
            Route::post('{application}/gespraeche/{interview}/abschliessen', [\App\Http\Controllers\Applications\JobApplicationController::class, 'completeInterview'])->name('interviews.complete');
            Route::post('{application}/bewertungen', [\App\Http\Controllers\Applications\JobApplicationController::class, 'addReview'])->name('reviews.store');
            Route::post('{application}/unterlagen', [\App\Http\Controllers\Applications\JobApplicationController::class, 'addDocument'])->name('documents.store');
            Route::post('{application}/entscheiden', [\App\Http\Controllers\Applications\JobApplicationController::class, 'decide'])->name('decide');
            Route::get('{application}/auskunft', [\App\Http\Controllers\Applications\JobApplicationController::class, 'export'])->name('export');
            Route::post('{application}/anonymisieren', [\App\Http\Controllers\Applications\JobApplicationController::class, 'anonymize'])->name('anonymize');
            Route::post('{application}/onboarding', [\App\Http\Controllers\Applications\JobApplicationController::class, 'createDraft'])->name('draft.store');
            Route::post('{application}/onboarding/{draft}/einladen', [\App\Http\Controllers\Applications\JobApplicationController::class, 'inviteDraft'])->name('draft.invite');
            Route::post('{application}/vertrag', [\App\Http\Controllers\Applications\ContractNegotiationController::class, 'storeForApplication'])->name('negotiations.store');
        });
        Route::prefix('vertragsverhandlungen')->name('applications.negotiations.')->group(function (): void {
            Route::post('{negotiation}/versionen', [\App\Http\Controllers\Applications\ContractNegotiationController::class, 'addVersion'])->name('versions.store');
            Route::post('{negotiation}/review-punkte', [\App\Http\Controllers\Applications\ContractNegotiationController::class, 'addReviewItem'])->name('reviews.store');
            Route::post('{negotiation}/review-punkte/{item}/entscheiden', [\App\Http\Controllers\Applications\ContractNegotiationController::class, 'resolveReviewItem'])->name('reviews.resolve');
            Route::post('{negotiation}/freigeben', [\App\Http\Controllers\Applications\ContractNegotiationController::class, 'approve'])->name('approve');
            Route::post('{negotiation}/abschliessen', [\App\Http\Controllers\Applications\ContractNegotiationController::class, 'conclude'])->name('conclude');
        });
        Route::get('berichte/bewerbungen', [\App\Http\Controllers\Reporting\ApplicationsReportController::class, 'index'])->name('applications.report');
        // Vergabe-Cockpit (Feature 108, MVP-631): Fristensicht neben Pipeline
        // und Trefferquote — im Vergabegeschäft entscheiden Fristen.
        Route::get('berichte/vergabe', [\App\Http\Controllers\Reporting\TenderCockpitController::class, 'index'])->name('tenders.cockpit');

        // ── Reklamation/Gewährleistung/Rückläufer (Feature 072, module.claims) ──
        Route::prefix('reklamationen')->name('claims.')->group(function (): void {
            Route::get('/', [\App\Http\Controllers\Claims\ClaimCaseController::class, 'index'])->name('index');
            Route::get('neu', [\App\Http\Controllers\Claims\ClaimCaseController::class, 'create'])->name('create');
            Route::post('/', [\App\Http\Controllers\Claims\ClaimCaseController::class, 'store'])->name('store');
            Route::get('bericht', [\App\Http\Controllers\Reporting\ClaimsReportController::class, 'index'])->name('reports.index');
            Route::post('bericht/snapshot', [\App\Http\Controllers\Reporting\ClaimsReportController::class, 'snapshot'])->name('reports.snapshot');
            Route::get('{claim}', [\App\Http\Controllers\Claims\ClaimCaseController::class, 'show'])->name('show');
            Route::put('{claim}', [\App\Http\Controllers\Claims\ClaimCaseController::class, 'update'])->name('update');
            Route::post('{claim}/bewerten', [\App\Http\Controllers\Claims\ClaimCaseController::class, 'assess'])->name('assess');
            Route::post('{claim}/entscheiden', [\App\Http\Controllers\Claims\ClaimCaseController::class, 'decide'])->name('decide');
            Route::post('{claim}/status', [\App\Http\Controllers\Claims\ClaimCaseController::class, 'transition'])->name('transition');
            Route::post('{claim}/nachweise', [\App\Http\Controllers\Claims\ClaimCaseController::class, 'storeEvidence'])->name('evidence.store');
            Route::post('{claim}/ruecksendungen', [\App\Http\Controllers\Claims\ClaimRmaController::class, 'store'])->name('rma.store');
            Route::post('ruecksendungen/{rma}/wareneingang', [\App\Http\Controllers\Claims\ClaimRmaController::class, 'receive'])->name('rma.receive');
            Route::post('ruecksendungen/{rma}/pruefen', [\App\Http\Controllers\Claims\ClaimRmaController::class, 'inspect'])->name('rma.inspect');
            Route::post('ruecksendungen/{rma}/verwendung', [\App\Http\Controllers\Claims\ClaimRmaController::class, 'disposition'])->name('rma.disposition');
            Route::post('{claim}/massnahmen', [\App\Http\Controllers\Claims\ClaimActionController::class, 'store'])->name('actions.store');
            Route::put('massnahmen/{action}', [\App\Http\Controllers\Claims\ClaimActionController::class, 'update'])->name('actions.update');
            Route::post('{claim}/folgen', [\App\Http\Controllers\Claims\ClaimFinancialController::class, 'store'])->name('financial.store');
            Route::post('folgen/{outcome}/freigeben', [\App\Http\Controllers\Claims\ClaimFinancialController::class, 'approve'])->name('financial.approve');
            Route::post('folgen/{outcome}/ausfuehren', [\App\Http\Controllers\Claims\ClaimFinancialController::class, 'execute'])->name('financial.execute');
            Route::post('folgen/{outcome}/belegnummer', [\App\Http\Controllers\Claims\ClaimFinancialController::class, 'reference'])->name('financial.reference');
            Route::post('{claim}/regress', [\App\Http\Controllers\Claims\ClaimRecourseController::class, 'store'])->name('recourses.store');
            Route::put('regress/{recourse}', [\App\Http\Controllers\Claims\ClaimRecourseController::class, 'update'])->name('recourses.update');
        });

        // ── Domainverwaltung / DomainReselling (Feature 083, module.domain) ──
        // Gating via config/plans.routes: admin.domain-provider.*/domains.*/domain-reseller.* → module.domain.
        Route::prefix('admin/domainreselling')->name('admin.domain-provider.')->group(function (): void {
            Route::get('/', [\App\Http\Controllers\Admin\Domain\DomainProviderConnectionController::class, 'index'])->name('index');
            Route::get('verbinden', [\App\Http\Controllers\Admin\Domain\DomainProviderConnectionController::class, 'create'])->name('create');
            Route::post('/', [\App\Http\Controllers\Admin\Domain\DomainProviderConnectionController::class, 'store'])->name('store');
            Route::post('{connection}/pruefen', [\App\Http\Controllers\Admin\Domain\DomainProviderConnectionController::class, 'test'])->name('test');
            Route::post('{connection}/zugangsdaten', [\App\Http\Controllers\Admin\Domain\DomainProviderConnectionController::class, 'rotate'])->name('rotate');
            Route::post('{connection}/abgleich', [\App\Http\Controllers\Admin\Domain\DomainProviderConnectionController::class, 'sync'])->name('sync');
            Route::post('{connection}/pilot', [\App\Http\Controllers\Admin\Domain\DomainProviderConnectionController::class, 'confirmPilot'])->name('pilot');
            Route::delete('{connection}', [\App\Http\Controllers\Admin\Domain\DomainProviderConnectionController::class, 'destroy'])->name('destroy');
        });

        // ── KI-Assistenz (Feature 025, MVP-400/401, module.ai) ──
        // Gating via config/plans.routes: admin.ai.* → module.ai.
        Route::prefix('admin/ki')->name('admin.ai.')->group(function (): void {
            Route::get('/', [\App\Http\Controllers\Admin\Ai\AiConnectionController::class, 'index'])->name('index');
            Route::get('verbinden', [\App\Http\Controllers\Admin\Ai\AiConnectionController::class, 'create'])->name('create');
            Route::post('/', [\App\Http\Controllers\Admin\Ai\AiConnectionController::class, 'store'])->name('store');
            // Statische Segmente VOR dem {connection}-Wildcard.
            // Verbrauchsbericht (Feature 025, Phase-36-Rest) — statisch VOR {connection}.
            Route::get('verbrauch', [\App\Http\Controllers\Admin\Ai\AiUsageReportController::class, 'index'])->name('usage');
            Route::get('gedaechtnis', [\App\Http\Controllers\Admin\Ai\AiMemoryController::class, 'index'])->name('memory');
            // DSGVO-Export des Gedächtnisses (Vollaudit 2026-07, M9), optional ?kunde=<id>.
            Route::get('gedaechtnis/export', [\App\Http\Controllers\Admin\Ai\AiMemoryController::class, 'export'])->name('memory.export');
            Route::get('gedaechtnis/neu', [\App\Http\Controllers\Admin\Ai\AiMemoryController::class, 'create'])->name('memory.create');
            Route::post('gedaechtnis', [\App\Http\Controllers\Admin\Ai\AiMemoryController::class, 'store'])->name('memory.store');
            Route::post('gedaechtnis/{entry}/umschalten', [\App\Http\Controllers\Admin\Ai\AiMemoryController::class, 'toggle'])->name('memory.toggle');
            Route::delete('gedaechtnis/{entry}', [\App\Http\Controllers\Admin\Ai\AiMemoryController::class, 'destroy'])->name('memory.destroy');
            Route::get('capability/{capability}', [\App\Http\Controllers\Admin\Ai\AiCapabilityController::class, 'edit'])->name('capability.edit');
            Route::post('capability/{capability}', [\App\Http\Controllers\Admin\Ai\AiCapabilityController::class, 'update'])->name('capability.update');
            Route::get('vorschau/{capability}', [\App\Http\Controllers\Admin\Ai\AiCapabilityController::class, 'preview'])->name('capability.preview');
            // Stammdaten nachbessern (MVP-493): ohne diesen Weg ließ sich ein
            // fehlendes Modell nur durch Löschen+Neuanlegen beheben.
            Route::get('{connection}/bearbeiten', [\App\Http\Controllers\Admin\Ai\AiConnectionController::class, 'edit'])->name('edit');
            Route::patch('{connection}', [\App\Http\Controllers\Admin\Ai\AiConnectionController::class, 'update'])->name('update');
            Route::post('{connection}/pruefen', [\App\Http\Controllers\Admin\Ai\AiConnectionController::class, 'test'])->name('test');
            Route::post('{connection}/sperren', [\App\Http\Controllers\Admin\Ai\AiConnectionController::class, 'block'])->name('block');
            Route::post('{connection}/entsperren', [\App\Http\Controllers\Admin\Ai\AiConnectionController::class, 'unblock'])->name('unblock');
            Route::post('{connection}/zugangsdaten', [\App\Http\Controllers\Admin\Ai\AiConnectionController::class, 'rotate'])->name('rotate');
            Route::delete('{connection}', [\App\Http\Controllers\Admin\Ai\AiConnectionController::class, 'destroy'])->name('destroy');
        });

        // ── Schreibfehler-Wörterbuch (Pflege, finance.config) ──
        Route::prefix('admin/woerterbuch')->name('admin.text-corrections.')->group(function (): void {
            Route::get('/', [\App\Http\Controllers\Admin\Invoicing\TextCorrectionController::class, 'index'])->name('index');
            // Statische Segmente VOR dem {correction}-Wildcard.
            Route::get('neu', [\App\Http\Controllers\Admin\Invoicing\TextCorrectionController::class, 'create'])->name('create');
            Route::post('/', [\App\Http\Controllers\Admin\Invoicing\TextCorrectionController::class, 'store'])->name('store');
            Route::get('{correction}/bearbeiten', [\App\Http\Controllers\Admin\Invoicing\TextCorrectionController::class, 'edit'])->name('edit');
            Route::patch('{correction}', [\App\Http\Controllers\Admin\Invoicing\TextCorrectionController::class, 'update'])->name('update');
            Route::post('{correction}/umschalten', [\App\Http\Controllers\Admin\Invoicing\TextCorrectionController::class, 'toggle'])->name('toggle');
            Route::delete('{correction}', [\App\Http\Controllers\Admin\Invoicing\TextCorrectionController::class, 'destroy'])->name('destroy');
        });

        // ── KI-Leistungstexte an Belegen (Feature 084, module.ai) ──
        // Gating via config/plans.routes: ai.suggestions.* → module.ai.
        Route::prefix('ki/vorschlaege')->name('ai.suggestions.')->group(function (): void {
            // Statische Segmente VOR dem {suggestion}-Wildcard.
            Route::post('rechnungen/{invoice}', [\App\Http\Controllers\Ai\AiSuggestionController::class, 'invoiceAll'])->name('invoice-all');
            Route::post('rechnungen/{invoice}/positionen/{item}', [\App\Http\Controllers\Ai\AiSuggestionController::class, 'invoiceItem'])->name('invoice-item');
            Route::get('rechnungen/{invoice}/positionen/{item}/uebersetzen', [\App\Http\Controllers\Ai\AiSuggestionController::class, 'invoiceItemTranslateForm'])->name('invoice-item-translate-form');
            Route::post('rechnungen/{invoice}/positionen/{item}/uebersetzen', [\App\Http\Controllers\Ai\AiSuggestionController::class, 'invoiceItemTranslate'])->name('invoice-item-translate');
            Route::post('angebote/{quote}/positionen/{item}', [\App\Http\Controllers\Ai\AiSuggestionController::class, 'quoteItem'])->name('quote-item');
            Route::post('merken', [\App\Http\Controllers\Ai\AiSuggestionController::class, 'learn'])->name('learn');
            Route::post('{suggestion}/uebernehmen', [\App\Http\Controllers\Ai\AiSuggestionController::class, 'accept'])->name('accept');
            Route::post('{suggestion}/verwerfen', [\App\Http\Controllers\Ai\AiSuggestionController::class, 'reject'])->name('reject');
        });

        // Wörterbuch-Lernen aus manuellen Belegtext-Korrekturen (bestätigter Dialog).
        Route::post('woerterbuch/merken', \App\Http\Controllers\Invoicing\TextCorrectionLearnController::class)->name('text-corrections.learn');

        Route::prefix('domains')->name('domains.')->group(function (): void {
            Route::get('/', [\App\Http\Controllers\Domain\DomainController::class, 'index'])->name('index');
            // Statische Segmente VOR dem {domain}-Wildcard.
            Route::post('verfuegbarkeit', [\App\Http\Controllers\Domain\DomainRegistrationController::class, 'check'])->name('availability');
            Route::post('registrieren', [\App\Http\Controllers\Domain\DomainRegistrationController::class, 'store'])->name('register');
            Route::get('accounting', [\App\Http\Controllers\Domain\DomainAccountingController::class, 'index'])
                ->middleware('can:domain.accounting.view')->name('accounting');
            Route::get('berichte', [\App\Http\Controllers\Reporting\DomainReportController::class, 'index'])
                ->middleware('can:domain.viewAny')->name('reports');
            Route::post('befehle/{command}/freigeben', [\App\Http\Controllers\Domain\DomainDangerousActionController::class, 'approve'])->name('commands.approve');
            Route::post('befehle/{command}/ablehnen', [\App\Http\Controllers\Domain\DomainDangerousActionController::class, 'reject'])->name('commands.reject');
            Route::get('{domain}', [\App\Http\Controllers\Domain\DomainController::class, 'show'])->name('show');
            Route::post('{domain}/abgleich', [\App\Http\Controllers\Domain\DomainController::class, 'refresh'])->name('refresh');
            Route::post('{domain}/kunde', [\App\Http\Controllers\Domain\DomainController::class, 'assignCustomer'])->name('customer');
            Route::post('{domain}/dns/lesen', [\App\Http\Controllers\Domain\DomainDnsController::class, 'read'])->name('dns.read');
            Route::post('{domain}/dns/ersetzen', [\App\Http\Controllers\Domain\DomainDnsController::class, 'replace'])->name('dns.replace');
            Route::post('{domain}/dns/aendern', [\App\Http\Controllers\Domain\DomainDnsController::class, 'modify'])->name('dns.modify');
            Route::post('{domain}/renewal-modus', [\App\Http\Controllers\Domain\DomainLifecycleController::class, 'renewalMode'])->name('renewal-mode');
            Route::post('{domain}/verlaengern', [\App\Http\Controllers\Domain\DomainLifecycleController::class, 'renew'])->name('renew');
            Route::post('{domain}/transfer-sperre', [\App\Http\Controllers\Domain\DomainLifecycleController::class, 'transferLock'])->name('transfer-lock');
            Route::post('{domain}/transfer-in', [\App\Http\Controllers\Domain\DomainLifecycleController::class, 'transferIn'])->name('transfer-in');
            Route::post('{domain}/hochrisiko', [\App\Http\Controllers\Domain\DomainDangerousActionController::class, 'requestAction'])->name('dangerous');
        });

        Route::prefix('domain-reseller')->name('domain-reseller.')->group(function (): void {
            Route::get('/', [\App\Http\Controllers\Domain\DomainResellerController::class, 'index'])->name('index');
            Route::get('{reseller}', [\App\Http\Controllers\Domain\DomainResellerController::class, 'show'])->name('show');
            Route::post('{reseller}/kunde', [\App\Http\Controllers\Domain\DomainResellerController::class, 'assignCustomer'])->name('customer');
            Route::post('{reseller}/domains-zuordnen', [\App\Http\Controllers\Domain\DomainResellerController::class, 'assignDomains'])->name('assign-domains');
        });

        // ── Geräte-/Maschinenverleih (Feature 073, module.rental) ───────
        Route::prefix('verleih')->name('rental.')->group(function (): void {
            Route::get('/', [\App\Http\Controllers\Rental\RentalCaseController::class, 'index'])->name('index');
            Route::get('neu', [\App\Http\Controllers\Rental\RentalCaseController::class, 'create'])->name('create');
            Route::post('/', [\App\Http\Controllers\Rental\RentalCaseController::class, 'store'])->name('store');
            Route::get('kalender', [\App\Http\Controllers\Rental\RentalCalendarController::class, 'index'])->name('calendar');
            Route::post('kalender/fenster', [\App\Http\Controllers\Rental\RentalCalendarController::class, 'store'])->name('reservations.store');
            Route::post('kalender/fenster/{reservation}/stornieren', [\App\Http\Controllers\Rental\RentalCalendarController::class, 'cancel'])->name('reservations.cancel');
            Route::get('geraetepool', [\App\Http\Controllers\Rental\RentalProfileController::class, 'index'])->name('profiles.index');
            Route::post('geraetepool', [\App\Http\Controllers\Rental\RentalProfileController::class, 'store'])->name('profiles.store');
            Route::put('geraetepool/{profile}', [\App\Http\Controllers\Rental\RentalProfileController::class, 'update'])->name('profiles.update');
            Route::get('preislisten', [\App\Http\Controllers\Rental\RentalRateCardController::class, 'index'])->name('rates.index');
            Route::post('preislisten', [\App\Http\Controllers\Rental\RentalRateCardController::class, 'store'])->name('rates.store');
            Route::post('preislisten/{rateCard}/aktivieren', [\App\Http\Controllers\Rental\RentalRateCardController::class, 'activate'])->name('rates.activate');
            Route::post('preislisten/{rateCard}/konditionen', [\App\Http\Controllers\Rental\RentalRateCardController::class, 'storeItem'])->name('rates.items.store');
            Route::delete('preislisten/{rateCard}/konditionen/{item}', [\App\Http\Controllers\Rental\RentalRateCardController::class, 'destroyItem'])->name('rates.items.destroy');
            Route::get('bericht', [\App\Http\Controllers\Reporting\RentalReportController::class, 'index'])->name('reports.index');
            Route::post('bericht/snapshot', [\App\Http\Controllers\Reporting\RentalReportController::class, 'snapshot'])->name('reports.snapshot');
            Route::get('{rental}', [\App\Http\Controllers\Rental\RentalCaseController::class, 'show'])->name('show');
            Route::put('{rental}', [\App\Http\Controllers\Rental\RentalCaseController::class, 'update'])->name('update');
            Route::post('{rental}/reservieren', [\App\Http\Controllers\Rental\RentalCaseController::class, 'reserve'])->name('reserve');
            Route::post('{rental}/verlaengern', [\App\Http\Controllers\Rental\RentalCaseController::class, 'extend'])->name('extend');
            Route::post('{rental}/tausch', [\App\Http\Controllers\Rental\RentalCaseController::class, 'swap'])->name('swap');
            Route::post('{rental}/stornieren', [\App\Http\Controllers\Rental\RentalCaseController::class, 'cancel'])->name('cancel');
            Route::post('{rental}/abschliessen', [\App\Http\Controllers\Rental\RentalCaseController::class, 'close'])->name('close');
            Route::post('{rental}/uebergabe', [\App\Http\Controllers\Rental\RentalHandoverController::class, 'handover'])->name('handover');
            Route::post('{rental}/ruecknahme', [\App\Http\Controllers\Rental\RentalHandoverController::class, 'return'])->name('return');
            Route::post('{rental}/positionen', [\App\Http\Controllers\Rental\RentalBillingController::class, 'storeCharge'])->name('charges.store');
            Route::post('{rental}/positionen/vorschlaege', [\App\Http\Controllers\Rental\RentalBillingController::class, 'applySuggestions'])->name('charges.suggest');
            Route::post('positionen/{charge}/freigeben', [\App\Http\Controllers\Rental\RentalBillingController::class, 'releaseCharge'])->name('charges.release');
            Route::post('positionen/{charge}/stornieren', [\App\Http\Controllers\Rental\RentalBillingController::class, 'cancelCharge'])->name('charges.cancel');
            Route::post('positionen/{charge}/belegnummer', [\App\Http\Controllers\Rental\RentalBillingController::class, 'externalReference'])->name('charges.reference');
            Route::post('{rental}/abrechnen', [\App\Http\Controllers\Rental\RentalBillingController::class, 'invoice'])->name('invoice');
            Route::post('{rental}/kaution', [\App\Http\Controllers\Rental\RentalBillingController::class, 'requestDeposit'])->name('deposits.store');
            Route::post('kaution/{deposit}/erhalten', [\App\Http\Controllers\Rental\RentalBillingController::class, 'receiveDeposit'])->name('deposits.receive');
            Route::post('kaution/{deposit}/abrechnen', [\App\Http\Controllers\Rental\RentalBillingController::class, 'settleDeposit'])->name('deposits.settle');
        });

        // ── Entsorgungsakten (Feature 100, module.entsorgung) ──────────────
        // Routen MÜSSEN disposal.* heißen (Modul-Gate in config/plans.php).
        Route::prefix('entsorgung')->name('disposal.')->group(function (): void {
            Route::get('/', [\App\Http\Controllers\Disposal\DisposalJobController::class, 'index'])->name('index');
            Route::get('neu', [\App\Http\Controllers\Disposal\DisposalJobController::class, 'create'])->name('create');
            Route::post('/', [\App\Http\Controllers\Disposal\DisposalJobController::class, 'store'])->name('store');
            Route::get('bericht', [\App\Http\Controllers\Reporting\DisposalReportController::class, 'index'])->name('reports.index');
            Route::get('{disposalJob}', [\App\Http\Controllers\Disposal\DisposalJobController::class, 'show'])->name('show');
            Route::get('{disposalJob}/bearbeiten', [\App\Http\Controllers\Disposal\DisposalJobController::class, 'edit'])->name('edit');
            Route::put('{disposalJob}', [\App\Http\Controllers\Disposal\DisposalJobController::class, 'update'])->name('update');
            Route::get('{disposalJob}/nachweis.pdf', [\App\Http\Controllers\Disposal\DisposalJobController::class, 'pdf'])->name('pdf');
            Route::post('{disposalJob}/abholen', [\App\Http\Controllers\Disposal\DisposalJobController::class, 'collect'])->name('collect');
            Route::post('{disposalJob}/behandlung', [\App\Http\Controllers\Disposal\DisposalJobController::class, 'startTreatment'])->name('treatment');
            Route::post('{disposalJob}/uebergeben', [\App\Http\Controllers\Disposal\DisposalJobController::class, 'markHandedOver'])->name('handed-over');
            Route::post('{disposalJob}/unterschreiben', [\App\Http\Controllers\Disposal\DisposalJobController::class, 'sign'])->name('sign');
            Route::post('{disposalJob}/abschliessen', [\App\Http\Controllers\Disposal\DisposalJobController::class, 'complete'])->name('complete');
            Route::post('{disposalJob}/stornieren', [\App\Http\Controllers\Disposal\DisposalJobController::class, 'cancel'])->name('cancel');
            Route::post('{disposalJob}/positionen', [\App\Http\Controllers\Disposal\DisposalItemController::class, 'store'])->name('items.store');
            Route::put('positionen/{disposalItem}', [\App\Http\Controllers\Disposal\DisposalItemController::class, 'update'])->name('items.update');
            Route::delete('positionen/{disposalItem}', [\App\Http\Controllers\Disposal\DisposalItemController::class, 'destroy'])->name('items.destroy');
            Route::post('positionen/{disposalItem}/behandlungen', [\App\Http\Controllers\Disposal\DisposalItemController::class, 'storeTreatment'])->name('treatments.store');
            Route::delete('behandlungen/{dataMediaTreatment}', [\App\Http\Controllers\Disposal\DisposalItemController::class, 'destroyTreatment'])->name('treatments.destroy');
            Route::post('{disposalJob}/uebergaben', [\App\Http\Controllers\Disposal\DisposalHandoverController::class, 'store'])->name('handovers.store');
            Route::delete('uebergaben/{disposalHandover}', [\App\Http\Controllers\Disposal\DisposalHandoverController::class, 'destroy'])->name('handovers.destroy');
        });

        // ── Leasing/Finanzierung/Asset-Verträge (Feature 074, module.asset_finance) ──
        Route::prefix('leasing')->name('asset-finance.')->group(function (): void {
            Route::get('/', [\App\Http\Controllers\AssetFinance\AssetFinanceContractController::class, 'index'])->name('index');
            Route::get('neu', [\App\Http\Controllers\AssetFinance\AssetFinanceContractController::class, 'create'])->name('create');
            Route::post('/', [\App\Http\Controllers\AssetFinance\AssetFinanceContractController::class, 'store'])->name('store');
            Route::get('fristen', [\App\Http\Controllers\AssetFinance\AssetFinanceOperationsController::class, 'deadlines'])->name('deadlines.index');
            Route::get('bericht', [\App\Http\Controllers\Reporting\AssetFinanceReportController::class, 'index'])->name('reports.index');
            Route::post('bericht/snapshot', [\App\Http\Controllers\Reporting\AssetFinanceReportController::class, 'snapshot'])->name('reports.snapshot');
            Route::get('{contract}', [\App\Http\Controllers\AssetFinance\AssetFinanceContractController::class, 'show'])->name('show');
            Route::put('{contract}', [\App\Http\Controllers\AssetFinance\AssetFinanceContractController::class, 'update'])->name('update');
            Route::post('{contract}/aktivieren', [\App\Http\Controllers\AssetFinance\AssetFinanceContractController::class, 'activate'])->name('activate');
            Route::post('{contract}/kuendigen', [\App\Http\Controllers\AssetFinance\AssetFinanceContractController::class, 'terminate'])->name('terminate');
            Route::post('{contract}/abschliessen', [\App\Http\Controllers\AssetFinance\AssetFinanceContractController::class, 'close'])->name('close');
            Route::post('{contract}/konditionen', [\App\Http\Controllers\AssetFinance\AssetFinanceContractController::class, 'storeTerm'])->name('terms.store');
            Route::post('{contract}/fristen', [\App\Http\Controllers\AssetFinance\AssetFinanceOperationsController::class, 'storeDeadline'])->name('deadlines.store');
            Route::post('fristen/{deadline}/erledigen', [\App\Http\Controllers\AssetFinance\AssetFinanceOperationsController::class, 'completeDeadline'])->name('deadlines.complete');
            Route::post('raten/{schedule}/referenz', [\App\Http\Controllers\AssetFinance\AssetFinanceOperationsController::class, 'linkSchedule'])->name('schedules.link');
            Route::post('{contract}/limits', [\App\Http\Controllers\AssetFinance\AssetFinanceOperationsController::class, 'storeUsageLimit'])->name('limits.store');
            Route::post('limits/{limit}/istwert', [\App\Http\Controllers\AssetFinance\AssetFinanceOperationsController::class, 'recordUsage'])->name('limits.record');
            Route::post('{contract}/optionen', [\App\Http\Controllers\AssetFinance\AssetFinanceOperationsController::class, 'storeOption'])->name('options.store');
            Route::post('optionen/{option}/ausueben', [\App\Http\Controllers\AssetFinance\AssetFinanceOperationsController::class, 'exerciseOption'])->name('options.exercise');
            Route::post('{contract}/ende', [\App\Http\Controllers\AssetFinance\AssetFinanceOperationsController::class, 'storeEndProcess'])->name('ends.store');
            Route::post('ende/{endProcess}/abschliessen', [\App\Http\Controllers\AssetFinance\AssetFinanceOperationsController::class, 'completeEndProcess'])->name('ends.complete');
            Route::post('{contract}/kosten-snapshot', [\App\Http\Controllers\Reporting\AssetFinanceReportController::class, 'costSnapshot'])->name('costs.snapshot');
        });

        // ── Allgemeine Vertragsverwaltung (Welle D CLM, module.contracts) ──
        Route::prefix('vertraege')->name('contracts.')->group(function (): void {
            Route::get('/', [\App\Http\Controllers\Contract\ContractController::class, 'index'])->name('index');
            Route::get('neu', [\App\Http\Controllers\Contract\ContractController::class, 'create'])->name('create');
            Route::post('/', [\App\Http\Controllers\Contract\ContractController::class, 'store'])->name('store');
            Route::get('{contract}', [\App\Http\Controllers\Contract\ContractController::class, 'show'])->name('show');
            Route::put('{contract}', [\App\Http\Controllers\Contract\ContractController::class, 'update'])->name('update');
            Route::post('{contract}/aktivieren', [\App\Http\Controllers\Contract\ContractController::class, 'activate'])->name('activate');
            Route::post('{contract}/kuendigen', [\App\Http\Controllers\Contract\ContractController::class, 'terminate'])->name('terminate');
            Route::post('{contract}/beenden', [\App\Http\Controllers\Contract\ContractController::class, 'end'])->name('end');
            Route::post('{contract}/obligationen', [\App\Http\Controllers\Contract\ContractController::class, 'storeObligation'])->name('obligations.store');
            Route::post('obligationen/{obligation}/erledigen', [\App\Http\Controllers\Contract\ContractController::class, 'completeObligation'])->name('obligations.complete');
            Route::post('{contract}/leasing-verknuepfen', [\App\Http\Controllers\Contract\ContractController::class, 'linkAssetFinance'])->name('asset-finance.link');
        });

        // ── Prüfmittel/Eichung/Kalibrierung (Feature 075, module.asset_compliance) ──
        Route::prefix('pruefmittel')->name('asset-compliance.')->group(function (): void {
            Route::get('/', [\App\Http\Controllers\AssetCompliance\AssetComplianceDashboardController::class, 'index'])->name('index');
            Route::post('sperren', [\App\Http\Controllers\AssetCompliance\AssetComplianceDashboardController::class, 'block'])->name('blocks.store');
            Route::post('sperren/{block}/aufheben', [\App\Http\Controllers\AssetCompliance\AssetComplianceDashboardController::class, 'release'])->name('blocks.release');
            Route::post('sperren/{block}/ausnahme', [\App\Http\Controllers\AssetCompliance\AssetComplianceDashboardController::class, 'grantException'])->name('blocks.exception');
            Route::post('ausnahmen/{exception}/widerrufen', [\App\Http\Controllers\AssetCompliance\AssetComplianceDashboardController::class, 'revokeException'])->name('blocks.exception.revoke');
            Route::get('profile', [\App\Http\Controllers\AssetCompliance\AssetComplianceProfileController::class, 'index'])->name('profiles.index');
            Route::post('profile', [\App\Http\Controllers\AssetCompliance\AssetComplianceProfileController::class, 'store'])->name('profiles.store');
            Route::post('profile/{profile}/anforderungen', [\App\Http\Controllers\AssetCompliance\AssetComplianceProfileController::class, 'storeRequirement'])->name('profiles.requirements.store');
            Route::post('profile/{profile}/zuweisen', [\App\Http\Controllers\AssetCompliance\AssetComplianceProfileController::class, 'assign'])->name('profiles.assign');
            Route::get('kalender', [\App\Http\Controllers\AssetCompliance\AssetInspectionController::class, 'index'])->name('schedules.index');
            Route::post('kalender', [\App\Http\Controllers\AssetCompliance\AssetInspectionController::class, 'storeSchedule'])->name('schedules.store');
            Route::post('pflichten/{assignment}/pruefen', [\App\Http\Controllers\AssetCompliance\AssetInspectionController::class, 'record'])->name('inspections.record');
            Route::get('bericht', [\App\Http\Controllers\Reporting\AssetComplianceReportController::class, 'index'])->name('reports.index');
            Route::post('bericht/snapshot', [\App\Http\Controllers\Reporting\AssetComplianceReportController::class, 'snapshot'])->name('reports.snapshot');
        });

        // ── Nachhaltigkeit/ESG (Feature 071, module.sustainability) ─────
        Route::prefix('nachhaltigkeit')->name('sustainability.')->group(function (): void {
            Route::get('/', [\App\Http\Controllers\Sustainability\SustainabilityController::class, 'index'])->name('index');
            Route::post('kriterien', [\App\Http\Controllers\Sustainability\SustainabilityController::class, 'storeCriterion'])->name('criteria.store');
            Route::post('aktivitaeten', [\App\Http\Controllers\Sustainability\SustainabilityController::class, 'storeActivity'])->name('activities.store');
            Route::post('faktoren', [\App\Http\Controllers\Sustainability\SustainabilityController::class, 'storeFactor'])->name('factors.store');
            Route::post('bewertungen', [\App\Http\Controllers\Sustainability\SustainabilityController::class, 'storeAssessment'])->name('assessments.store');
            Route::get('bewertungen/{assessment}', [\App\Http\Controllers\Sustainability\SustainabilityController::class, 'showAssessment'])->name('assessments.show');
            Route::put('bewertungen/{assessment}/kriterium/{item}', [\App\Http\Controllers\Sustainability\SustainabilityController::class, 'scoreItem'])->name('assessments.items.update');
            Route::post('bewertungen/{assessment}/finalisieren', [\App\Http\Controllers\Sustainability\SustainabilityController::class, 'finalizeAssessment'])->name('assessments.finalize');
            Route::post('bewertungen/{assessment}/neue-version', [\App\Http\Controllers\Sustainability\SustainabilityController::class, 'newAssessmentVersion'])->name('assessments.new-version');
            Route::post('massnahmen', [\App\Http\Controllers\Sustainability\SustainabilityController::class, 'storeMeasure'])->name('measures.store');
            Route::put('massnahmen/{measure}', [\App\Http\Controllers\Sustainability\SustainabilityController::class, 'updateMeasure'])->name('measures.update');
            Route::post('ziele', [\App\Http\Controllers\Sustainability\SustainabilityController::class, 'storeTarget'])->name('targets.store');
            Route::post('bericht/snapshot', [\App\Http\Controllers\Sustainability\SustainabilityController::class, 'storeSnapshot'])->name('snapshot.store');
        });

        // ── Notfall-/Krisenmanagement (Feature 070, module.crisis_management) ──
        Route::prefix('krisen')->name('crisis.')->group(function (): void {
            Route::get('/', [\App\Http\Controllers\Crisis\CrisisCaseController::class, 'index'])->name('index');
            Route::get('neu', [\App\Http\Controllers\Crisis\CrisisCaseController::class, 'create'])->name('create');
            Route::post('/', [\App\Http\Controllers\Crisis\CrisisCaseController::class, 'store'])->name('store');
            Route::post('stabsrollen', [\App\Http\Controllers\Crisis\CrisisCaseController::class, 'storeRole'])->name('roles.store');
            Route::get('uebungen', [\App\Http\Controllers\Crisis\CrisisExerciseController::class, 'index'])->name('exercises.index');
            Route::get('uebungen/neu', [\App\Http\Controllers\Crisis\CrisisExerciseController::class, 'create'])->name('exercises.create');
            Route::post('uebungen', [\App\Http\Controllers\Crisis\CrisisExerciseController::class, 'store'])->name('exercises.store');
            Route::get('uebungen/{exercise}/dokumentieren', [\App\Http\Controllers\Crisis\CrisisExerciseController::class, 'documentForm'])->name('exercises.document.form');
            Route::post('uebungen/{exercise}/dokumentieren', [\App\Http\Controllers\Crisis\CrisisExerciseController::class, 'document'])->name('exercises.document');
            Route::get('{case}', [\App\Http\Controllers\Crisis\CrisisCaseController::class, 'show'])->name('show');
            Route::post('{case}/status', [\App\Http\Controllers\Crisis\CrisisCaseController::class, 'updateStatus'])->name('status');
            Route::post('{case}/aktivieren', [\App\Http\Controllers\Crisis\CrisisCaseController::class, 'activate'])->name('activate');
            Route::post('{case}/entwarnen', [\App\Http\Controllers\Crisis\CrisisCaseController::class, 'allClear'])->name('all-clear');
            Route::post('{case}/schliessen', [\App\Http\Controllers\Crisis\CrisisCaseController::class, 'close'])->name('close');
            Route::post('{case}/stab', [\App\Http\Controllers\Crisis\CrisisCaseController::class, 'assignTeam'])->name('team.store');
            Route::delete('{case}/stab/{assignment}', [\App\Http\Controllers\Crisis\CrisisCaseController::class, 'removeTeam'])->name('team.destroy');
            Route::post('{case}/alarm', [\App\Http\Controllers\Crisis\CrisisCaseController::class, 'alert'])->name('alert');
            Route::post('{case}/alarm/eskalieren', [\App\Http\Controllers\Crisis\CrisisCaseController::class, 'escalateAlert'])->name('alert.escalate');
            Route::post('{case}/stab/{assignment}/quittieren', [\App\Http\Controllers\Crisis\CrisisCaseController::class, 'acknowledge'])->name('team.acknowledge');
            Route::post('{case}/lagebericht', [\App\Http\Controllers\Crisis\CrisisCaseController::class, 'storeSituationReport'])->name('sitrep.store');
            Route::post('{case}/entscheidungen', [\App\Http\Controllers\Crisis\CrisisCaseController::class, 'storeDecision'])->name('decisions.store');
            Route::post('{case}/massnahmen', [\App\Http\Controllers\Crisis\CrisisCaseController::class, 'storeAction'])->name('actions.store');
            Route::put('{case}/massnahmen/{action}', [\App\Http\Controllers\Crisis\CrisisCaseController::class, 'updateAction'])->name('actions.update');
            Route::post('{case}/kommunikation', [\App\Http\Controllers\Crisis\CrisisCaseController::class, 'storeCommunication'])->name('communications.store');
            Route::post('{case}/kommunikation/{communication}/freigeben', [\App\Http\Controllers\Crisis\CrisisCaseController::class, 'approveCommunication'])->name('communications.approve');
            Route::post('{case}/kommunikation/{communication}/gesendet', [\App\Http\Controllers\Crisis\CrisisCaseController::class, 'markCommunicationSent'])->name('communications.sent');
            Route::post('{case}/bcm', [\App\Http\Controllers\Crisis\CrisisCaseController::class, 'storeContinuityImpact'])->name('bcm.store');
            Route::put('{case}/bcm/{impact}', [\App\Http\Controllers\Crisis\CrisisCaseController::class, 'updateContinuityImpact'])->name('bcm.update');
            Route::post('{case}/verknuepfungen', [\App\Http\Controllers\Crisis\CrisisCaseController::class, 'addLink'])->name('links.store');
            Route::post('{case}/nachbereitung', [\App\Http\Controllers\Crisis\CrisisCaseController::class, 'storeReview'])->name('review.store');
        });

        // ── Investitionsplanung (Feature 069, module.investments) ─────
        Route::prefix('investitionen')->name('investments.')->group(function (): void {
            Route::get('/', [\App\Http\Controllers\Investments\InvestmentController::class, 'index'])->name('index');
            Route::get('neu', [\App\Http\Controllers\Investments\InvestmentController::class, 'create'])->name('create');
            Route::post('/', [\App\Http\Controllers\Investments\InvestmentController::class, 'store'])->name('store');
            Route::post('kostenstellen', [\App\Http\Controllers\Investments\InvestmentController::class, 'storeCostCenter'])->name('cost-centers.store');
            Route::get('bericht', [\App\Http\Controllers\Reporting\InvestmentsReportController::class, 'index'])->name('report');
            Route::get('{case}', [\App\Http\Controllers\Investments\InvestmentController::class, 'show'])->name('show');
            Route::get('{case}/bearbeiten', [\App\Http\Controllers\Investments\InvestmentController::class, 'edit'])->name('edit');
            Route::put('{case}', [\App\Http\Controllers\Investments\InvestmentController::class, 'update'])->name('update');
            Route::delete('{case}', [\App\Http\Controllers\Investments\InvestmentController::class, 'destroy'])->name('destroy');
            Route::post('{case}/status', [\App\Http\Controllers\Investments\InvestmentController::class, 'updateStatus'])->name('status');
            Route::post('{case}/varianten', [\App\Http\Controllers\Investments\InvestmentController::class, 'addOption'])->name('options.store');
            Route::post('{case}/varianten/{option}/empfehlen', [\App\Http\Controllers\Investments\InvestmentController::class, 'recommendOption'])->name('options.recommend');
            Route::delete('{case}/varianten/{option}', [\App\Http\Controllers\Investments\InvestmentController::class, 'removeOption'])->name('options.destroy');
            Route::post('{case}/budget', [\App\Http\Controllers\Investments\InvestmentController::class, 'submitBudget'])->name('budget.submit');
            Route::post('{case}/budget/{budgetRequest}/freigeben', [\App\Http\Controllers\Investments\InvestmentController::class, 'approveBudget'])->name('budget.approve');
            Route::post('{case}/budget/{budgetRequest}/ablehnen', [\App\Http\Controllers\Investments\InvestmentController::class, 'rejectBudget'])->name('budget.reject');
            Route::post('{case}/verknuepfungen', [\App\Http\Controllers\Investments\InvestmentController::class, 'addLink'])->name('links.store');
            Route::post('{case}/ist-werte', [\App\Http\Controllers\Investments\InvestmentController::class, 'addActual'])->name('actuals.store');
            Route::post('{case}/abweichungen', [\App\Http\Controllers\Investments\InvestmentController::class, 'addDeviation'])->name('deviations.store');
            Route::post('{case}/abweichungen/{deviation}/entscheiden', [\App\Http\Controllers\Investments\InvestmentController::class, 'decideDeviation'])->name('deviations.decide');
            Route::post('{case}/abweichungen/{deviation}/nachtrag', [\App\Http\Controllers\Investments\InvestmentController::class, 'supplementBudget'])->name('budget.supplement');
            Route::post('{case}/nachbewertung', [\App\Http\Controllers\Investments\InvestmentController::class, 'storeReview'])->name('review.store');
        });

        // ── Angebote (Feature 066, MVP-170) ───────────────────────────
        Route::prefix('angebote')->name('quotes.')->group(function (): void {
            // MVP-549: Bestandsroute → Feed mit vorgesetztem Tab.
            Route::get('/', [\App\Http\Controllers\Billing\DocumentFeedController::class, 'fromQuotes'])->name('index');
            Route::get('neu', [\App\Http\Controllers\QuoteController::class, 'create'])->name('create');
            Route::post('/', [\App\Http\Controllers\QuoteController::class, 'store'])->name('store');
            Route::get('{quote}', [\App\Http\Controllers\QuoteController::class, 'show'])->name('show');
            Route::delete('{quote}', [\App\Http\Controllers\QuoteController::class, 'destroy'])->name('destroy');
            // MVP-650: Angebots-PDF + Auftragsbestätigung (Design-Pipeline).
            Route::get('{quote}/pdf', [\App\Http\Controllers\QuoteController::class, 'pdf'])->name('pdf');
            Route::get('{quote}/auftragsbestaetigung', [\App\Http\Controllers\QuoteController::class, 'orderConfirmationPdf'])->name('order-confirmation');
            Route::post('{quote}/freigeben', [\App\Http\Controllers\QuoteController::class, 'approve'])->name('approve');
            Route::post('{quote}/versenden', [\App\Http\Controllers\QuoteController::class, 'send'])->name('send');
            Route::post('{quote}/entscheiden', [\App\Http\Controllers\QuoteController::class, 'decide'])->name('decide');
            Route::post('{quote}/neue-version', [\App\Http\Controllers\QuoteController::class, 'newVersion'])->name('new-version');
            Route::post('{quote}/ueberfuehren', [\App\Http\Controllers\QuoteController::class, 'convert'])->name('convert');
            Route::get('{quote}/positionen/neu', [\App\Http\Controllers\QuoteController::class, 'itemForm'])->name('items.create');
            Route::post('{quote}/positionen', [\App\Http\Controllers\QuoteController::class, 'addItem'])->name('items.store');
            Route::get('{quote}/positionen/{item}/bearbeiten', [\App\Http\Controllers\QuoteController::class, 'itemForm'])->name('items.edit');
            Route::put('{quote}/positionen/{item}', [\App\Http\Controllers\QuoteController::class, 'updateItem'])->name('items.update');
            Route::delete('{quote}/positionen/{item}', [\App\Http\Controllers\QuoteController::class, 'removeItem'])->name('items.destroy');
        });

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
            // Storno eines übergebenen Nachweises: gibt die Quellen wieder frei.
            Route::post('{transfer}/stornieren', [\App\Http\Controllers\Finance\FinanceTransferController::class, 'cancel'])->name('cancel');
            // Rechnungstexte des Belegs (MVP-491).
            Route::patch('{transfer}/texte', [\App\Http\Controllers\Finance\FinanceTransferController::class, 'updateTexts'])->name('texts.update');
            // Positions-Aktionen: entfernen, verschieben, zusammenfassen (MVP-492).
            Route::delete('{transfer}/positionen/{position}', [\App\Http\Controllers\Finance\TransferPositionController::class, 'destroy'])->name('positions.destroy');
            Route::post('{transfer}/positionen/{position}/verschieben', [\App\Http\Controllers\Finance\TransferPositionController::class, 'move'])->name('positions.move');
            Route::post('{transfer}/positionen/zusammenfassen', [\App\Http\Controllers\Finance\TransferPositionController::class, 'merge'])->name('positions.merge');
            // Korrektur-Übergabe zu einem übergebenen Nachweis (MVP-490).
            Route::post('{transfer}/korrigieren', [\App\Http\Controllers\Finance\FinanceTransferController::class, 'correct'])->name('correct');
            Route::get('{transfer}/download', [\App\Http\Controllers\Finance\FinanceTransferController::class, 'download'])->name('download');
            // Eingefrorene Positionen prüfen/bearbeiten (MVP-487/488).
            Route::patch('{transfer}/positionen/{position}', [\App\Http\Controllers\Finance\TransferPositionController::class, 'update'])->name('positions.update');
            Route::post('{transfer}/positionen/{position}/ki-text', [\App\Http\Controllers\Finance\TransferPositionController::class, 'suggest'])->name('positions.suggest');
            Route::post('{transfer}/positionen/ki-text', [\App\Http\Controllers\Finance\TransferPositionController::class, 'suggestAll'])->name('positions.suggest-all');
        });

        // ── Offene Zeiten (MVP-460): Buchhaltungs-Arbeitsliste unabgerechneter
        // Zeiten. Routen MÜSSEN finance.* heißen (Plan-Gating 'finance.*' →
        // module.finance). Sicht-Gate: timeEntry.viewAny (im Controller).
        Route::prefix('finanzen/offene-zeiten')->name('finance.open-times.')->group(function (): void {
            Route::get('/', [\App\Http\Controllers\Finance\OpenTimesController::class, 'index'])->name('index');
            Route::get('export', [\App\Http\Controllers\Finance\OpenTimesController::class, 'export'])->name('export');
            // Altbestand-Abschluss: offene Zeiten bis Stichtag als abgerechnet markieren.
            Route::get('abgerechnet-markieren', [\App\Http\Controllers\Finance\OpenTimesController::class, 'markBilledDialog'])->name('mark-billed-dialog');
            Route::post('abgerechnet-markieren', [\App\Http\Controllers\Finance\OpenTimesController::class, 'markBilled'])->name('mark-billed');
        });

        // ── Zahlungsabgleich (Feature 045, Priorität 3 / Phase 4) ───────────────
        // Routen MÜSSEN finance.* heißen (Plan-Gating 'finance.*' → module.finance).
        // Autorisierung über die Bank*-Policies (finance.payment.import/reconcile).
        Route::prefix('finanzen/zahlungsabgleich')->name('finance.reconciliation.')->group(function (): void {
            Route::get('/', [\App\Http\Controllers\Finance\PaymentReconciliationController::class, 'index'])->name('index');
            Route::get('import', [\App\Http\Controllers\Finance\PaymentReconciliationController::class, 'create'])->name('create');
            Route::post('import', [\App\Http\Controllers\Finance\PaymentReconciliationController::class, 'upload'])->name('upload');
            Route::get('{statement}', [\App\Http\Controllers\Finance\PaymentReconciliationController::class, 'show'])->name('show');
            Route::get('{statement}/download', [\App\Http\Controllers\Finance\PaymentReconciliationController::class, 'download'])->name('download');
            Route::post('umsatz/{transaction}/bestaetigen', [\App\Http\Controllers\Finance\PaymentReconciliationController::class, 'confirm'])->name('confirm');
            Route::post('umsatz/{transaction}/ignorieren', [\App\Http\Controllers\Finance\PaymentReconciliationController::class, 'ignore'])->name('ignore');
            Route::post('umsatz/{transaction}/nicht-zuordenbar', [\App\Http\Controllers\Finance\PaymentReconciliationController::class, 'unassignable'])->name('unassignable');
            // Lastschrift-Rückläufer (MVP-334): Original-Zuordnung GoBD-konform kompensieren.
            Route::post('umsatz/{transaction}/ruecklaeufer', [\App\Http\Controllers\Finance\PaymentReconciliationController::class, 'processReturn'])->name('return');
            Route::delete('zuordnung/{allocation}', [\App\Http\Controllers\Finance\PaymentReconciliationController::class, 'unmatch'])->name('unmatch');
        });

        // ── Eigene Bankkonten (Feature 045, finance.config) ─────────────────────
        Route::prefix('finanzen/bankkonten')->name('finance.bank-accounts.')->group(function (): void {
            Route::get('/', [\App\Http\Controllers\Finance\BankAccountController::class, 'index'])->name('index');
            Route::get('neu', [\App\Http\Controllers\Finance\BankAccountController::class, 'create'])->name('create');
            Route::post('/', [\App\Http\Controllers\Finance\BankAccountController::class, 'store'])->name('store');
            Route::get('{bankAccount}/bearbeiten', [\App\Http\Controllers\Finance\BankAccountController::class, 'edit'])->name('edit');
            Route::put('{bankAccount}', [\App\Http\Controllers\Finance\BankAccountController::class, 'update'])->name('update');
            Route::delete('{bankAccount}', [\App\Http\Controllers\Finance\BankAccountController::class, 'destroy'])->name('destroy');
        });

        // ── DATEV-Buchungsstapel (Feature 045, Priorität 2 / Phase 3) ───────────
        // Routen MÜSSEN finance.* heißen (Plan-Gating 'finance.*' → module.finance).
        // Autorisierung über DatevBookingBatchPolicy (finance.booking.export;
        // Konfiguration über finance.config).
        Route::prefix('finanzen/datev-buchungen')->name('finance.datev.')->group(function (): void {
            Route::get('/', [\App\Http\Controllers\Finance\DatevBookingController::class, 'index'])->name('index');
            Route::get('konfiguration', [\App\Http\Controllers\Finance\DatevBookingController::class, 'editConfig'])->name('config');
            Route::put('konfiguration', [\App\Http\Controllers\Finance\DatevBookingController::class, 'updateConfig'])->name('config.update');
            Route::get('neu', [\App\Http\Controllers\Finance\DatevBookingController::class, 'create'])->name('create');
            Route::post('/', [\App\Http\Controllers\Finance\DatevBookingController::class, 'store'])->name('store');
            // EXTF-Stammdatenexport Kategorie 16 (Nachtrag 045a): Debitoren aus dem Kundenstamm.
            Route::post('stammdaten/debitoren', [\App\Http\Controllers\Finance\DatevBookingController::class, 'exportDebtors'])
                ->middleware('throttle:6,1')
                ->name('debtors.export');
            // EXTF-Sachkonten-Beistellung Kategorie 20 (MVP-334): verwendete Sachkonten mit Beschriftung.
            Route::post('stammdaten/sachkonten', [\App\Http\Controllers\Finance\DatevBookingController::class, 'exportGlAccounts'])
                ->middleware('throttle:6,1')
                ->name('gl-accounts.export');
            Route::get('{batch}', [\App\Http\Controllers\Finance\DatevBookingController::class, 'show'])->name('show');
            Route::post('{batch}/finalisieren', [\App\Http\Controllers\Finance\DatevBookingController::class, 'finalize'])->name('finalize');
            Route::get('{batch}/download', [\App\Http\Controllers\Finance\DatevBookingController::class, 'download'])->name('download');
            // Teilauswahl/mehrere Stapel (MVP-334): Zuschnitt am Draft ändern bzw. Draft verwerfen.
            Route::post('{batch}/quellen-entfernen', [\App\Http\Controllers\Finance\DatevBookingController::class, 'removeSources'])->name('sources.remove');
            Route::delete('{batch}', [\App\Http\Controllers\Finance\DatevBookingController::class, 'destroy'])->name('destroy');
        });
        // Eingangs-E-Rechnung (Nachtrag 045b): XRechnung/ZUGFeRD empfangen,
        // visualisieren und als Document ablegen — keine lokale Invoice.
        Route::prefix('finanzen/eingangsrechnungen')->name('finance.incoming-invoices.')->group(function (): void {
            Route::get('/', [\App\Http\Controllers\Finance\IncomingInvoiceController::class, 'index'])->name('index');
            Route::post('/', [\App\Http\Controllers\Finance\IncomingInvoiceController::class, 'store'])->name('store');
            Route::post('{incoming}/entscheiden', [\App\Http\Controllers\Finance\IncomingInvoiceController::class, 'decide'])->name('decide');
            Route::post('{incoming}/uebergeben', [\App\Http\Controllers\Finance\IncomingInvoiceController::class, 'transfer'])->name('transfer');
            Route::get('{document}/xml', [\App\Http\Controllers\Finance\IncomingInvoiceController::class, 'xml'])->name('xml');
            Route::get('{document}', [\App\Http\Controllers\Finance\IncomingInvoiceController::class, 'show'])->name('show');
        });
        // Steuerregelmatrix (Phase 23, MVP-242) — module.finance über finance.*;
        // Recht finance.config wird im Controller geprüft.
        Route::prefix('finanzen/steuerregeln')->name('finance.tax-rules.')->group(function (): void {
            Route::get('/', [\App\Http\Controllers\Finance\TaxRuleController::class, 'index'])->name('index');
            Route::post('/', [\App\Http\Controllers\Finance\TaxRuleController::class, 'store'])->name('store');
            Route::post('{rule}/stilllegen', [\App\Http\Controllers\Finance\TaxRuleController::class, 'retire'])->name('retire');
            Route::post('import', [\App\Http\Controllers\Finance\TaxRuleController::class, 'import'])->name('import');
        });
        // GoBD-Z3-Datenträgerüberlassung (Feature 063, MVP-132) — module.finance über finance.*
        Route::prefix('finanzen/gobd')->name('finance.gobd.')->group(function (): void {
            Route::get('/', [\App\Http\Controllers\Finance\GobdExportController::class, 'index'])->name('index');
            Route::get('check', [\App\Http\Controllers\Finance\GobdExportController::class, 'check'])->name('check');
            Route::post('export', [\App\Http\Controllers\Finance\GobdExportController::class, 'export'])->name('export');
        });
        Route::patch('projects/{project}/tasks/{task}/complete', [TaskController::class, 'complete'])->name('projects.tasks.complete');
        Route::get('time-entries/create', [TimeEntryController::class, 'pick'])->name('time-entries.create');
        // Massen-Neuzuordnung (MVP-508) — vor der Resource, damit „reassign"
        // nie als {time_entry}-Parameter gelesen wird.
        Route::get('projects/{project}/time-entries/reassign', [TimeEntryController::class, 'reassignDialog'])->name('projects.time-entries.reassign-dialog');
        Route::post('projects/{project}/time-entries/reassign', [TimeEntryController::class, 'reassign'])->name('projects.time-entries.reassign');
        // Portal-Veröffentlichung einzelner Zeiten (MVP-511).
        Route::post('projects/{project}/time-entries/portal-visibility', [TimeEntryController::class, 'updatePortalVisibility'])->name('projects.time-entries.portal-visibility');
        Route::resource('projects.time-entries', TimeEntryController::class)->except(['index', 'show']);
        // Zeitaufteilung (Feature 103, MVP-514): Anteile eines Zeiteintrags auf Dimensionen.
        Route::get('time-entries/{timeEntry}/allocations', [\App\Http\Controllers\TimeAllocationController::class, 'edit'])->name('time-entries.allocations.edit');
        Route::put('time-entries/{timeEntry}/allocations', [\App\Http\Controllers\TimeAllocationController::class, 'update'])->name('time-entries.allocations.update');
        Route::resource('projects.billing-rules', ProjectBillingRuleController::class)->except(['index', 'show', 'edit']);
        Route::patch('projects/{project}/billing-settings', [ProjectBillingRuleController::class, 'updateSettings'])->name('projects.billing-settings.update');
        // Projektstufe der Satzhierarchie (MVP-482) — eigene Route, damit das
        // Taktungsformular die Sätze nicht mit leeren Werten überschreibt.
        Route::patch('projects/{project}/rates', [ProjectBillingRuleController::class, 'updateRates'])->name('projects.rates.update');

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
        // Magic-Link widerrufen (Feature 012 MVP; Vollaudit 2026-07, M6).
        Route::delete('projects/{project}/timesheets/{timesheet}/magic-link', [TimesheetSignatureController::class, 'revokeMagicLink'])->name('projects.timesheets.magic-link.revoke');

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
        // Zwischen-Status Homeoffice/Dienstgang (Feature 103, MVP-532).
        Route::post('attendance/intermediate', [AttendanceController::class, 'intermediate'])->name('attendance.intermediate');
        Route::post('attendance/cancel', [AttendanceController::class, 'cancel'])->name('attendance.cancel');
        Route::put('attendance/{attendance}', [AttendanceController::class, 'update'])->name('attendance.update');
        Route::delete('attendance/{attendance}', [AttendanceController::class, 'destroy'])->name('attendance.destroy');

        // ── Tages-Dashboard ───────────────────────────────────────────────────
        Route::get('today', [TodayController::class, 'show'])->name('today.show');
        // Quick-Buchung offener Blöcke → Projekt (MVP-015, Rang 37).
        Route::post('today/quick-book', [QuickBookController::class, 'store'])->name('today.quick-book');
        // Eingabeleiste (Toggl-artig): manuelle Buchung + projektabhängige Optionen.
        Route::post('today/entry-bar', [TimeEntryBarController::class, 'store'])->name('today.entry-bar.store');
        Route::get('today/entry-bar/{project}/options', [TimeEntryBarController::class, 'options'])->name('today.entry-bar.options');

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
        // Belegdatei zur Auslage (Feature 105, MVP-550)
        Route::get('expenses/{expense}/beleg', [ExpenseController::class, 'receipt'])->name('expenses.receipt');
        // Zuordnung Auslage ↔ Buchhaltungsbeleg (Feature 105, MVP-551)
        Route::post('expenses/{expense}/buchungsbeleg', [ExpenseController::class, 'linkVoucher'])->name('expenses.link-voucher');
        Route::delete('expenses/{expense}/buchungsbeleg', [ExpenseController::class, 'unlinkVoucher'])->name('expenses.unlink-voucher');
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

        // ── Sicherheitsereignis-Register (Arbeitsschutz, Feature 013) ──────
        Route::get('safety-events', [SafetyEventController::class, 'index'])->name('safety-events.index');
        Route::get('safety-events/create', [SafetyEventController::class, 'create'])->name('safety-events.create');
        Route::post('safety-events', [SafetyEventController::class, 'store'])->name('safety-events.store');
        Route::get('safety-events/{safety_event}', [SafetyEventController::class, 'show'])->name('safety-events.show');
        Route::get('safety-events/{safety_event}/edit', [SafetyEventController::class, 'edit'])->name('safety-events.edit');
        Route::put('safety-events/{safety_event}', [SafetyEventController::class, 'update'])->name('safety-events.update');
        Route::post('safety-events/{safety_event}/transition', [SafetyEventController::class, 'transition'])->name('safety-events.transition');
        Route::post('safety-events/{safety_event}/follow-up', [SafetyEventController::class, 'followUp'])->name('safety-events.follow-up');
        Route::delete('safety-events/{safety_event}', [SafetyEventController::class, 'destroy'])->name('safety-events.destroy');

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
        // Detailseite (Rang 28) — nach documents/create registriert, damit das Literal zuerst matcht.
        Route::get('documents/{document}', [\App\Http\Controllers\DocumentController::class, 'show'])->name('documents.show');
        Route::get('documents/{document}/edit', [\App\Http\Controllers\DocumentController::class, 'edit'])->name('documents.edit');
        Route::put('documents/{document}', [\App\Http\Controllers\DocumentController::class, 'update'])->name('documents.update');
        Route::get('documents/{document}/versions', [\App\Http\Controllers\DocumentController::class, 'versions'])->name('documents.versions');
        Route::post('documents/{document}/versions', [\App\Http\Controllers\DocumentController::class, 'addVersion'])->name('documents.versions.store');
        Route::get('documents/{document}/download/{version?}', [\App\Http\Controllers\DocumentController::class, 'download'])->name('documents.download');
        Route::post('documents/{document}/archive', [\App\Http\Controllers\DocumentController::class, 'archive'])->name('documents.archive');
        // Kundenfreigabe fürs Kundenportal (Welle D — Dokument-Spiegelung).
        Route::post('documents/{document}/customer-release', [\App\Http\Controllers\DocumentController::class, 'release'])->name('documents.customer-release');
        Route::post('documents/{document}/customer-revoke', [\App\Http\Controllers\DocumentController::class, 'revoke'])->name('documents.customer-revoke');
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

        // ── Ideenlandkarten (Feature 054, MVP-104/105) ─ Gate ideas.* → module.ideas
        Route::get('ideas', [\App\Http\Controllers\IdeaMapController::class, 'index'])->name('ideas.index');
        Route::get('ideas/create', [\App\Http\Controllers\IdeaMapController::class, 'create'])->name('ideas.create');
        Route::post('ideas', [\App\Http\Controllers\IdeaMapController::class, 'store'])->name('ideas.store');
        Route::post('ideas/import', [\App\Http\Controllers\IdeaMapController::class, 'import'])->name('ideas.import'); // MVP-138 FreeMind/OPML
        Route::get('ideas/{map}', [\App\Http\Controllers\IdeaMapController::class, 'show'])->name('ideas.show');
        Route::get('ideas/{map}/edit', [\App\Http\Controllers\IdeaMapController::class, 'edit'])->name('ideas.edit');
        Route::put('ideas/{map}', [\App\Http\Controllers\IdeaMapController::class, 'update'])->name('ideas.update');
        Route::post('ideas/{map}/archive', [\App\Http\Controllers\IdeaMapController::class, 'archive'])->name('ideas.archive');
        Route::post('ideas/{map}/unarchive', [\App\Http\Controllers\IdeaMapController::class, 'unarchive'])->name('ideas.unarchive');
        Route::post('ideas/{map}/transfer-ownership', [\App\Http\Controllers\IdeaMapController::class, 'transferOwnership'])->name('ideas.transfer-ownership'); // manageLifecycle (Austritt)
        Route::post('ideas/{map}/shares', [\App\Http\Controllers\IdeaMapController::class, 'storeShare'])->name('ideas.shares.store'); // MVP-107 Freigaben
        Route::delete('ideas/{map}/shares/{share}', [\App\Http\Controllers\IdeaMapController::class, 'destroyShare'])->name('ideas.shares.destroy');
        // Knotenbezogene Editor-API (MVP-106/108): kleine JSON-Operationen, nie „ganze Karte speichern".
        Route::get('ideas/{map}/tree', [\App\Http\Controllers\IdeaNodeController::class, 'tree'])->name('ideas.maps.tree');
        Route::post('ideas/{map}/sync', [\App\Http\Controllers\IdeaNodeController::class, 'sync'])->name('ideas.maps.sync'); // MVP-136 Whole-Map-Sync (Canvas)
        Route::get('ideas/{map}/export.json', [\App\Http\Controllers\IdeaMapController::class, 'exportJson'])->name('ideas.export.json'); // MVP-110
        Route::get('ideas/{map}/export.pdf', [\App\Http\Controllers\IdeaMapController::class, 'exportPdf'])->name('ideas.export.pdf'); // MVP-110
        Route::get('ideas/{map}/export.opml', [\App\Http\Controllers\IdeaMapController::class, 'exportOpml'])->name('ideas.export.opml'); // MVP-138
        Route::get('ideas/{map}/export.md', [\App\Http\Controllers\IdeaMapController::class, 'exportMarkdown'])->name('ideas.export.md'); // MVP-138
        Route::post('ideas/{map}/presence', [\App\Http\Controllers\IdeaMapController::class, 'presence'])->name('ideas.maps.presence'); // MVP-108 Bearbeitungspräsenz
        Route::get('ideas/{map}/history', [\App\Http\Controllers\IdeaMapController::class, 'history'])->name('ideas.maps.history'); // MVP-108 Änderungsverlauf
        Route::post('ideas/{map}/nodes', [\App\Http\Controllers\IdeaNodeController::class, 'store'])->name('ideas.nodes.store');
        Route::patch('ideas/{map}/nodes/{node}', [\App\Http\Controllers\IdeaNodeController::class, 'update'])->name('ideas.nodes.update');
        Route::post('ideas/{map}/nodes/{node}/move', [\App\Http\Controllers\IdeaNodeController::class, 'move'])->name('ideas.nodes.move');
        Route::post('ideas/{map}/nodes/{node}/reorder', [\App\Http\Controllers\IdeaNodeController::class, 'reorder'])->name('ideas.nodes.reorder');
        Route::post('ideas/{map}/nodes/{nodeSqid}/restore', [\App\Http\Controllers\IdeaNodeController::class, 'restore'])->name('ideas.nodes.restore');
        Route::post('ideas/{map}/nodes/{node}/convert', [\App\Http\Controllers\IdeaNodeController::class, 'convert'])->name('ideas.nodes.convert'); // MVP-109 Überführung
        Route::post('ideas/{map}/nodes/{node}/link', [\App\Http\Controllers\IdeaNodeController::class, 'link'])->name('ideas.nodes.link');
        Route::delete('ideas/{map}/nodes/{node}', [\App\Http\Controllers\IdeaNodeController::class, 'destroy'])->name('ideas.nodes.destroy');
        Route::post('ideas/{mapSqid}/restore', [\App\Http\Controllers\IdeaMapController::class, 'restore'])->name('ideas.restore'); // manuelles Sqid-Decoding (SoftDeleted bindet nicht implizit)
        Route::delete('ideas/{map}', [\App\Http\Controllers\IdeaMapController::class, 'destroy'])->name('ideas.destroy');

        // ── Vorlagen- & Formularsystem (Feature 032) ───────────────────────
        Route::get('form-templates', [\App\Http\Controllers\FormTemplateController::class, 'index'])->name('form-templates.index');
        Route::get('form-templates/create', [\App\Http\Controllers\FormTemplateController::class, 'create'])->name('form-templates.create');
        Route::post('form-templates', [\App\Http\Controllers\FormTemplateController::class, 'store'])->name('form-templates.store');
        Route::get('form-templates/{template}/edit', [\App\Http\Controllers\FormTemplateController::class, 'edit'])->name('form-templates.edit');
        Route::put('form-templates/{template}', [\App\Http\Controllers\FormTemplateController::class, 'update'])->name('form-templates.update');
        Route::post('form-templates/{template}/activate', [\App\Http\Controllers\FormTemplateController::class, 'activate'])->name('form-templates.activate');
        Route::post('form-templates/{template}/archive', [\App\Http\Controllers\FormTemplateController::class, 'archive'])->name('form-templates.archive');
        Route::delete('form-templates/{template}', [\App\Http\Controllers\FormTemplateController::class, 'destroy'])->name('form-templates.destroy');

        // ── Prozeduren / Arbeitsanweisungen (Feature 026) ──────────────────
        Route::get('procedures', [\App\Http\Controllers\ProcedureTemplateController::class, 'index'])->name('procedures.index');
        Route::get('procedures/create', [\App\Http\Controllers\ProcedureTemplateController::class, 'create'])->name('procedures.create');
        Route::post('procedures', [\App\Http\Controllers\ProcedureTemplateController::class, 'store'])->name('procedures.store');
        Route::get('procedures/{template}/edit', [\App\Http\Controllers\ProcedureTemplateController::class, 'edit'])->name('procedures.edit');
        Route::put('procedures/{template}', [\App\Http\Controllers\ProcedureTemplateController::class, 'update'])->name('procedures.update');
        Route::post('procedures/{template}/versions', [\App\Http\Controllers\ProcedureTemplateController::class, 'storeVersion'])->name('procedures.versions.store');
        Route::post('procedures/{template}/versions/{version}/publish', [\App\Http\Controllers\ProcedureTemplateController::class, 'publish'])->name('procedures.versions.publish');
        Route::post('procedures/{template}/activate', [\App\Http\Controllers\ProcedureTemplateController::class, 'activate'])->name('procedures.activate');
        Route::post('procedures/{template}/archive', [\App\Http\Controllers\ProcedureTemplateController::class, 'archive'])->name('procedures.archive');

        // ── Rezeptpflege (MVP-455): Materialpositionen nur am Draft; Partyservice-
        // Aufsatz (Profil/Allergene) nur bei installiertem Branchenprofil. ──
        Route::post('procedures/{template}/versions/{version}/materials', [\App\Http\Controllers\Recipes\RecipeController::class, 'storeMaterial'])->name('procedures.materials.store');
        Route::delete('procedures/{template}/versions/{version}/materials/{requirement}', [\App\Http\Controllers\Recipes\RecipeController::class, 'destroyMaterial'])->name('procedures.materials.destroy');
        Route::post('procedures/{template}/versions/{version}/recipe-profile', [\App\Http\Controllers\Recipes\RecipeController::class, 'saveProfile'])->name('procedures.recipe-profile.save');
        Route::post('procedures/{template}/versions/{version}/ingredient-allergens/{article}', [\App\Http\Controllers\Recipes\RecipeController::class, 'saveIngredientAllergens'])->name('procedures.ingredient-allergens.save');

        // ── Menü-/Buffetplanung (MVP-455, Partyservice, module.lager) ──
        Route::get('recipe-menus', [\App\Http\Controllers\Recipes\RecipeMenuController::class, 'index'])->name('recipe-menus.index');
        Route::post('recipe-menus', [\App\Http\Controllers\Recipes\RecipeMenuController::class, 'store'])->name('recipe-menus.store');
        Route::get('recipe-menus/{menu}', [\App\Http\Controllers\Recipes\RecipeMenuController::class, 'show'])->name('recipe-menus.show');
        Route::post('recipe-menus/{menu}/items', [\App\Http\Controllers\Recipes\RecipeMenuController::class, 'storeItem'])->name('recipe-menus.items.store');
        Route::delete('recipe-menus/{menu}/items/{item}', [\App\Http\Controllers\Recipes\RecipeMenuController::class, 'destroyItem'])->name('recipe-menus.items.destroy');

        // ── Personenbeförderung (MVP-456, Branchenprofil taxi-mietwagen):
        // Fahrtakten mit Pflichtgates, Stammdaten (Tarife/Konzessionen/
        // Fahrzeugprofile) und Schichtabrechnung. Profil-Gate: 404 im
        // Controller (Muster Recipes). ──
        Route::get('passenger-rides', [\App\Http\Controllers\Passenger\PassengerRideController::class, 'index'])->name('passenger-rides.index');
        Route::get('passenger-rides/create', [\App\Http\Controllers\Passenger\PassengerRideController::class, 'create'])->name('passenger-rides.create');
        Route::post('passenger-rides', [\App\Http\Controllers\Passenger\PassengerRideController::class, 'store'])->name('passenger-rides.store');
        Route::get('passenger-rides/{ride}', [\App\Http\Controllers\Passenger\PassengerRideController::class, 'show'])->name('passenger-rides.show');
        Route::post('passenger-rides/{ride}/assign', [\App\Http\Controllers\Passenger\PassengerRideController::class, 'assign'])->name('passenger-rides.assign');
        Route::post('passenger-rides/{ride}/start', [\App\Http\Controllers\Passenger\PassengerRideController::class, 'start'])->name('passenger-rides.start');
        Route::post('passenger-rides/{ride}/transition', [\App\Http\Controllers\Passenger\PassengerRideController::class, 'transition'])->name('passenger-rides.transition');
        Route::post('passenger-rides/{ride}/complete', [\App\Http\Controllers\Passenger\PassengerRideController::class, 'complete'])->name('passenger-rides.complete');
        Route::post('passenger-rides/{ride}/close', [\App\Http\Controllers\Passenger\PassengerRideController::class, 'close'])->name('passenger-rides.close');
        Route::post('passenger-rides/{ride}/return', [\App\Http\Controllers\Passenger\PassengerRideController::class, 'recordReturn'])->name('passenger-rides.return');

        Route::get('passenger-masterdata', [\App\Http\Controllers\Passenger\PassengerMasterDataController::class, 'index'])->name('passenger-masterdata.index');
        Route::get('passenger-masterdata/tariffs/create', [\App\Http\Controllers\Passenger\PassengerMasterDataController::class, 'createTariff'])->name('passenger-masterdata.tariffs.create');
        Route::post('passenger-masterdata/tariffs', [\App\Http\Controllers\Passenger\PassengerMasterDataController::class, 'storeTariff'])->name('passenger-masterdata.tariffs.store');
        Route::get('passenger-masterdata/tariffs/{tariff}/edit', [\App\Http\Controllers\Passenger\PassengerMasterDataController::class, 'editTariff'])->name('passenger-masterdata.tariffs.edit');
        Route::put('passenger-masterdata/tariffs/{tariff}', [\App\Http\Controllers\Passenger\PassengerMasterDataController::class, 'updateTariff'])->name('passenger-masterdata.tariffs.update');
        Route::post('passenger-masterdata/tariffs/{tariff}/rules', [\App\Http\Controllers\Passenger\PassengerMasterDataController::class, 'storeTariffRule'])->name('passenger-masterdata.tariffs.rules.store');
        Route::delete('passenger-masterdata/tariffs/{tariff}/rules/{rule}', [\App\Http\Controllers\Passenger\PassengerMasterDataController::class, 'destroyTariffRule'])->name('passenger-masterdata.tariffs.rules.destroy');
        Route::get('passenger-masterdata/concessions/create', [\App\Http\Controllers\Passenger\PassengerMasterDataController::class, 'createConcession'])->name('passenger-masterdata.concessions.create');
        Route::post('passenger-masterdata/concessions', [\App\Http\Controllers\Passenger\PassengerMasterDataController::class, 'storeConcession'])->name('passenger-masterdata.concessions.store');
        Route::get('passenger-masterdata/concessions/{concession}/edit', [\App\Http\Controllers\Passenger\PassengerMasterDataController::class, 'editConcession'])->name('passenger-masterdata.concessions.edit');
        Route::put('passenger-masterdata/concessions/{concession}', [\App\Http\Controllers\Passenger\PassengerMasterDataController::class, 'updateConcession'])->name('passenger-masterdata.concessions.update');
        Route::get('passenger-masterdata/vehicle-profiles/create', [\App\Http\Controllers\Passenger\PassengerMasterDataController::class, 'createVehicleProfile'])->name('passenger-masterdata.vehicle-profiles.create');
        Route::post('passenger-masterdata/vehicle-profiles', [\App\Http\Controllers\Passenger\PassengerMasterDataController::class, 'storeVehicleProfile'])->name('passenger-masterdata.vehicle-profiles.store');
        Route::get('passenger-masterdata/vehicle-profiles/{profile}/edit', [\App\Http\Controllers\Passenger\PassengerMasterDataController::class, 'editVehicleProfile'])->name('passenger-masterdata.vehicle-profiles.edit');
        Route::put('passenger-masterdata/vehicle-profiles/{profile}', [\App\Http\Controllers\Passenger\PassengerMasterDataController::class, 'updateVehicleProfile'])->name('passenger-masterdata.vehicle-profiles.update');

        Route::get('passenger-settlements', [\App\Http\Controllers\Passenger\PassengerSettlementController::class, 'index'])->name('passenger-settlements.index');
        Route::get('passenger-settlements/create', [\App\Http\Controllers\Passenger\PassengerSettlementController::class, 'create'])->name('passenger-settlements.create');
        Route::post('passenger-settlements', [\App\Http\Controllers\Passenger\PassengerSettlementController::class, 'store'])->name('passenger-settlements.store');
        Route::get('passenger-settlements/{settlement}/edit', [\App\Http\Controllers\Passenger\PassengerSettlementController::class, 'edit'])->name('passenger-settlements.edit');
        Route::put('passenger-settlements/{settlement}', [\App\Http\Controllers\Passenger\PassengerSettlementController::class, 'update'])->name('passenger-settlements.update');
        Route::post('passenger-settlements/{settlement}/close', [\App\Http\Controllers\Passenger\PassengerSettlementController::class, 'close'])->name('passenger-settlements.close');
        Route::post('passenger-settlements/{settlement}/cash-entry', [\App\Http\Controllers\Passenger\PassengerSettlementController::class, 'postCashEntry'])->name('passenger-settlements.cash-entry');

        // ── Druckaufträge (MVP-459, Branchenprofil druck-kopiershop):
        // Fachakte am Fertigungsauftrag mit Dateicheck/Preflight, Freigabe
        // (Hash-Bindung), Maschinen-Gate, QK und Ausgabe. ──
        Route::get('print-orders', [\App\Http\Controllers\Print\PrintOrderController::class, 'index'])->name('print-orders.index');
        Route::get('print-orders/create', [\App\Http\Controllers\Print\PrintOrderController::class, 'create'])->name('print-orders.create');
        Route::post('print-orders', [\App\Http\Controllers\Print\PrintOrderController::class, 'store'])->name('print-orders.store');
        Route::get('print-orders/{order}', [\App\Http\Controllers\Print\PrintOrderController::class, 'show'])->name('print-orders.show');
        Route::post('print-orders/{order}/file', [\App\Http\Controllers\Print\PrintOrderController::class, 'uploadFile'])->name('print-orders.file');
        Route::post('print-orders/{order}/preflight/run', [\App\Http\Controllers\Print\PrintOrderController::class, 'runPreflight'])->name('print-orders.preflight.run');
        Route::post('print-orders/{order}/preflight/manual', [\App\Http\Controllers\Print\PrintOrderController::class, 'recordManualPreflight'])->name('print-orders.preflight.manual');
        Route::post('print-orders/{order}/preflight/override', [\App\Http\Controllers\Print\PrintOrderController::class, 'overridePreflight'])->name('print-orders.preflight.override');
        Route::post('print-orders/{order}/approve', [\App\Http\Controllers\Print\PrintOrderController::class, 'approve'])->name('print-orders.approve');
        Route::post('print-orders/{order}/production/start', [\App\Http\Controllers\Print\PrintOrderController::class, 'startProduction'])->name('print-orders.production.start');
        Route::post('print-orders/{order}/production/resume', [\App\Http\Controllers\Print\PrintOrderController::class, 'resumeProduction'])->name('print-orders.production.resume');
        Route::post('print-orders/{order}/quality-check', [\App\Http\Controllers\Print\PrintOrderController::class, 'qualityCheck'])->name('print-orders.quality-check');
        Route::post('print-orders/{order}/issue', [\App\Http\Controllers\Print\PrintOrderController::class, 'issue'])->name('print-orders.issue');
        Route::post('print-orders/{order}/cancel', [\App\Http\Controllers\Print\PrintOrderController::class, 'cancel'])->name('print-orders.cancel');
        Route::post('print-orders/{order}/claim', [\App\Http\Controllers\Print\PrintOrderController::class, 'openClaim'])->name('print-orders.claim');
        Route::get('procedure-runs/{run}/print', [\App\Http\Controllers\ProcedureRunController::class, 'print'])->name('procedure-runs.print');
        Route::post('diary/{diary}/procedures/{template}/start', [\App\Http\Controllers\ProcedureRunController::class, 'start'])->name('procedure-runs.start');
        // Mobile Ausführung eines Prozedurlaufs (MVP-063): Schritt-für-Schritt,
        // bedingte Schritte, Warteschritte (MVP-064), Vier-Augen und Medien.
        Route::get('procedure-runs/{run}', [\App\Http\Controllers\ProcedureRunController::class, 'show'])->name('procedure-runs.show');
        Route::post('procedure-runs/{run}/steps/{stepRun}/execute', [\App\Http\Controllers\ProcedureRunController::class, 'executeStep'])->name('procedure-runs.steps.execute');
        Route::post('procedure-runs/{run}/steps/{stepRun}/wait/begin', [\App\Http\Controllers\ProcedureRunController::class, 'beginWait'])->name('procedure-runs.steps.wait.begin');
        Route::post('procedure-runs/{run}/steps/{stepRun}/wait/continue', [\App\Http\Controllers\ProcedureRunController::class, 'continueWait'])->name('procedure-runs.steps.wait.continue');
        Route::post('procedure-runs/{run}/steps/{stepRun}/second-person', [\App\Http\Controllers\ProcedureRunController::class, 'signSecondPerson'])->name('procedure-runs.steps.second-person');
        Route::post('procedure-runs/{run}/complete', [\App\Http\Controllers\ProcedureRunController::class, 'complete'])->name('procedure-runs.complete');
        Route::post('procedure-runs/{run}/abort', [\App\Http\Controllers\ProcedureRunController::class, 'abort'])->name('procedure-runs.abort');

        Route::get('form-submissions', [\App\Http\Controllers\FormSubmissionController::class, 'index'])->name('form-submissions.index');
        Route::get('form-submissions/create', [\App\Http\Controllers\FormSubmissionController::class, 'create'])->name('form-submissions.create');
        Route::post('form-submissions', [\App\Http\Controllers\FormSubmissionController::class, 'store'])->name('form-submissions.store');
        Route::get('form-submissions/{submission}/pdf', [\App\Http\Controllers\FormSubmissionController::class, 'pdf'])->name('form-submissions.pdf'); // Feature 032 Rang 31
        Route::get('form-submissions/{submission}', [\App\Http\Controllers\FormSubmissionController::class, 'show'])->name('form-submissions.show');

        // ── Protokolle (MVP-020) ───────────────────────────────────────────
        // Detailseite (Rang 28): Trägerseite für Panels (externe Beteiligte, Wetter, Verlauf).
        Route::get('protocols/{protocol}', [ProtocolController::class, 'show'])->name('protocols.show');
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
        // Vollaudit 2026-07 (H7): Caption-Pflege + Reihenfolge (Service auditiert).
        Route::patch('protocol-item-photos/{photo}/caption', [ProtocolController::class, 'updatePhotoCaption'])->name('protocols.items.photos.caption');
        Route::post('protocol-item-photos/{photo}/promote', [ProtocolController::class, 'promotePhoto'])->name('protocols.items.photos.promote');
        Route::post('protocols/{protocol}/signature-tokens', [ProtocolController::class, 'issueSignatureToken'])->name('protocols.signature-tokens.store');
        // Widerruf externer Signatur-Links (Feature 012 MVP; Vollaudit 2026-07, M6).
        Route::delete('protocols/{protocol}/signature-tokens/{token}', [ProtocolController::class, 'revokeSignatureToken'])->name('protocols.signature-tokens.destroy');
        Route::get('protocols/{protocol}/pdf', [ProtocolController::class, 'pdf'])->name('protocols.pdf');
        Route::post('protocols/{protocol}/weather', [ProtocolController::class, 'attachWeather'])->name('protocols.weather'); // Feature 062

        // ── Kunden-Rückfragen aus dem Portal (Feature 012) ─────────────────
        Route::get('customer-queries', [CustomerQueryController::class, 'index'])->name('customer-queries.index');
        Route::post('customer-queries/{customerQuery}/answer', [CustomerQueryController::class, 'answer'])->name('customer-queries.answer');
        Route::post('customer-queries/{customerQuery}/close', [CustomerQueryController::class, 'close'])->name('customer-queries.close');

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
        Route::get('assets/merge/compare', [\App\Http\Controllers\AssetMergeController::class, 'compare'])->name('assets.merge.compare');
        Route::post('assets/merge', [\App\Http\Controllers\AssetMergeController::class, 'merge'])->name('assets.merge');
        Route::post('assets', [AssetController::class, 'store'])->name('assets.store');
        Route::get('assets/{asset}/edit', [AssetController::class, 'edit'])->name('assets.edit');
        Route::put('assets/{asset}', [AssetController::class, 'update'])->name('assets.update');
        Route::post('assets/{asset}/unblock', [AssetController::class, 'unblock'])->name('assets.unblock');
        Route::get('assets/{asset}/dossier', \App\Http\Controllers\AssetDossierController::class)->name('assets.dossier');
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

        // ── Ausgabe/Rückgabe (Checkout) + Defekt/Sperre (Feature 009) ────────
        Route::get('assets/{asset}/checkout/create', [AssetCheckoutController::class, 'create'])->name('assets.checkout.create');
        Route::post('assets/{asset}/checkout', [AssetCheckoutController::class, 'store'])->name('assets.checkout.store');
        Route::post('assets/{asset}/checkout/{assignment}/return', [AssetCheckoutController::class, 'update'])->name('assets.checkout.return');

        Route::get('assets/{asset}/defects/create', [AssetDefectController::class, 'create'])->name('assets.defects.create');
        Route::post('assets/{asset}/defects', [AssetDefectController::class, 'store'])->name('assets.defects.store');
        Route::get('assets/{asset}/defects/{defect}/resolve', [AssetDefectController::class, 'resolveForm'])->name('assets.defects.resolve-form');
        Route::post('assets/{asset}/defects/{defect}/transition', [AssetDefectController::class, 'transition'])->name('assets.defects.transition');

        Route::get('software', [SoftwareController::class, 'index'])->name('software.index');
        Route::get('software/create', [SoftwareController::class, 'create'])->name('software.create');
        Route::post('software', [SoftwareController::class, 'store'])->name('software.store');
        Route::get('software/{software}/edit', [SoftwareController::class, 'edit'])->name('software.edit');
        Route::put('software/{software}', [SoftwareController::class, 'update'])->name('software.update');
        Route::delete('software/{software}', [SoftwareController::class, 'destroy'])->name('software.destroy');

        // Genehmigungs-Register (Veranstalter): behördliche Genehmigungen mit
        // Status/Frist/Nachweis. Gated über config/plans.php (permits.* = module.vertrieb).
        Route::get('permits', [\App\Http\Controllers\PermitController::class, 'index'])->name('permits.index');
        Route::get('permits/create', [\App\Http\Controllers\PermitController::class, 'create'])->name('permits.create');
        Route::post('permits', [\App\Http\Controllers\PermitController::class, 'store'])->name('permits.store');
        Route::get('permits/{permit}/edit', [\App\Http\Controllers\PermitController::class, 'edit'])->name('permits.edit');
        Route::put('permits/{permit}', [\App\Http\Controllers\PermitController::class, 'update'])->name('permits.update');
        Route::delete('permits/{permit}', [\App\Http\Controllers\PermitController::class, 'destroy'])->name('permits.destroy');

        Route::get('service-tickets', [ServiceTicketController::class, 'index'])->name('service-tickets.index');
        Route::get('service-tickets/create', [ServiceTicketController::class, 'create'])->name('service-tickets.create');
        Route::post('service-tickets', [ServiceTicketController::class, 'store'])->name('service-tickets.store');
        Route::get('service-tickets/{ticket}', [ServiceTicketController::class, 'show'])->name('service-tickets.show');
        Route::post('service-tickets/{ticket}/transition', [ServiceTicketController::class, 'transition'])->name('service-tickets.transition');
        Route::post('service-tickets/{ticket}/assign', [ServiceTicketController::class, 'assign'])->name('service-tickets.assign');
        // Vollaudit 2026-07 (M29): Incident-Klassifikation (Impact/Urgency → Priorität).
        Route::post('service-tickets/{ticket}/classify', [ServiceTicketController::class, 'classify'])->name('service-tickets.classify');
        Route::delete('service-tickets/{ticket}', [ServiceTicketController::class, 'destroy'])->name('service-tickets.destroy');
        // Konversation (Feature 065, MVP-152): Antwort vs. Notiz — getrennte
        // Aktionen/Rechte (Typfrage, keine Flagfrage).
        Route::post('service-tickets/{ticket}/reply', [\App\Http\Controllers\Helpdesk\TicketConversationController::class, 'reply'])->name('helpdesk.tickets.reply');
        Route::post('service-tickets/{ticket}/note', [\App\Http\Controllers\Helpdesk\TicketConversationController::class, 'note'])->name('helpdesk.tickets.note');
        // Ticket-Detail-Widgets (Feature 065, MVP-160): Beobachter,
        // Verknüpfungen, Major Incident — module.helpdesk.
        Route::post('service-tickets/{ticket}/watchers', [\App\Http\Controllers\Helpdesk\TicketWatcherController::class, 'store'])->name('helpdesk.tickets.watchers.store');
        Route::delete('service-tickets/{ticket}/watchers/{user}', [\App\Http\Controllers\Helpdesk\TicketWatcherController::class, 'destroy'])->name('helpdesk.tickets.watchers.destroy');
        Route::get('service-tickets/{ticket}/links/create', [\App\Http\Controllers\Helpdesk\TicketLinkController::class, 'create'])->name('helpdesk.tickets.links.create');
        Route::post('service-tickets/{ticket}/links', [\App\Http\Controllers\Helpdesk\TicketLinkController::class, 'store'])->name('helpdesk.tickets.links.store');
        Route::post('service-tickets/{ticket}/major', [\App\Http\Controllers\Helpdesk\TicketMajorIncidentController::class, 'store'])->name('helpdesk.tickets.major.store');
        Route::delete('service-tickets/{ticket}/major', [\App\Http\Controllers\Helpdesk\TicketMajorIncidentController::class, 'destroy'])->name('helpdesk.tickets.major.destroy');
        // Queue-Board (Feature 065, MVP-160) — module.helpdesk.
        Route::get('helpdesk/board', [\App\Http\Controllers\Helpdesk\QueueBoardController::class, 'index'])->name('helpdesk.board.index');
        Route::post('helpdesk/board/bulk-assign', [\App\Http\Controllers\Helpdesk\QueueBoardController::class, 'bulkAssign'])->name('helpdesk.board.bulk-assign');
        Route::post('helpdesk/board/bulk-queue', [\App\Http\Controllers\Helpdesk\QueueBoardController::class, 'bulkMove'])->name('helpdesk.board.bulk-queue');
        // Queue-Verwaltung (Feature 065, MVP-150) — module.helpdesk.
        Route::get('helpdesk/queues', [\App\Http\Controllers\Helpdesk\ServiceQueueController::class, 'index'])->name('helpdesk.queues.index');
        Route::get('helpdesk/queues/create', [\App\Http\Controllers\Helpdesk\ServiceQueueController::class, 'create'])->name('helpdesk.queues.create');
        Route::get('helpdesk/queues/{queue}/edit', [\App\Http\Controllers\Helpdesk\ServiceQueueController::class, 'edit'])->name('helpdesk.queues.edit');
        Route::post('helpdesk/queues', [\App\Http\Controllers\Helpdesk\ServiceQueueController::class, 'store'])->name('helpdesk.queues.store');
        Route::patch('helpdesk/queues/{queue}', [\App\Http\Controllers\Helpdesk\ServiceQueueController::class, 'update'])->name('helpdesk.queues.update');
        Route::delete('helpdesk/queues/{queue}', [\App\Http\Controllers\Helpdesk\ServiceQueueController::class, 'destroy'])->name('helpdesk.queues.destroy');
        // SLA-Verträge (read-only Detailseite, Feature 010): trägt Kontingente (44) + Pflichttermine (43).
        Route::get('sla-contracts', [\App\Http\Controllers\SlaContractController::class, 'index'])->name('sla-contracts.index');
        // SLA-CRUD (Feature 065, P3) — bestehendes Recht slaContract.manage.
        Route::post('sla-contracts', [\App\Http\Controllers\SlaContractController::class, 'store'])->name('sla-contracts.store');
        Route::patch('sla-contracts/{slaContract}', [\App\Http\Controllers\SlaContractController::class, 'update'])->name('sla-contracts.update');
        // Helpdesk-Bericht (Feature 065, P9).
        Route::get('helpdesk/berichte', [\App\Http\Controllers\Reporting\HelpdeskReportController::class, 'index'])->name('helpdesk.reports.index');
        // Drilldown/Exporte (Feature 065, MVP-159) — Drilldown nur signiert.
        Route::get('helpdesk/berichte/drilldown', [\App\Http\Controllers\Reporting\HelpdeskReportExportController::class, 'drilldown'])->name('helpdesk.reports.drilldown');
        Route::get('helpdesk/berichte/export/{metric}.csv', [\App\Http\Controllers\Reporting\HelpdeskReportExportController::class, 'csv'])->name('helpdesk.reports.csv');
        Route::get('helpdesk/berichte/export/bericht.pdf', [\App\Http\Controllers\Reporting\HelpdeskReportExportController::class, 'pdf'])->name('helpdesk.reports.pdf');
        // Routing-Regeln (Feature 065, P3).
        Route::get('helpdesk/routing', [\App\Http\Controllers\Helpdesk\TicketRoutingController::class, 'index'])->name('helpdesk.routing.index');
        Route::post('helpdesk/routing', [\App\Http\Controllers\Helpdesk\TicketRoutingController::class, 'store'])->name('helpdesk.routing.store');
        Route::patch('helpdesk/routing/{rule}', [\App\Http\Controllers\Helpdesk\TicketRoutingController::class, 'update'])->name('helpdesk.routing.update');
        Route::delete('helpdesk/routing/{rule}', [\App\Http\Controllers\Helpdesk\TicketRoutingController::class, 'destroy'])->name('helpdesk.routing.destroy');
        Route::post('helpdesk/routing/dry-run', [\App\Http\Controllers\Helpdesk\TicketRoutingController::class, 'dryRun'])->name('helpdesk.routing.dry-run');
        // Servicekatalog-Pflege (Feature 065, MVP-154) — servicedesk.* → module.service_desk!
        Route::get('servicedesk/catalog', [\App\Http\Controllers\Helpdesk\ServiceCatalogController::class, 'index'])->name('servicedesk.catalog.index');
        Route::get('servicedesk/catalog/services/create', [\App\Http\Controllers\Helpdesk\ServiceCatalogController::class, 'createService'])->name('servicedesk.catalog.services.create');
        Route::post('servicedesk/catalog/services', [\App\Http\Controllers\Helpdesk\ServiceCatalogController::class, 'storeService'])->name('servicedesk.catalog.services.store');
        Route::get('servicedesk/catalog/services/{service}/edit', [\App\Http\Controllers\Helpdesk\ServiceCatalogController::class, 'editService'])->name('servicedesk.catalog.services.edit');
        Route::patch('servicedesk/catalog/services/{service}', [\App\Http\Controllers\Helpdesk\ServiceCatalogController::class, 'updateService'])->name('servicedesk.catalog.services.update');
        Route::delete('servicedesk/catalog/services/{service}', [\App\Http\Controllers\Helpdesk\ServiceCatalogController::class, 'destroyService'])->name('servicedesk.catalog.services.destroy');
        Route::get('servicedesk/catalog/offerings/create', [\App\Http\Controllers\Helpdesk\ServiceCatalogController::class, 'createOffering'])->name('servicedesk.catalog.offerings.create');
        Route::post('servicedesk/catalog/offerings', [\App\Http\Controllers\Helpdesk\ServiceCatalogController::class, 'storeOffering'])->name('servicedesk.catalog.offerings.store');
        Route::get('servicedesk/catalog/offerings/{offering}/edit', [\App\Http\Controllers\Helpdesk\ServiceCatalogController::class, 'editOffering'])->name('servicedesk.catalog.offerings.edit');
        Route::patch('servicedesk/catalog/offerings/{offering}', [\App\Http\Controllers\Helpdesk\ServiceCatalogController::class, 'updateOffering'])->name('servicedesk.catalog.offerings.update');
        Route::delete('servicedesk/catalog/offerings/{offering}', [\App\Http\Controllers\Helpdesk\ServiceCatalogController::class, 'destroyOffering'])->name('servicedesk.catalog.offerings.destroy');
        Route::get('servicedesk/catalog/items/create', [\App\Http\Controllers\Helpdesk\ServiceCatalogController::class, 'createItem'])->name('servicedesk.catalog.items.create');
        Route::post('servicedesk/catalog/items', [\App\Http\Controllers\Helpdesk\ServiceCatalogController::class, 'storeItem'])->name('servicedesk.catalog.items.store');
        Route::get('servicedesk/catalog/items/{item}/edit', [\App\Http\Controllers\Helpdesk\ServiceCatalogController::class, 'editItem'])->name('servicedesk.catalog.items.edit');
        Route::patch('servicedesk/catalog/items/{item}', [\App\Http\Controllers\Helpdesk\ServiceCatalogController::class, 'updateItem'])->name('servicedesk.catalog.items.update');
        Route::delete('servicedesk/catalog/items/{item}', [\App\Http\Controllers\Helpdesk\ServiceCatalogController::class, 'destroyItem'])->name('servicedesk.catalog.items.destroy');
        // Genehmigungs-Inbox (Feature 065, MVP-154; MVP-157 nutzt sie für Changes mit).
        Route::get('servicedesk/approvals', [\App\Http\Controllers\Helpdesk\ApprovalInboxController::class, 'index'])->name('servicedesk.approvals.index');
        Route::get('servicedesk/approvals/{approval}/decide', [\App\Http\Controllers\Helpdesk\ApprovalInboxController::class, 'decideForm'])->name('servicedesk.approvals.decide-form');
        Route::post('servicedesk/approvals/{approval}/decide', [\App\Http\Controllers\Helpdesk\ApprovalInboxController::class, 'decide'])->name('servicedesk.approvals.decide');
        // Problem-Management (Feature 065, MVP-156) — servicedesk.* → module.service_desk!
        Route::get('servicedesk/problems', [\App\Http\Controllers\Helpdesk\ProblemController::class, 'index'])->name('servicedesk.problems.index');
        Route::get('servicedesk/problems/create', [\App\Http\Controllers\Helpdesk\ProblemController::class, 'create'])->name('servicedesk.problems.create');
        Route::post('servicedesk/problems', [\App\Http\Controllers\Helpdesk\ProblemController::class, 'store'])->name('servicedesk.problems.store');
        Route::get('servicedesk/problems/{problem}', [\App\Http\Controllers\Helpdesk\ProblemController::class, 'show'])->name('servicedesk.problems.show');
        Route::get('servicedesk/problems/{problem}/edit', [\App\Http\Controllers\Helpdesk\ProblemController::class, 'edit'])->name('servicedesk.problems.edit');
        Route::patch('servicedesk/problems/{problem}', [\App\Http\Controllers\Helpdesk\ProblemController::class, 'update'])->name('servicedesk.problems.update');
        Route::post('servicedesk/problems/{problem}/transition', [\App\Http\Controllers\Helpdesk\ProblemController::class, 'transition'])->name('servicedesk.problems.transition');
        Route::post('servicedesk/problems/{problem}/effectiveness', [\App\Http\Controllers\Helpdesk\ProblemController::class, 'effectiveness'])->name('servicedesk.problems.effectiveness');
        Route::post('servicedesk/problems/{problem}/publish', [\App\Http\Controllers\Helpdesk\ProblemController::class, 'publish'])->name('servicedesk.problems.publish');
        // Change-/CAB-Management (Feature 065, MVP-157) — servicedesk.* → module.service_desk!
        // Freigaben laufen über die GEMEINSAME Genehmigungs-Inbox (servicedesk.approvals.*).
        Route::get('servicedesk/changes', [\App\Http\Controllers\Helpdesk\ChangeController::class, 'index'])->name('servicedesk.changes.index');
        Route::get('servicedesk/changes/create', [\App\Http\Controllers\Helpdesk\ChangeController::class, 'create'])->name('servicedesk.changes.create');
        Route::post('servicedesk/changes', [\App\Http\Controllers\Helpdesk\ChangeController::class, 'store'])->name('servicedesk.changes.store');
        Route::get('servicedesk/changes/{change}', [\App\Http\Controllers\Helpdesk\ChangeController::class, 'show'])->name('servicedesk.changes.show');
        Route::post('servicedesk/changes/{change}/implement', [\App\Http\Controllers\Helpdesk\ChangeController::class, 'implement'])->name('servicedesk.changes.implement');
        Route::get('servicedesk/changes/{change}/complete', [\App\Http\Controllers\Helpdesk\ChangeController::class, 'completeForm'])->name('servicedesk.changes.complete-form');
        Route::post('servicedesk/changes/{change}/complete', [\App\Http\Controllers\Helpdesk\ChangeController::class, 'complete'])->name('servicedesk.changes.complete');
        Route::post('servicedesk/changes/{change}/assets', [\App\Http\Controllers\Helpdesk\ChangeController::class, 'storeAsset'])->name('servicedesk.changes.assets.store');
        Route::delete('servicedesk/changes/{change}/assets/{asset}', [\App\Http\Controllers\Helpdesk\ChangeController::class, 'destroyAsset'])->name('servicedesk.changes.assets.destroy');
        // Standard-Change-Vorlagen (MVP-157): Modal-CRUD + Freigabe.
        Route::get('servicedesk/change-templates', [\App\Http\Controllers\Helpdesk\ChangeTemplateController::class, 'index'])->name('servicedesk.change-templates.index');
        Route::get('servicedesk/change-templates/create', [\App\Http\Controllers\Helpdesk\ChangeTemplateController::class, 'create'])->name('servicedesk.change-templates.create');
        Route::post('servicedesk/change-templates', [\App\Http\Controllers\Helpdesk\ChangeTemplateController::class, 'store'])->name('servicedesk.change-templates.store');
        Route::get('servicedesk/change-templates/{template}/edit', [\App\Http\Controllers\Helpdesk\ChangeTemplateController::class, 'edit'])->name('servicedesk.change-templates.edit');
        Route::patch('servicedesk/change-templates/{template}', [\App\Http\Controllers\Helpdesk\ChangeTemplateController::class, 'update'])->name('servicedesk.change-templates.update');
        Route::post('servicedesk/change-templates/{template}/approve', [\App\Http\Controllers\Helpdesk\ChangeTemplateController::class, 'approve'])->name('servicedesk.change-templates.approve');
        Route::delete('servicedesk/change-templates/{template}', [\App\Http\Controllers\Helpdesk\ChangeTemplateController::class, 'destroy'])->name('servicedesk.change-templates.destroy');
        Route::get('sla-contracts/{slaContract}', [\App\Http\Controllers\SlaContractController::class, 'show'])->name('sla-contracts.show');

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

        // Führerscheinkontrolle (MVP-417): Fälligkeiten + dokumentierte Sichtprüfung.
        Route::get('driver-license-checks', [\App\Http\Controllers\DriverLicenseCheckController::class, 'index'])->name('driver-license-checks.index');
        Route::get('driver-license-checks/create', [\App\Http\Controllers\DriverLicenseCheckController::class, 'create'])->name('driver-license-checks.create');
        Route::post('driver-license-checks', [\App\Http\Controllers\DriverLicenseCheckController::class, 'store'])->name('driver-license-checks.store');
        Route::get('driver-license-checks/{user}', [\App\Http\Controllers\DriverLicenseCheckController::class, 'show'])->name('driver-license-checks.show');

        // Fahrzeug-Reservierungen (Feature 028 — Disposition).
        Route::get('vehicle-reservations', [VehicleReservationController::class, 'index'])->name('vehicle-reservations.index');
        Route::post('vehicle-reservations', [VehicleReservationController::class, 'store'])->name('vehicle-reservations.store');
        Route::delete('vehicle-reservations/{vehicleReservation}', [VehicleReservationController::class, 'destroy'])->name('vehicle-reservations.destroy');

        Route::get('energy-logs', [EnergyLogController::class, 'index'])->name('energy-logs.index');
        Route::get('energy-logs/create', [EnergyLogController::class, 'create'])->name('energy-logs.create');
        Route::post('energy-logs', [EnergyLogController::class, 'store'])->name('energy-logs.store');
        Route::get('energy-logs/{energyLog}/edit', [EnergyLogController::class, 'edit'])->name('energy-logs.edit');
        Route::put('energy-logs/{energyLog}', [EnergyLogController::class, 'update'])->name('energy-logs.update');
        Route::delete('energy-logs/{energyLog}', [EnergyLogController::class, 'destroy'])->name('energy-logs.destroy');

        // ── Geocoding (intern) ──────────────────────────────────────────────
        Route::post('api/internal/geocode', GeocodeController::class)->name('api.internal.geocode');

        // ── Cloud-Dokumenteingang (Feature 080) ─────────────────────────────
        Route::get('admin/cloud-intake', [\App\Http\Controllers\Admin\CloudIntakeAdminController::class, 'index'])
            ->name('admin.cloud-intake.index');
        Route::post('admin/cloud-intake/{connection}/folder', [\App\Http\Controllers\Admin\CloudIntakeAdminController::class, 'selectFolder'])
            ->name('admin.cloud-intake.folder');
        Route::post('admin/cloud-intake/{connection}/preview', [\App\Http\Controllers\Admin\CloudIntakeAdminController::class, 'preview'])
            ->name('admin.cloud-intake.preview');
        Route::delete('admin/cloud-intake/{connection}', [\App\Http\Controllers\Admin\CloudIntakeAdminController::class, 'disconnect'])
            ->name('admin.cloud-intake.disconnect');
        Route::get('admin/cloud-intake/{connection}/routes/create', [\App\Http\Controllers\Admin\CloudIntakeAdminController::class, 'createRoute'])
            ->name('admin.cloud-intake.routes.create');
        Route::post('admin/cloud-intake/{connection}/routes', [\App\Http\Controllers\Admin\CloudIntakeAdminController::class, 'storeRoute'])
            ->name('admin.cloud-intake.routes.store');
        Route::get('admin/cloud-intake/routes/{cloudRoute}/edit', [\App\Http\Controllers\Admin\CloudIntakeAdminController::class, 'editRoute'])
            ->name('admin.cloud-intake.routes.edit');
        Route::put('admin/cloud-intake/routes/{cloudRoute}', [\App\Http\Controllers\Admin\CloudIntakeAdminController::class, 'updateRoute'])
            ->name('admin.cloud-intake.routes.update');
        Route::delete('admin/cloud-intake/routes/{cloudRoute}', [\App\Http\Controllers\Admin\CloudIntakeAdminController::class, 'destroyRoute'])
            ->name('admin.cloud-intake.routes.destroy');

        // ── Cloud-Backupziele (Feature 017 Phase 32) — Plattform-Admin ──────
        Route::get('admin/backup-targets', [\App\Http\Controllers\Admin\BackupTargetAdminController::class, 'index'])
            ->name('admin.backup-targets.index');
        Route::delete('admin/backup-targets/{backupConnection}', [\App\Http\Controllers\Admin\BackupTargetAdminController::class, 'disconnect'])
            ->name('admin.backup-targets.disconnect');
        Route::post('admin/backup-targets/generations/{backupGeneration}/hold', [\App\Http\Controllers\Admin\BackupTargetAdminController::class, 'toggleHold'])
            ->name('admin.backup-targets.generations.hold');
        Route::get('admin/backup-targets/{backupConnection}/cleanup', [\App\Http\Controllers\Admin\BackupTargetAdminController::class, 'cleanupPreview'])
            ->name('admin.backup-targets.cleanup.preview');
        Route::delete('admin/backup-targets/generations/{backupGeneration}', [\App\Http\Controllers\Admin\BackupTargetAdminController::class, 'destroyGeneration'])
            ->name('admin.backup-targets.generations.destroy');

        // ── Offline-Sync-Outbox (Feature 035, Phase 1) ──────────────────────
        Route::post('api/internal/sync/commands', SyncCommandController::class)
            ->middleware('throttle:60,1')
            ->name('api.internal.sync.commands');
        // Phase 3 (MVP-367): Geräte-lokale Liste der Outbox-/abgelehnten
        // Befehle; Inhalte rendert resources/js/offline-sync.js aus IndexedDB.
        Route::view('offline/changes', 'offline.changes')->name('offline.changes');

        // ── Globale Suche / Command-Palette ─────────────────────────────────
        Route::get('api/internal/search', GlobalSearchController::class)->name('api.internal.search');
        // Vollergebnisseite der globalen Suche (globale-suche.md AK 2–3; Vollaudit 2026-07, M8).
        Route::get('suche', [\App\Http\Controllers\SearchController::class, 'index'])->name('search.index');

        // ── Globale Zeitauswahl (Header-Widget) ─────────────────────────────────
        Route::post('ui/date-range', [DateRangeController::class, 'update'])->name('ui.date-range.update');
        Route::post('ui/date-range/shift', [DateRangeController::class, 'shift'])->name('ui.date-range.shift');

        // ── Gleitzeit ───────────────────────────────────────────────────────────
        // ── Aktuelle Personal-Belegung (MVP-524, Opt-in je Org) ─────────────────
        Route::get('belegung', [\App\Http\Controllers\PresenceBoardController::class, 'index'])->name('presence.board');

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
        // Prüfexport-Bundle für freigegebene/gesperrte Monate (Rang 40).
        Route::post('admin/month-approval/{monthClosure}/bundle', [\App\Http\Controllers\Admin\MonthApprovalInboxController::class, 'bundle'])
            ->name('admin.month-approval.bundle');

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

        // ── Überstunden-Anträge (MVP-519) ───────────────────────────────────────
        Route::get('overtime', [\App\Http\Controllers\OvertimeRequestController::class, 'index'])
            ->name('overtime.index');
        Route::get('overtime/create', [\App\Http\Controllers\OvertimeRequestController::class, 'create'])
            ->name('overtime.create');
        Route::post('overtime', [\App\Http\Controllers\OvertimeRequestController::class, 'store'])
            ->name('overtime.store');
        Route::post('overtime/{overtime}/withdraw', [\App\Http\Controllers\OvertimeRequestController::class, 'withdraw'])
            ->name('overtime.withdraw');

        Route::get('admin/overtime', [\App\Http\Controllers\Admin\OvertimeInboxController::class, 'index'])
            ->name('admin.overtime.index');
        Route::post('admin/overtime/{overtime}/approve', [\App\Http\Controllers\Admin\OvertimeInboxController::class, 'approve'])
            ->name('admin.overtime.approve');
        Route::post('admin/overtime/{overtime}/reject', [\App\Http\Controllers\Admin\OvertimeInboxController::class, 'reject'])
            ->name('admin.overtime.reject');

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

        // ── Tagesabschluss (MVP-015) ────────────────────────────────────────────
        Route::get('tagesabschluss', [\App\Http\Controllers\DayCloseController::class, 'show'])
            ->name('day-close.show');
        Route::post('tagesabschluss/save', [\App\Http\Controllers\DayCloseController::class, 'save'])
            ->name('day-close.save');
        Route::post('tagesabschluss/close', [\App\Http\Controllers\DayCloseController::class, 'close'])
            ->name('day-close.close');
        Route::post('tagesabschluss/request-correction', [\App\Http\Controllers\DayCloseController::class, 'requestCorrection'])
            ->name('day-close.request-correction');
        Route::post('tagesabschluss/corrections/{dayCorrection}/approve', [\App\Http\Controllers\DayCloseController::class, 'approveCorrection'])
            ->name('day-close.correction.approve');
        Route::post('tagesabschluss/corrections/{dayCorrection}/reject', [\App\Http\Controllers\DayCloseController::class, 'rejectCorrection'])
            ->name('day-close.correction.reject');
        Route::post('tagesabschluss/reopen', [\App\Http\Controllers\DayCloseController::class, 'reopen'])
            ->name('day-close.reopen');

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
        // Kostenstellen-Override je Zeile im Prüf-UI (Rang 35, nur Status ready).
        Route::patch('exports/{export}/lines/{line}', [\App\Http\Controllers\TimeExportController::class, 'updateLine'])
            ->name('exports.lines.update');
        // Löschung mit Pflicht-Begründung (Vollaudit 2026-07, N6).
        Route::delete('exports/{export}', [\App\Http\Controllers\TimeExportController::class, 'destroy'])
            ->name('exports.destroy');

        // ── Plan/Ist-Report (MVP-018) ──────────────────────────────────────────
        Route::get('reports/plan-ist/presence', [\App\Http\Controllers\Reporting\PlanIstReportController::class, 'presence'])
            ->name('reports.plan-ist.presence');
        // Team-/Org-Aggregation (Rang 38, report.presence.team/.organization).
        Route::get('reports/plan-ist/team', [\App\Http\Controllers\Reporting\PlanIstReportController::class, 'team'])
            ->name('reports.plan-ist.team');
        Route::get('reports/plan-ist/organization', [\App\Http\Controllers\Reporting\PlanIstReportController::class, 'organization'])
            ->name('reports.plan-ist.organization');
        // Erweiterte Dimensionen (A14 · MVP-333): Schicht/Projekt/Standort,
        // org-weit → report.presence.organization (im Controller geprüft).
        Route::get('reports/plan-ist/shifts', [\App\Http\Controllers\Reporting\PlanIstReportController::class, 'shifts'])
            ->name('reports.plan-ist.shifts');
        Route::get('reports/plan-ist/projects', [\App\Http\Controllers\Reporting\PlanIstReportController::class, 'projects'])
            ->name('reports.plan-ist.projects');
        Route::get('reports/plan-ist/sites', [\App\Http\Controllers\Reporting\PlanIstReportController::class, 'sites'])
            ->name('reports.plan-ist.sites');

        // ── Auswertungen ────────────────────────────────────────────────────────
        // Übersichts-Landing (Feature 002): KPIs + Einstieg in alle Reports.
        Route::get('reports', [\App\Http\Controllers\Reporting\ReportsOverviewController::class, 'index'])->name('reports.index');
        Route::get('reports/my-year', [MyYearReportController::class, 'index'])->name('reports.my-year');
        Route::get('reports/my-month', [MyMonthReportController::class, 'index'])->name('reports.my-month');
        Route::get('reports/external-payouts', [ExternalPayoutReportController::class, 'index'])->name('reports.external-payouts');
        Route::get('reports/customers', [CustomerAnalysisReportController::class, 'index'])->name('reports.customers');
        // Entscheidungsanalysen (Phase 53, MVP-465/466/467/468).
        Route::get('reports/customer-value', [\App\Http\Controllers\Reporting\CustomerValueReportController::class, 'index'])
            ->name('reports.customer-value');
        // Zeitaufteilung nach Dimension (Feature 103, MVP-514 P3).
        Route::get('reports/allocations', [\App\Http\Controllers\Reporting\AllocationReportController::class, 'index'])
            ->name('reports.allocations');
        // Zuschlags-Prognose auf geplante Dienste (Feature 103, MVP-533).
        Route::get('reports/surcharge-forecast', [\App\Http\Controllers\Reporting\SurchargeForecastReportController::class, 'index'])
            ->name('reports.surcharge-forecast');
        Route::get('reports/customer-retention', [\App\Http\Controllers\Reporting\CustomerRetentionReportController::class, 'index'])
            ->name('reports.customer-retention');
        // Kohorten-Drilldown (MVP-470): wer steckt hinter einer Heatmap-Zelle?
        Route::get('reports/customer-retention/drilldown', [\App\Http\Controllers\Reporting\CustomerRetentionReportController::class, 'drilldown'])
            ->name('reports.customer-retention.drilldown');
        Route::get('reports/utilization', [\App\Http\Controllers\Reporting\UtilizationReportController::class, 'index'])
            ->name('reports.utilization');
        Route::get('reports/payment-behavior', [\App\Http\Controllers\Reporting\PaymentBehaviorReportController::class, 'index'])
            ->name('reports.payment-behavior');
        // Lieferantenanalyse (MVP-472): Einkaufs-Pendant zur Kundenanalyse,
        // Ausgaben aus dem Beleg-Spiegel (ohne Lager nutzbar), report.view-gated.
        Route::get('reports/suppliers', [\App\Http\Controllers\Reporting\SupplierAnalysisReportController::class, 'index'])
            ->name('reports.suppliers');
        // Lieferantenwert (MVP-473): RFM/Portfolio-Pendant zum Kundenwert.
        Route::get('reports/supplier-value', [\App\Http\Controllers\Reporting\SupplierValueReportController::class, 'index'])
            ->name('reports.supplier-value');
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
        // Wiederholdefekt-Statistik (Feature 009 → Rang 47).
        Route::get('reports/assets/drilldown/recurring-defects', [\App\Http\Controllers\Reporting\AssetDrilldownReportController::class, 'recurringDefects'])
            ->name('reports.assets.drilldown.recurring-defects');
        Route::get('reports/customer-project', [CustomerProjectReportController::class, 'index'])->name('reports.customer-project');
        // Datenqualitäts-Report: Aufträge mit fehlenden Pflichtklassifikationen (Feature 024 → Rang 57).
        Route::get('reports/data-quality', [\App\Http\Controllers\Reporting\DataQualityReportController::class, 'index'])->name('reports.data-quality');
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
        // Urlaubsplan-Jahresübersicht + Fehlzeitenkarte (MVP-520).
        Route::get('reports/absence-calendar', [\App\Http\Controllers\Reporting\AbsenceCalendarReportController::class, 'index'])->name('reports.absence-calendar');
        Route::get('reports/sickness', [SicknessReportController::class, 'index'])->name('reports.sickness');
        Route::get('reports/operations', [OperationsReportController::class, 'index'])->name('reports.operations');
        Route::get('reports/materials', [MaterialReportController::class, 'index'])->name('reports.materials');
        Route::get('reports/economics', [EconomicsReportController::class, 'index'])->name('reports.economics');
        // Beleg-Drilldown je Report-Zelle (Rang 59c, signierte Links).
        Route::get('reports/economics/drilldown', [EconomicsReportController::class, 'drilldown'])->name('reports.economics.drilldown');
        Route::get('reports/billing', [BillingReportController::class, 'index'])->name('reports.billing');
        Route::get('reports/expenses', [ExpenseReportController::class, 'index'])->name('reports.expenses');
        Route::get('reports/qualifications', [QualificationReportController::class, 'index'])->name('reports.qualifications');
        // Feature 002: Kohortenvergleich vor/nach Fortbildung.
        Route::get('reports/cohort-comparison', [\App\Http\Controllers\Reporting\CohortComparisonReportController::class, 'index'])
            ->name('reports.cohort-comparison');
        Route::get('reports/attendance', [AttendanceReportController::class, 'index'])->name('reports.attendance');
        // Notfall-Anwesenheitsliste (Feature 103, MVP-518): bewusst NICHT modul-gegated (Arbeitsschutz).
        Route::get('reports/presence-emergency', [\App\Http\Controllers\Reporting\PresenceEmergencyReportController::class, 'index'])
            ->name('reports.presence-emergency');
        Route::get('reports/audit-activity', [AuditActivityReportController::class, 'index'])->name('reports.audit-activity');
        Route::get('reports/sla', [\App\Http\Controllers\Reporting\SlaReportController::class, 'index'])->name('reports.sla');
        Route::get('reports/safety', [\App\Http\Controllers\Reporting\SafetyReportController::class, 'index'])->name('reports.safety');
        // ArbZG-Compliance auf Ist-Arbeitszeit (Feature 006).
        // Compliance-Dashboard (Rang 39): org-weite Übersicht mit Drilldown in den Einzelreport.
        Route::get('reports/compliance/dashboard', [\App\Http\Controllers\Reporting\ArbZgComplianceReportController::class, 'dashboard'])
            ->name('reports.compliance.dashboard');
        Route::get('reports/arbzg-compliance', [\App\Http\Controllers\Reporting\ArbZgComplianceReportController::class, 'index'])
            ->name('reports.arbzg-compliance');
        // Persistierte Verstoß-Historie + Acknowledge-Workflow (Feature 006, Welle D).
        Route::get('reports/compliance/history', [\App\Http\Controllers\Reporting\ArbZgComplianceReportController::class, 'history'])
            ->name('reports.compliance.history');
        Route::post('reports/compliance/findings/{finding}/acknowledge', [\App\Http\Controllers\Reporting\ArbZgComplianceReportController::class, 'acknowledge'])
            ->name('reports.compliance.acknowledge');
        Route::post('reports/sla/violations/{violation}/acknowledge', [\App\Http\Controllers\Reporting\SlaReportController::class, 'acknowledge'])
            ->name('reports.sla.acknowledge');

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
            ->except(['show'])
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

        // Theme-Editor (eigene Themes erstellen). Erstellen/Bearbeiten ist über
        // EnforcePlanModules (config/plans.php: admin.themes.* = module.theming)
        // auf Pro+ gegated; die Anwendung gesetzter Themes läuft im Layout und
        // bleibt davon unberührt.
        Route::get('admin/themes', [\App\Http\Controllers\Admin\ThemeController::class, 'index'])->name('admin.themes.index');
        Route::get('admin/themes/create', [\App\Http\Controllers\Admin\ThemeController::class, 'create'])->name('admin.themes.create');
        Route::post('admin/themes', [\App\Http\Controllers\Admin\ThemeController::class, 'store'])->name('admin.themes.store');
        Route::get('admin/themes/{key}/edit', [\App\Http\Controllers\Admin\ThemeController::class, 'edit'])->name('admin.themes.edit');
        Route::put('admin/themes/{key}', [\App\Http\Controllers\Admin\ThemeController::class, 'update'])->name('admin.themes.update');
        Route::delete('admin/themes/{key}', [\App\Http\Controllers\Admin\ThemeController::class, 'destroy'])->name('admin.themes.destroy');
        Route::put('admin/themes-default', [\App\Http\Controllers\Admin\ThemeController::class, 'setDefault'])->name('admin.themes.default');

        // Konfigurierbare Nummernkreise (Tickets, Assets, Kunden, Rechnungen, Gutschriften).
        Route::get('admin/number-formats', [\App\Http\Controllers\Admin\NumberFormatController::class, 'index'])
            ->name('admin.number-formats.index');
        Route::put('admin/number-formats', [\App\Http\Controllers\Admin\NumberFormatController::class, 'update'])
            ->name('admin.number-formats.update');

        Route::resource('admin/entry-types', EntryTypeController::class)
            ->names('admin.entry-types')
            ->parameters(['entry-types' => 'entryType'])
            ->except('show');

        // Feature 002: Zielwerte & Benchmarks pflegen (GF/Admin).
        Route::resource('admin/report-targets', \App\Http\Controllers\Admin\ReportTargetController::class)
            ->names('admin.report-targets')
            ->parameters(['report-targets' => 'reportTarget'])
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
        // CSV-Mustervorlage je Entität (Feature 020 MVP; Vollaudit 2026-07, N8).
        Route::get('admin/imports/vorlage/{entity}.csv', [ImportController::class, 'template'])->name('admin.imports.template');
        // MVP-438: iCal-Beispieldatei je Zeiterfassungs-Entität.
        Route::get('admin/imports/vorlage/{entity}.ics', [ImportController::class, 'icalSample'])->name('admin.imports.icalSample');
        Route::post('admin/imports/preflight', [ImportController::class, 'preflight'])->name('admin.imports.preflight');
        Route::get('admin/imports/{import}', [ImportController::class, 'show'])->name('admin.imports.show');
        Route::post('admin/imports/{import}/confirm', [ImportController::class, 'confirm'])->name('admin.imports.confirm');
        // Wert-Mapping unbekannter Tags/Kategorien (Rang 58).
        Route::post('admin/imports/{import}/mapping', [ImportController::class, 'mapping'])->name('admin.imports.mapping');
        Route::delete('admin/imports/{import}', [ImportController::class, 'destroy'])->name('admin.imports.destroy');
        Route::get('admin/imports/{import}/errors.csv', [ImportController::class, 'downloadErrors'])->name('admin.imports.errors');

        // Datentransfer — zentraler Im-/Export-Bereich
        Route::get('admin/data', [\App\Http\Controllers\Admin\DataTransferController::class, 'index'])->name('admin.data.index');
        Route::get('admin/data/history', [\App\Http\Controllers\Admin\DataTransferController::class, 'history'])->name('admin.data.history');
        Route::post('admin/data/export', [\App\Http\Controllers\Admin\DataTransferController::class, 'export'])->name('admin.data.export');
        Route::get('admin/data/{export}/download', [\App\Http\Controllers\Admin\DataTransferController::class, 'download'])->name('admin.data.download');
        Route::delete('admin/data/{export}', [\App\Http\Controllers\Admin\DataTransferController::class, 'destroy'])->name('admin.data.destroy');

        // Integrations-Drehscheibe: zentrale Zuordnungs-Inbox + Zuordnungs-Register (MVP-103)
        Route::get('admin/integration/inbox', [\App\Http\Controllers\Admin\IntegrationInboxController::class, 'index'])->name('admin.integration.inbox');
        Route::post('admin/integration/inbox/{item}/assign', [\App\Http\Controllers\Admin\IntegrationInboxController::class, 'assign'])->name('admin.integration.inbox.assign');
        Route::post('admin/integration/inbox/{item}/create', [\App\Http\Controllers\Admin\IntegrationInboxController::class, 'create'])->name('admin.integration.inbox.create');
        Route::post('admin/integration/inbox/{item}/accept-remote', [\App\Http\Controllers\Admin\IntegrationInboxController::class, 'acceptRemote'])->name('admin.integration.inbox.accept-remote');
        Route::post('admin/integration/inbox/{item}/keep-local', [\App\Http\Controllers\Admin\IntegrationInboxController::class, 'keepLocal'])->name('admin.integration.inbox.keep-local');
        Route::post('admin/integration/inbox/{item}/dismiss', [\App\Http\Controllers\Admin\IntegrationInboxController::class, 'dismiss'])->name('admin.integration.inbox.dismiss');
        Route::post('admin/integration/inbox/group/book', [\App\Http\Controllers\Admin\IntegrationInboxController::class, 'bookGroup'])->name('admin.integration.inbox.group.book');
        Route::post('admin/integration/inbox/group/dismiss', [\App\Http\Controllers\Admin\IntegrationInboxController::class, 'dismissGroup'])->name('admin.integration.inbox.group.dismiss');
        Route::get('admin/integration/mappings', [\App\Http\Controllers\Admin\IntegrationMappingController::class, 'index'])->name('admin.integration.mappings.index');
        Route::delete('admin/integration/mappings/{reference}', [\App\Http\Controllers\Admin\IntegrationMappingController::class, 'destroy'])->name('admin.integration.mappings.destroy');

        Route::get('admin/branch-profiles', [BranchProfileController::class, 'index'])
            ->name('admin.branch-profiles.index');
        Route::post('admin/branch-profiles-import', [BranchProfileController::class, 'import'])
            ->name('admin.branch-profiles.import');
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

        // Ausgehende Webhooks (Feature 008): Listenseite mit Zustellprotokoll
        // + Modal-CRUD, Secret-Rotation und Test-Event. Pflege durch Admin
        // (webhook.manage). {webhook} bindet via Sqid (HasSqid).
        Route::get('admin/webhooks', [\App\Http\Controllers\Admin\WebhookEndpointController::class, 'index'])
            ->name('admin.webhooks.index');
        Route::get('admin/webhooks/create', [\App\Http\Controllers\Admin\WebhookEndpointController::class, 'create'])
            ->name('admin.webhooks.create');
        Route::post('admin/webhooks', [\App\Http\Controllers\Admin\WebhookEndpointController::class, 'store'])
            ->name('admin.webhooks.store');
        Route::get('admin/webhooks/{webhook}/edit', [\App\Http\Controllers\Admin\WebhookEndpointController::class, 'edit'])
            ->name('admin.webhooks.edit');
        Route::put('admin/webhooks/{webhook}', [\App\Http\Controllers\Admin\WebhookEndpointController::class, 'update'])
            ->name('admin.webhooks.update');
        Route::post('admin/webhooks/{webhook}/rotate-secret', [\App\Http\Controllers\Admin\WebhookEndpointController::class, 'rotateSecret'])
            ->name('admin.webhooks.rotate-secret');
        Route::post('admin/webhooks/{webhook}/test', [\App\Http\Controllers\Admin\WebhookEndpointController::class, 'test'])
            ->middleware('throttle:12,1')
            ->name('admin.webhooks.test');
        Route::delete('admin/webhooks/{webhook}', [\App\Http\Controllers\Admin\WebhookEndpointController::class, 'destroy'])
            ->name('admin.webhooks.destroy');

        // Zuschlagsregeln (Feature 005): Listenseite + Modal-CRUD,
        // Pflege durch Admin/Buchhaltung (surchargeRule.manage).
        Route::resource('admin/surcharge-rules', \App\Http\Controllers\Admin\SurchargeRuleController::class)
            ->names('admin.surcharge-rules')
            ->parameters(['surcharge-rules' => 'surchargeRule'])
            ->except('show');

        // Kostenstellen-Regeln für den Zeitexport (Rang 35): gleiche Mechanik.
        Route::resource('admin/cost-center-rules', \App\Http\Controllers\Admin\CostCenterRuleController::class)
            ->names('admin.cost-center-rules')
            ->parameters(['cost-center-rules' => 'costCenterRule'])
            ->except('show');

        // Lohnarten-Mapping + automatische Export-Lieferung (A21 · MVP-019):
        // gleiche Mechanik; Lieferkonfiguration je Profil als eigener Dialog.
        Route::get('admin/wage-type-mappings/delivery/{profile}/edit', [\App\Http\Controllers\Admin\WageTypeMappingController::class, 'editDelivery'])
            ->where('profile', '[a-z0-9_-]+')
            ->name('admin.wage-type-mappings.delivery.edit');
        Route::put('admin/wage-type-mappings/delivery/{profile}', [\App\Http\Controllers\Admin\WageTypeMappingController::class, 'updateDelivery'])
            ->where('profile', '[a-z0-9_-]+')
            ->name('admin.wage-type-mappings.delivery.update');
        Route::resource('admin/wage-type-mappings', \App\Http\Controllers\Admin\WageTypeMappingController::class)
            ->names('admin.wage-type-mappings')
            ->parameters(['wage-type-mappings' => 'wageTypeMapping'])
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

        // Personalstamm-CSV-Import (Feature 103, MVP-537) — vor der Resource,
        // damit 'members-import' nicht als {member} gebunden wird.
        Route::get('org/members-import', [OrgMemberController::class, 'importForm'])->name('org.members.import.form');
        Route::post('org/members-import', [OrgMemberController::class, 'import'])->name('org.members.import');
        Route::get('org/members-import/template', [OrgMemberController::class, 'importTemplate'])->name('org.members.import.template');
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
            // Besetzungsvorschläge (Feature 007)
            Route::get('/suggest', [ScheduleController::class, 'suggest'])->name('suggest');
            // Verfügbarkeiten & Wunschdienste – Self-Service (Feature 007)
            Route::get('/availability', [AvailabilityController::class, 'index'])->name('availability.index');
            Route::post('/availability/windows', [AvailabilityController::class, 'storeWindow'])->name('availability.windows.store');
            Route::put('/availability/windows/{window}', [AvailabilityController::class, 'updateWindow'])->name('availability.windows.update');
            Route::delete('/availability/windows/{window}', [AvailabilityController::class, 'destroyWindow'])->name('availability.windows.destroy');
            Route::post('/availability/desired', [AvailabilityController::class, 'storeDesired'])->name('availability.desired.store');
            Route::put('/availability/desired/{desired}', [AvailabilityController::class, 'updateDesired'])->name('availability.desired.update');
            Route::delete('/availability/desired/{desired}', [AvailabilityController::class, 'destroyDesired'])->name('availability.desired.destroy');
            // Schichttausch mit Freigabe (Feature 007)
            Route::get('/exchanges', [ShiftExchangeController::class, 'index'])->name('exchanges.index');
            Route::post('/exchanges', [ShiftExchangeController::class, 'store'])->name('exchanges.store');
            Route::patch('/exchanges/{exchange}/accept', [ShiftExchangeController::class, 'accept'])->name('exchanges.accept');
            Route::patch('/exchanges/{exchange}/cancel', [ShiftExchangeController::class, 'cancel'])->name('exchanges.cancel');
            Route::patch('/exchanges/{exchange}/approve', [ShiftExchangeController::class, 'approve'])->name('exchanges.approve');
            Route::patch('/exchanges/{exchange}/reject', [ShiftExchangeController::class, 'reject'])->name('exchanges.reject');
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
        Route::get('profile/api-tokens/create', [ApiTokenController::class, 'create'])->name('profile.api-tokens.create');
        Route::post('profile/api-tokens', [ApiTokenController::class, 'store'])->name('profile.api-tokens.store');
        Route::delete('profile/api-tokens/{id}', [ApiTokenController::class, 'destroy'])
            ->where('id', '[A-Za-z0-9]+')
            ->name('profile.api-tokens.destroy');
    });
});
