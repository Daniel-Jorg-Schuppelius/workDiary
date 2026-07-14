<?php
/*
 * Created on   : Sun May 03 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : api.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

use App\Http\Controllers\Api\{AssetStatusVisibilityController, AssetTimelineController, AttachmentController, AttendanceController, CommentController, CustomerController, DashboardController, DiaryController, EmergencyAssignmentController, FlexController, HookController, LocationController, MaterialController, MeController, OnCallShiftController, ProjectController, PushSubscriptionController, StopwatchController, TagController, TaskController, TimesheetController, TimesheetEntryController, TimesheetMaterialController};
use Illuminate\Support\Facades\Route;

// Siehe routes/web.php: Projekt-Bindung akzeptiert ID/Sqid oder
// "<kunde>/<projekt>". [A-Za-z0-9]+ setzt ein alphanumerisches Sqid-Alphabet
// voraus (abgesichert via SqidRoutePatternTest).
Route::pattern('project', '[A-Za-z0-9]+|[a-z0-9-]+/[a-z0-9-]+');

// Standort-Ingest von Geräte-Apps (OwnTracks/Traccar). Auth über Pro-Gerät-Token
// im Pfad statt Sanctum – die Apps können sich nicht interaktiv anmelden.
Route::match(['get', 'post'], 'location/ingest/{token}', [LocationController::class, 'ingest'])
    ->where('token', '[A-Za-z0-9]+')
    ->middleware('throttle:webhook-ingest')
    ->name('api.location.ingest');

// CTI-Webhook (Feature 056, MVP-118): Telefonanlagen/Provider (sipgate u. a.)
// POSTen Anruf-Ereignisse. Auth über einen Token im Pfad; nur Metadaten,
// nie Gesprächsinhalte.
Route::match(['get', 'post'], 'cti/webhook/{token}', \App\Http\Controllers\Api\CtiWebhookController::class)
    ->where('token', '[A-Za-z0-9_]+')
    ->middleware('throttle:webhook-ingest')
    ->name('api.cti.webhook');

// Terminal-Ingest (Feature 061, MVP-130): Hardware-Stempelterminals POSTen
// Badge-Scans. Auth über einen Gerätetoken im Pfad (Muster location/ingest).
Route::post('terminal/ingest/{token}', \App\Http\Controllers\Api\TerminalIngestController::class)
    ->where('token', '[A-Za-z0-9_]+')
    ->middleware('throttle:webhook-ingest')
    ->name('api.terminal.ingest');

Route::middleware('auth:sanctum')->group(function () {
    Route::get('me', MeController::class)->name('api.me');

    // Punktueller Browser-Standort-Stempel (navigator.geolocation).
    Route::post('location/stamp', [LocationController::class, 'stamp'])->middleware('ability:location:write')->name('api.location.stamp');

    // Aufträge (Feature 008 → Rang 60): Lesen vs. Schreiben getrennt gescopt.
    // Bestandstokens (`*`) matchen weiterhin jede Ability.
    Route::get('diary', [DiaryController::class, 'index'])->middleware('ability:diary:read')->name('api.diary.index');
    Route::get('diary/{diary}', [DiaryController::class, 'show'])->middleware('ability:diary:read')->name('api.diary.show');
    Route::post('diary', [DiaryController::class, 'store'])->middleware('ability:diary:write')->name('api.diary.store');
    Route::put('diary/{diary}', [DiaryController::class, 'update'])->middleware('ability:diary:write')->name('api.diary.update');
    Route::patch('diary/{diary}', [DiaryController::class, 'update'])->middleware('ability:diary:write');
    Route::delete('diary/{diary}', [DiaryController::class, 'destroy'])->middleware('ability:diary:write')->name('api.diary.destroy');
    Route::post('diary/{diary}/archive', [DiaryController::class, 'archive'])->middleware('ability:diary:write')->name('api.diary.archive');
    Route::post('diary/{diary}/restore', [DiaryController::class, 'restore'])->middleware('ability:diary:write')->name('api.diary.restore');

    // Ticketeingang (Feature 065, MVP-152): minimal, org-gebunden.
    Route::post('tickets', [\App\Http\Controllers\Api\TicketController::class, 'store'])->middleware('ability:tickets:write')->name('api.tickets.store');

    Route::post('diary/{diary}/comments', [CommentController::class, 'store'])->middleware('ability:comments:write')->name('api.diary.comments.store');
    Route::put('comments/{comment}', [CommentController::class, 'update'])->middleware('ability:comments:write')->name('api.comments.update');
    Route::delete('comments/{comment}', [CommentController::class, 'destroy'])->middleware('ability:comments:write')->name('api.comments.destroy');

    Route::post('attachments/{type}/{id}', [AttachmentController::class, 'store'])
        ->whereIn('type', ['diary', 'comment', 'shift', 'assignment', 'asset'])
        ->middleware('ability:attachments:write')
        ->name('api.attachments.store');
    Route::get('attachments/{attachment}/download', [AttachmentController::class, 'download'])->middleware('ability:attachments:read')->name('api.attachments.download');
    Route::delete('attachments/{attachment}', [AttachmentController::class, 'destroy'])->middleware('ability:attachments:write')->name('api.attachments.destroy');

    Route::get('tags', [TagController::class, 'index'])->middleware('ability:tags:read')->name('api.tags.index');
    Route::post('tags', [TagController::class, 'store'])->middleware('ability:tags:write')->name('api.tags.store');
    Route::put('tags/{tag}', [TagController::class, 'update'])->middleware('ability:tags:write')->name('api.tags.update');
    Route::patch('tags/{tag}', [TagController::class, 'update'])->middleware('ability:tags:write');
    Route::delete('tags/{tag}', [TagController::class, 'destroy'])->middleware('ability:tags:write')->name('api.tags.destroy');

    Route::get('shifts', [OnCallShiftController::class, 'index'])->middleware('ability:shifts:read')->name('api.shifts.index');
    Route::get('shifts/{shift}', [OnCallShiftController::class, 'show'])->middleware('ability:shifts:read')->name('api.shifts.show');

    Route::get('assignments', [EmergencyAssignmentController::class, 'index'])->middleware('ability:assignments:read')->name('api.assignments.index');
    Route::get('assignments/{assignment}', [EmergencyAssignmentController::class, 'show'])->middleware('ability:assignments:read')->name('api.assignments.show');

    Route::get('dashboard', DashboardController::class)->middleware('ability:dashboard:read')->name('api.dashboard');

    Route::get('assets/{asset}/timeline', AssetTimelineController::class)
        ->middleware('ability:assets:read')->name('api.assets.timeline');

    Route::get('assets/{asset}/status-visibility', AssetStatusVisibilityController::class)
        ->middleware('ability:assets:read')->name('api.assets.status-visibility');

    Route::get('push/vapid', [PushSubscriptionController::class, 'vapid'])->name('api.push.vapid');
    Route::post('push/subscribe', [PushSubscriptionController::class, 'store'])->middleware('ability:push:write')->name('api.push.subscribe');
    Route::delete('push/unsubscribe', [PushSubscriptionController::class, 'destroy'])->middleware('ability:push:write')->name('api.push.unsubscribe');

    // ── Stundenzettel / Material / Flex / Stoppuhr ─────────────────────────
    Route::get('timesheets', [TimesheetController::class, 'index'])->middleware('ability:timesheets:read')->name('api.timesheets.index');
    Route::post('projects/{project}/timesheets', [TimesheetController::class, 'store'])->middleware('ability:timesheets:write')->name('api.timesheets.store');
    Route::get('timesheets/{timesheet}', [TimesheetController::class, 'show'])->middleware('ability:timesheets:read')->name('api.timesheets.show');
    Route::put('timesheets/{timesheet}', [TimesheetController::class, 'update'])->middleware('ability:timesheets:write')->name('api.timesheets.update');
    Route::delete('timesheets/{timesheet}', [TimesheetController::class, 'destroy'])->middleware('ability:timesheets:write')->name('api.timesheets.destroy');
    Route::post('timesheets/{timesheet}/submit', [TimesheetController::class, 'submit'])->middleware('ability:timesheets:write')->name('api.timesheets.submit');
    Route::post('timesheets/{timesheet}/sign', [TimesheetController::class, 'sign'])->middleware('ability:timesheets:write')->name('api.timesheets.sign');
    Route::get('timesheets/{timesheet}/pdf', [TimesheetController::class, 'pdf'])->middleware('ability:timesheets:read')->name('api.timesheets.pdf');

    Route::get('timesheets/{timesheet}/entries', [TimesheetEntryController::class, 'index'])->middleware('ability:timesheets:read')->name('api.timesheets.entries.index');
    Route::post('timesheets/{timesheet}/entries', [TimesheetEntryController::class, 'store'])->middleware('ability:timesheets:write')->name('api.timesheets.entries.store');
    Route::put('timesheets/{timesheet}/entries/{entry}', [TimesheetEntryController::class, 'update'])->middleware('ability:timesheets:write')->name('api.timesheets.entries.update');
    Route::delete('timesheets/{timesheet}/entries/{entry}', [TimesheetEntryController::class, 'destroy'])->middleware('ability:timesheets:write')->name('api.timesheets.entries.destroy');

    Route::get('timesheets/{timesheet}/materials', [TimesheetMaterialController::class, 'index'])->middleware('ability:timesheets:read')->name('api.timesheets.materials.index');
    Route::post('timesheets/{timesheet}/materials', [TimesheetMaterialController::class, 'store'])->middleware('ability:timesheets:write')->name('api.timesheets.materials.store');
    Route::put('timesheets/{timesheet}/materials/{usage}', [TimesheetMaterialController::class, 'update'])->middleware('ability:timesheets:write')->name('api.timesheets.materials.update');
    Route::delete('timesheets/{timesheet}/materials/{usage}', [TimesheetMaterialController::class, 'destroy'])->middleware('ability:timesheets:write')->name('api.timesheets.materials.destroy');

    Route::get('materials', [MaterialController::class, 'index'])->middleware('ability:materials:read')->name('api.materials.index');

    Route::get('stopwatch', [StopwatchController::class, 'current'])->middleware('ability:stopwatch:read')->name('api.stopwatch.current');
    Route::post('stopwatch/start', [StopwatchController::class, 'start'])->middleware('ability:stopwatch:write')->name('api.stopwatch.start');
    Route::post('stopwatch/stop', [StopwatchController::class, 'stop'])->middleware('ability:stopwatch:write')->name('api.stopwatch.stop');

    Route::get('attendance/current', [AttendanceController::class, 'current'])->middleware('ability:attendance:read')->name('api.attendance.current');
    Route::post('attendance/clock-in', [AttendanceController::class, 'clockIn'])->middleware('ability:attendance:write')->name('api.attendance.clock-in');
    Route::post('attendance/clock-out', [AttendanceController::class, 'clockOut'])->middleware('ability:attendance:write')->name('api.attendance.clock-out');

    Route::get('flex/summary', [FlexController::class, 'summary'])->middleware('ability:flex:read')->name('api.flex.summary');

    // ── Customers / Projects / Tasks (Kimai-Parity) ────────────────────────
    // apiResource in Einzelrouten aufgelöst, um Lesen/Schreiben getrennt zu scopen.
    Route::get('customers', [CustomerController::class, 'index'])->middleware('ability:customers:read')->name('api.customers.index');
    Route::get('customers/{customer}', [CustomerController::class, 'show'])->middleware('ability:customers:read')->name('api.customers.show');
    Route::post('customers', [CustomerController::class, 'store'])->middleware('ability:customers:write')->name('api.customers.store');
    Route::put('customers/{customer}', [CustomerController::class, 'update'])->middleware('ability:customers:write')->name('api.customers.update');
    Route::patch('customers/{customer}', [CustomerController::class, 'update'])->middleware('ability:customers:write');
    Route::delete('customers/{customer}', [CustomerController::class, 'destroy'])->middleware('ability:customers:write')->name('api.customers.destroy');

    Route::get('projects', [ProjectController::class, 'index'])->middleware('ability:projects:read')->name('api.projects.index');
    Route::get('projects/{project}', [ProjectController::class, 'show'])->middleware('ability:projects:read')->name('api.projects.show');
    Route::post('projects', [ProjectController::class, 'store'])->middleware('ability:projects:write')->name('api.projects.store');
    Route::put('projects/{project}', [ProjectController::class, 'update'])->middleware('ability:projects:write')->name('api.projects.update');
    Route::patch('projects/{project}', [ProjectController::class, 'update'])->middleware('ability:projects:write');
    Route::delete('projects/{project}', [ProjectController::class, 'destroy'])->middleware('ability:projects:write')->name('api.projects.destroy');
    Route::get('tasks', [TaskController::class, 'index'])->middleware('ability:tasks:read')->name('api.tasks.index');
    Route::get('tasks/{task}', [TaskController::class, 'show'])->middleware('ability:tasks:read')->name('api.tasks.show');
    Route::put('tasks/{task}', [TaskController::class, 'update'])->middleware('ability:tasks:write')->name('api.tasks.update');
    Route::delete('tasks/{task}', [TaskController::class, 'destroy'])->middleware('ability:tasks:write')->name('api.tasks.destroy');
    Route::post('projects/{project}/tasks', [TaskController::class, 'store'])->middleware('ability:tasks:write')->name('api.tasks.store');

    // ── REST-Hooks für n8n/Make/Zapier (Feature 008 → Rang 61) ─────────────
    // Eigene Ability `hooks:manage`; Zustellung/Signatur/Auto-Disable liegen in
    // der bestehenden Webhook-Infrastruktur. `events` VOR `{hook}` registrieren.
    Route::middleware('ability:hooks:manage')->group(function (): void {
        Route::get('hooks', [HookController::class, 'index'])->name('api.hooks.index');
        Route::get('hooks/events', [HookController::class, 'events'])->name('api.hooks.events');
        Route::post('hooks', [HookController::class, 'store'])->name('api.hooks.store');
        Route::post('hooks/{hook}/test', [HookController::class, 'test'])->name('api.hooks.test');
        Route::delete('hooks/{hook}', [HookController::class, 'destroy'])->name('api.hooks.destroy');
    });
});
