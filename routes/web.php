<?php

/*
 * Created on   : Wed Apr 29 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : web.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

use App\Http\Controllers\AccountPasswordController;
use App\Http\Controllers\ActivityCategoryController;
use App\Http\Controllers\Admin\Access\AccessHubController;
use App\Http\Controllers\Admin\Access\MemberController as AccessMemberController;
use App\Http\Controllers\Admin\Access\PermissionController as AccessPermissionController;
use App\Http\Controllers\Admin\Access\RoleController as AccessRoleController;
use App\Http\Controllers\Admin\Access\UserGroupController as AccessUserGroupController;
use App\Http\Controllers\Admin\AutomationRuleController;
use App\Http\Controllers\Admin\EntryTypeController;
use App\Http\Controllers\Admin\ExpenseCategoryController;
use App\Http\Controllers\Admin\PerDiemRateController;
use App\Http\Controllers\Admin\PluginController as AdminPluginController;
use App\Http\Controllers\AdminTimeEntryController;
use App\Http\Controllers\ApiTokenController;
use App\Http\Controllers\ArchiveController;
use App\Http\Controllers\AttachmentController;
use App\Http\Controllers\AttendanceController;
use App\Http\Controllers\AuditLogController;
use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\Auth\TenantRegistrationController;
use App\Http\Controllers\BrandingController;
use App\Http\Controllers\CalendarFeedController;
use App\Http\Controllers\CommentController;
use App\Http\Controllers\CoverageRequirementController;
use App\Http\Controllers\CustomerController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\DiaryController;
use App\Http\Controllers\DiaryExportController;
use App\Http\Controllers\DutyController;
use App\Http\Controllers\DutyPlanController;
use App\Http\Controllers\EmergencyAssignmentController;
use App\Http\Controllers\EnergyLogController;
use App\Http\Controllers\EventCategoryController;
use App\Http\Controllers\EventController;
use App\Http\Controllers\EventParticipantController;
use App\Http\Controllers\ExpenseApprovalController;
use App\Http\Controllers\ExpenseController;
use App\Http\Controllers\FlexController;
use App\Http\Controllers\FlexEligibilityController;
use App\Http\Controllers\GeocodeController;
use App\Http\Controllers\GlobalSearchController;
use App\Http\Controllers\HolidayController;
use App\Http\Controllers\HomeController;
use App\Http\Controllers\IcsFeedController;
use App\Http\Controllers\InvoiceController;
use App\Http\Controllers\KanbanController;
use App\Http\Controllers\LicenseController;
use App\Http\Controllers\LocaleController;
use App\Http\Controllers\MaterialController;
use App\Http\Controllers\MilestoneController;
use App\Http\Controllers\OnCallShiftController;
use App\Http\Controllers\OpenIssueController;
use App\Http\Controllers\OrganizationController;
use App\Http\Controllers\OrganizationSwitchController;
use App\Http\Controllers\OrgMemberController;
use App\Http\Controllers\PerDiemTripController;
use App\Http\Controllers\Plugins\LexofficeCustomerController;
use App\Http\Controllers\PrintController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\ProjectBillingRuleController;
use App\Http\Controllers\ProjectController;
use App\Http\Controllers\ProjectRecurrenceRuleController;
use App\Http\Controllers\ProtocolController;
use App\Http\Controllers\PublicProtocolSignatureController;
use App\Http\Controllers\PublicSignatureController;
use App\Http\Controllers\PushSubscriptionController;
use App\Http\Controllers\QualificationController;
use App\Http\Controllers\Reporting\AbsencesReportController;
use App\Http\Controllers\Reporting\AttendanceReportController;
use App\Http\Controllers\Reporting\AuditActivityReportController;
use App\Http\Controllers\Reporting\BillingReportController;
use App\Http\Controllers\Reporting\CoverageReportController;
use App\Http\Controllers\Reporting\CustomerAnalysisReportController;
use App\Http\Controllers\Reporting\CustomerProjectReportController;
use App\Http\Controllers\Reporting\EntryTypeAnalysisReportController;
use App\Http\Controllers\Reporting\ExpenseReportController;
use App\Http\Controllers\Reporting\FleetReportController;
use App\Http\Controllers\Reporting\MaterialReportController;
use App\Http\Controllers\Reporting\MyMonthReportController;
use App\Http\Controllers\Reporting\MyYearReportController;
use App\Http\Controllers\Reporting\OnCallReportController;
use App\Http\Controllers\Reporting\OperationsReportController;
use App\Http\Controllers\Reporting\ProjectDetailsReportController;
use App\Http\Controllers\Reporting\QualificationReportController;
use App\Http\Controllers\Reporting\SicknessReportController;
use App\Http\Controllers\Reporting\WeekByUserReportController;
use App\Http\Controllers\Reporting\WorkBalanceReportController;
use App\Http\Controllers\RoomController;
use App\Http\Controllers\ScheduleController;
use App\Http\Controllers\ScheduledShiftController;
use App\Http\Controllers\ScheduleImportController;
use App\Http\Controllers\ShiftTypeController;
use App\Http\Controllers\SickLeaveController;
use App\Http\Controllers\StopwatchController;
use App\Http\Controllers\TagController;
use App\Http\Controllers\TaskController;
use App\Http\Controllers\TimeEntryCommentController;
use App\Http\Controllers\TimeEntryController;
use App\Http\Controllers\TimesheetController;
use App\Http\Controllers\TimesheetEntryController;
use App\Http\Controllers\TimesheetMaterialController;
use App\Http\Controllers\TimesheetSignatureController;
use App\Http\Controllers\TodayController;
use App\Http\Controllers\TourController;
use App\Http\Controllers\TravelLogController;
use App\Http\Controllers\UI\DateRangeController;
use App\Http\Controllers\VacationController;
use App\Http\Controllers\VehicleController;
use App\Http\Controllers\WeekController;
use App\Http\Controllers\WorkScheduleController;
use Illuminate\Support\Facades\Route;

// Projekt-Bindung: erlaubt numerische ID (Backward-Compat) ODER die
// zusammengesetzte URL "<kunde-slug>/<projekt-slug>" (Sentinel "intern"
// für Projekte ohne Kunden). Siehe Project::getRouteKey().
Route::pattern('project', '[0-9]+|[a-z0-9-]+/[a-z0-9-]+');

// Lizenz-Aktivierung (umgeht EnsureValidLicense via bypass_paths)
Route::get('/license', [LicenseController::class, 'show'])->name('license.show');
Route::post('/license', [LicenseController::class, 'store'])->middleware('throttle:6,1')->name('license.store');

// Startseite (öffentlich)
Route::get('/', HomeController::class)->name('home');

// Auth
Route::get('/login', [LoginController::class, 'showLoginForm'])->name('login')->middleware('guest');
Route::post('/login', [LoginController::class, 'login'])->middleware(['guest', 'throttle:login']);
Route::post('/logout', [LoginController::class, 'logout'])->name('logout');

Route::get('/register', [TenantRegistrationController::class, 'showForm'])->name('register')->middleware('guest');
Route::post('/register', [TenantRegistrationController::class, 'register'])->middleware(['guest', 'throttle:register']);

Route::post('/locale/{locale}', [LocaleController::class, 'switch'])->name('locale.switch');

// Öffentlicher ICS-Feed (nur Visibility=Public Events)
Route::get('calendar/public.ics', [IcsFeedController::class, 'public'])->name('events.ics.public');

// Tokenisierter persönlicher Schedule-Feed (Urlaube + Schichten).
Route::get('calendar/feed/{token}.ics', [IcsFeedController::class, 'personalSchedule'])
    ->name('calendar.feed.personal');

// Öffentlicher Stundenzettel-Sign-Link (Magic-Token)
Route::get('sign/timesheet/{token}', [PublicSignatureController::class, 'show'])->name('timesheets.public-sign');
Route::post('sign/timesheet/{token}', [PublicSignatureController::class, 'store'])->name('timesheets.public-sign.submit');
Route::get('sign/timesheet/thanks', [PublicSignatureController::class, 'thanks'])->name('timesheets.public-thanks');

// Öffentlicher Protokoll-Signaturlink (MVP-022 §3.3)
Route::get('sign/protocol/{token}', [PublicProtocolSignatureController::class, 'show'])
    ->name('protocols.public-sign');
Route::post('sign/protocol/{token}', [PublicProtocolSignatureController::class, 'store'])
    ->middleware('throttle:6,1')
    ->name('protocols.public-sign.submit');

// Tagebuch (nur für eingeloggte Benutzer)
Route::middleware('auth')->group(function () {
    // Diese Routen sind für jeden eingeloggten User erreichbar – auch für
    // Legacy-only-Accounts, die sonst keinen Zugriff auf das neue System
    // haben. Ohne sie könnten sie weder den Modus zurückwechseln noch ihr
    // Passwort/Profil verwalten.
    Route::post('/mode/{mode}', [HomeController::class, 'switchMode'])->name('mode.switch');

    Route::get('account/password', [AccountPasswordController::class, 'edit'])->name('account.password.edit');
    Route::post('account/password', [AccountPasswordController::class, 'update'])->middleware('throttle:password')->name('account.password.update');

    Route::get('account/profile', [ProfileController::class, 'edit'])->name('account.profile.edit');
    Route::put('account/profile', [ProfileController::class, 'update'])->name('account.profile.update');

    // Persönlicher Kalender-Feed (Token-Generierung + Subscribe-URL).
    Route::get('account/calendar', [CalendarFeedController::class, 'show'])
        ->name('account.calendar.show');
    Route::post('account/calendar/rotate', [CalendarFeedController::class, 'rotate'])
        ->name('account.calendar.rotate');
    Route::delete('account/calendar', [CalendarFeedController::class, 'revoke'])
        ->name('account.calendar.revoke');

    // Alle folgenden Routen gehören zum neuen System und sind nur für
    // dort freigeschaltete User (is_new_system=true) bzw. Admins erreichbar.
    Route::middleware('access.new')->group(function () {
        Route::get('dashboard', [DashboardController::class, '__invoke'])->name('dashboard');

        Route::get('diary/export.csv', [DiaryExportController::class, 'csv'])->name('diary.export.csv');
        Route::get('diary/export.pdf', [DiaryExportController::class, 'pdf'])->name('diary.export.pdf');

        Route::resource('holidays', HolidayController::class)->except('show');

        Route::resource('diary', DiaryController::class)->parameters(['diary' => 'diary']);
        Route::post('diary/{diary}/archive', [DiaryController::class, 'archive'])->name('diary.archive');
        Route::post('diary/{diary}/restore', [DiaryController::class, 'restore'])->name('diary.restore');
        Route::post('diary/{diary}/comments', [CommentController::class, 'store'])->name('diary.comments.store');
        Route::post('time-entries/{timeEntry}/comments', [TimeEntryCommentController::class, 'store'])->name('time-entries.comments.store');
        Route::put('comments/{comment}', [CommentController::class, 'update'])->name('comments.update');
        Route::delete('comments/{comment}', [CommentController::class, 'destroy'])->name('comments.destroy');

        Route::post('attachments/{type}/{id}', [AttachmentController::class, 'store'])
            ->whereIn('type', ['diary', 'comment', 'shift', 'assignment', 'task', 'customer', 'organization', 'user', 'asset'])
            ->whereNumber('id')
            ->name('attachments.store');
        Route::get('attachments/{attachment}/download', [AttachmentController::class, 'download'])->name('attachments.download');
        Route::delete('attachments/{attachment}', [AttachmentController::class, 'destroy'])->name('attachments.destroy');
        Route::delete('attachments/{type}/{id}/meta/{meta}', [AttachmentController::class, 'destroyMeta'])
            ->whereIn('type', ['organization', 'user'])
            ->whereNumber('id')
            ->whereIn('meta', ['logo', 'logo_dark', 'avatar'])
            ->name('attachments.destroyMeta');

        Route::get('week', WeekController::class)->name('week.index');

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

        Route::get('shifts', fn () => redirect()->route('duties.index'))->name('shifts.index');
        Route::get('assignments', fn () => redirect()->route('duties.index', ['tab' => 'notdienst']))->name('assignments.index');
        Route::resource('shifts', OnCallShiftController::class)->except(['show', 'index'])->parameters(['shifts' => 'shift']);
        Route::resource('assignments', EmergencyAssignmentController::class)->except(['show', 'index'])->parameters(['assignments' => 'assignment']);

        Route::get('vacations', fn () => redirect()->route('duties.index', ['tab' => 'urlaub']))->name('vacations.index');
        Route::resource('vacations', VacationController::class)->except(['show', 'index']);
        Route::patch('vacations/{vacation}/approve', [VacationController::class, 'approve'])->name('vacations.approve');
        Route::patch('vacations/{vacation}/reject', [VacationController::class, 'reject'])->name('vacations.reject');
        Route::get('vacations/{vacation}/reject-form', [VacationController::class, 'rejectForm'])->name('vacations.reject-form');
        Route::patch('vacations/{vacation}/cancel', [VacationController::class, 'cancel'])->name('vacations.cancel');

        Route::get('sick-leaves', fn () => redirect()->route('duties.index', ['tab' => 'krank']))->name('sick-leaves.index');
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

        Route::get('calendar/events.ics', [IcsFeedController::class, 'personal'])->name('events.ics.personal');

        // ── Kunden (Kimai-style customers) ──────────────────────────────────────
        Route::get('customers/export', [CustomerController::class, 'export'])->name('customers.export');
        Route::get('customers/import', [CustomerController::class, 'importForm'])->name('customers.import.form');
        Route::post('customers/import', [CustomerController::class, 'import'])->name('customers.import');
        Route::post('customers/lexoffice/push-all', [CustomerController::class, 'bulkPushLexoffice'])
            ->name('customers.lexoffice.push-all');
        Route::resource('customers', CustomerController::class);
        Route::post('customers/{customer}/archive', [CustomerController::class, 'archive'])->name('customers.archive');
        Route::post('customers/{customer}/restore', [CustomerController::class, 'restore'])->name('customers.restore');

        // ── Plugin-Aktionen (Lexoffice) ─────────────────────────────────────────
        Route::post('customers/{customer}/lexoffice/contact', [LexofficeCustomerController::class, 'pushContact'])
            ->name('customers.lexoffice.contact');
        Route::post('customers/{customer}/lexoffice/time-export', [LexofficeCustomerController::class, 'exportTime'])
            ->name('customers.lexoffice.time-export');

        // ── Plugin-Übersicht (Admin) ────────────────────────────────────────────
        Route::get('admin/plugins', [AdminPluginController::class, 'index'])->name('admin.plugins.index');

        Route::resource('projects', ProjectController::class);
        Route::resource('projects.milestones', MilestoneController::class)->except(['index', 'show']);
        Route::resource('projects.tasks', TaskController::class)->except(['index', 'show']);

        // ── Rechnungen / Invoicing ────────────────────────────────────
        Route::get('invoices', [InvoiceController::class, 'index'])->name('invoices.index');
        Route::get('invoices/create', [InvoiceController::class, 'create'])->name('invoices.create');
        Route::post('invoices', [InvoiceController::class, 'store'])->name('invoices.store');
        Route::get('invoices/{invoice}', [InvoiceController::class, 'show'])->name('invoices.show');
        Route::delete('invoices/{invoice}', [InvoiceController::class, 'destroy'])->name('invoices.destroy');
        Route::post('invoices/{invoice}/issue', [InvoiceController::class, 'issue'])->name('invoices.issue');
        Route::post('invoices/{invoice}/pay', [InvoiceController::class, 'pay'])->name('invoices.pay');
        Route::get('invoices/{invoice}/pdf', [InvoiceController::class, 'pdf'])->name('invoices.pdf');
        Route::get('invoices/{invoice}/expenses', [InvoiceController::class, 'expensesForm'])->name('invoices.expenses.form');
        Route::post('invoices/{invoice}/expenses', [InvoiceController::class, 'attachExpenses'])->name('invoices.expenses.attach');
        Route::patch('projects/{project}/tasks/{task}/complete', [TaskController::class, 'complete'])->name('projects.tasks.complete');
        Route::get('time-entries/create', [TimeEntryController::class, 'pick'])->name('time-entries.create');
        Route::resource('projects.time-entries', TimeEntryController::class)->except(['index', 'show']);
        Route::resource('projects.billing-rules', ProjectBillingRuleController::class)->except(['index', 'show', 'edit']);

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
        Route::post('open-issues', [OpenIssueController::class, 'store'])->name('open-issues.store');
        Route::put('open-issues/{issue}', [OpenIssueController::class, 'update'])->name('open-issues.update');
        Route::delete('open-issues/{issue}', [OpenIssueController::class, 'destroy'])->name('open-issues.destroy');
        Route::put('open-issues/{issue}/assignee', [OpenIssueController::class, 'assign'])->name('open-issues.assign');
        Route::post('open-issues/{issue}/transitions/{action}', [OpenIssueController::class, 'transition'])
            ->whereIn('action', ['start', 'block', 'unblock', 'complete', 'wontDo', 'reopen'])
            ->name('open-issues.transition');

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

        // ── Auswertungen ────────────────────────────────────────────────────────
        Route::get('reports/my-year', [MyYearReportController::class, 'index'])->name('reports.my-year');
        Route::get('reports/my-month', [MyMonthReportController::class, 'index'])->name('reports.my-month');
        Route::get('reports/customers', [CustomerAnalysisReportController::class, 'index'])->name('reports.customers');
        Route::get('reports/entry-types', [EntryTypeAnalysisReportController::class, 'index'])->name('reports.entry-types');
        Route::get('reports/customer-project', [CustomerProjectReportController::class, 'index'])->name('reports.customer-project');
        Route::get('reports/week-by-user', [WeekByUserReportController::class, 'index'])->name('reports.week-by-user');
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

        Route::resource('admin/entry-types', EntryTypeController::class)
            ->names('admin.entry-types')
            ->parameters(['entry-types' => 'entryType'])
            ->except('show');

        Route::resource('admin/expense-categories', ExpenseCategoryController::class)
            ->names('admin.expense-categories')
            ->parameters(['expense-categories' => 'expenseCategory'])
            ->except('show');

        Route::resource('admin/per-diem-rates', PerDiemRateController::class)
            ->names('admin.per-diem-rates')
            ->parameters(['per-diem-rates' => 'perDiemRate'])
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
