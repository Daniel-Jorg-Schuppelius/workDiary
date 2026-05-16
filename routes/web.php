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
use App\Http\Controllers\Admin\PluginController as AdminPluginController;
use App\Http\Controllers\ApiTokenController;
use App\Http\Controllers\ArchiveController;
use App\Http\Controllers\AttachmentController;
use App\Http\Controllers\AuditLogController;
use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\Auth\TenantRegistrationController;
use App\Http\Controllers\CommentController;
use App\Http\Controllers\CoverageRequirementController;
use App\Http\Controllers\CustomerController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\DiaryController;
use App\Http\Controllers\DiaryExportController;
use App\Http\Controllers\DutyController;
use App\Http\Controllers\DutyPlanController;
use App\Http\Controllers\EmergencyAssignmentController;
use App\Http\Controllers\FlexController;
use App\Http\Controllers\HolidayController;
use App\Http\Controllers\HomeController;
use App\Http\Controllers\InvoiceController;
use App\Http\Controllers\KanbanController;
use App\Http\Controllers\LocaleController;
use App\Http\Controllers\MaterialController;
use App\Http\Controllers\MilestoneController;
use App\Http\Controllers\OnCallShiftController;
use App\Http\Controllers\OrganizationController;
use App\Http\Controllers\OrgMemberController;
use App\Http\Controllers\Plugins\LexofficeCustomerController;
use App\Http\Controllers\PrintController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\ProjectBillingRuleController;
use App\Http\Controllers\ProjectController;
use App\Http\Controllers\PublicSignatureController;
use App\Http\Controllers\PushSubscriptionController;
use App\Http\Controllers\QualificationController;
use App\Http\Controllers\Reporting\CustomerProjectReportController;
use App\Http\Controllers\Reporting\MyMonthReportController;
use App\Http\Controllers\Reporting\MyYearReportController;
use App\Http\Controllers\Reporting\ProjectDetailsReportController;
use App\Http\Controllers\Reporting\WeekByUserReportController;
use App\Http\Controllers\ScheduleController;
use App\Http\Controllers\ScheduledShiftController;
use App\Http\Controllers\ScheduleImportController;
use App\Http\Controllers\ShiftTypeController;
use App\Http\Controllers\StopwatchController;
use App\Http\Controllers\TagController;
use App\Http\Controllers\TaskController;
use App\Http\Controllers\TimeEntryController;
use App\Http\Controllers\TimesheetController;
use App\Http\Controllers\TimesheetEntryController;
use App\Http\Controllers\TimesheetMaterialController;
use App\Http\Controllers\TimesheetSignatureController;
use App\Http\Controllers\UI\DateRangeController;
use App\Http\Controllers\VacationController;
use App\Http\Controllers\WeekController;
use App\Http\Controllers\WorkScheduleController;
use Illuminate\Support\Facades\Route;

// Startseite (öffentlich)
Route::get('/', HomeController::class)->name('home');

// Auth
Route::get('/login', [LoginController::class, 'showLoginForm'])->name('login')->middleware('guest');
Route::post('/login', [LoginController::class, 'login'])->middleware(['guest', 'throttle:login']);
Route::post('/logout', [LoginController::class, 'logout'])->name('logout');

Route::get('/register', [TenantRegistrationController::class, 'showForm'])->name('register')->middleware('guest');
Route::post('/register', [TenantRegistrationController::class, 'register'])->middleware(['guest', 'throttle:register']);

Route::post('/locale/{locale}', [LocaleController::class, 'switch'])->name('locale.switch');

// Öffentlicher Stundenzettel-Sign-Link (Magic-Token)
Route::get('sign/timesheet/{token}', [PublicSignatureController::class, 'show'])->name('timesheets.public-sign');
Route::post('sign/timesheet/{token}', [PublicSignatureController::class, 'store'])->name('timesheets.public-sign.submit');
Route::get('sign/timesheet/thanks', [PublicSignatureController::class, 'thanks'])->name('timesheets.public-thanks');

// Tagebuch (nur für eingeloggte Benutzer)
Route::middleware('auth')->group(function () {
    Route::post('/mode/{mode}', [HomeController::class, 'switchMode'])->name('mode.switch');

    Route::get('dashboard', [DashboardController::class, '__invoke'])->name('dashboard');

    Route::get('account/password', [AccountPasswordController::class, 'edit'])->name('account.password.edit');
    Route::post('account/password', [AccountPasswordController::class, 'update'])->middleware('throttle:password')->name('account.password.update');

    Route::get('account/profile', [ProfileController::class, 'edit'])->name('account.profile.edit');
    Route::put('account/profile', [ProfileController::class, 'update'])->name('account.profile.update');

    Route::get('diary/export.csv', [DiaryExportController::class, 'csv'])->name('diary.export.csv');
    Route::get('diary/export.pdf', [DiaryExportController::class, 'pdf'])->name('diary.export.pdf');

    Route::resource('holidays', HolidayController::class)->except('show');

    Route::resource('diary', DiaryController::class)->parameters(['diary' => 'diary']);
    Route::post('diary/{diary}/archive', [DiaryController::class, 'archive'])->name('diary.archive');
    Route::post('diary/{diary}/restore', [DiaryController::class, 'restore'])->name('diary.restore');
    Route::post('diary/{diary}/comments', [CommentController::class, 'store'])->name('diary.comments.store');
    Route::put('comments/{comment}', [CommentController::class, 'update'])->name('comments.update');
    Route::delete('comments/{comment}', [CommentController::class, 'destroy'])->name('comments.destroy');

    Route::post('attachments/{type}/{id}', [AttachmentController::class, 'store'])
        ->whereIn('type', ['diary', 'comment', 'shift', 'assignment'])
        ->whereNumber('id')
        ->name('attachments.store');
    Route::get('attachments/{attachment}/download', [AttachmentController::class, 'download'])->name('attachments.download');
    Route::delete('attachments/{attachment}', [AttachmentController::class, 'destroy'])->name('attachments.destroy');

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

    Route::resource('tags', TagController::class)->except('show');

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
    Route::patch('projects/{project}/tasks/{task}/complete', [TaskController::class, 'complete'])->name('projects.tasks.complete');
    Route::resource('projects.time-entries', TimeEntryController::class)->except(['index', 'show']);
    Route::resource('projects.billing-rules', ProjectBillingRuleController::class)->except(['index', 'show', 'create', 'edit']);

    // ── Stundenzettel (an Projekt gekoppelt) ────────────────────────────────
    Route::get('timesheets', [TimesheetController::class, 'index'])->name('timesheets.index');
    Route::post('timesheets/quick', [TimesheetController::class, 'storeQuick'])->name('timesheets.quick');
    Route::resource('projects.timesheets', TimesheetController::class)
        ->parameters(['timesheets' => 'timesheet'])
        ->except(['index']);
    Route::post('projects/{project}/timesheets/{timesheet}/submit', [TimesheetController::class, 'submit'])
        ->name('projects.timesheets.submit');

    Route::post('projects/{project}/timesheets/{timesheet}/entries', [TimesheetEntryController::class, 'store'])->name('projects.timesheets.entries.store');
    Route::put('projects/{project}/timesheets/{timesheet}/entries/{entry}', [TimesheetEntryController::class, 'update'])->name('projects.timesheets.entries.update');
    Route::delete('projects/{project}/timesheets/{timesheet}/entries/{entry}', [TimesheetEntryController::class, 'destroy'])->name('projects.timesheets.entries.destroy');

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

    // ── Globale Zeitauswahl (Header-Widget) ─────────────────────────────────
    Route::post('ui/date-range', [DateRangeController::class, 'update'])->name('ui.date-range.update');
    Route::post('ui/date-range/shift', [DateRangeController::class, 'shift'])->name('ui.date-range.shift');

    // ── Gleitzeit ───────────────────────────────────────────────────────────
    Route::get('flex', [FlexController::class, 'index'])->name('flex.index');
    Route::get('flex/admin', [FlexController::class, 'admin'])->name('flex.admin');

    // ── Auswertungen ────────────────────────────────────────────────────────
    Route::get('reports/my-year', [MyYearReportController::class, 'index'])->name('reports.my-year');
    Route::get('reports/my-month', [MyMonthReportController::class, 'index'])->name('reports.my-month');
    Route::get('reports/customer-project', [CustomerProjectReportController::class, 'index'])->name('reports.customer-project');
    Route::get('reports/week-by-user', [WeekByUserReportController::class, 'index'])->name('reports.week-by-user');
    Route::get('reports/project-details', [ProjectDetailsReportController::class, 'index'])->name('reports.project-details');

    // ── Material-Stamm (Admin) ──────────────────────────────────────────────
    Route::resource('materials', MaterialController::class)->except('show');

    // ── Arbeitszeit-Modell ──────────────────────────────────────────────────
    Route::get('account/work-schedule', [WorkScheduleController::class, 'self'])->name('account.work-schedule');
    Route::get('users/{user}/work-schedule', [WorkScheduleController::class, 'edit'])->name('users.work-schedule.edit');
    Route::put('users/{user}/work-schedule', [WorkScheduleController::class, 'update'])->name('users.work-schedule.update');

    Route::get('archive', [ArchiveController::class, 'index'])->name('archive.index');
    Route::post('archive/run', [ArchiveController::class, 'run'])->name('archive.run');

    Route::get('audit', [AuditLogController::class, 'index'])->name('audit.index');

    Route::resource('admin/organizations', OrganizationController::class)
        ->names('admin.organizations')
        ->parameters(['organizations' => 'organization']);

    Route::resource('org/members', OrgMemberController::class)
        ->names('org.members')
        ->parameters(['members' => 'member'])
        ->except('show');

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
