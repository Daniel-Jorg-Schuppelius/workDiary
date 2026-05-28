<?php
/*
 * Created on   : Sun May 03 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : api.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

use App\Http\Controllers\Api\{AssetStatusVisibilityController, AssetTimelineController, AttachmentController, AttendanceController, CommentController, CustomerController, DashboardController, DiaryController, EmergencyAssignmentController, FlexController, MaterialController, MeController, OnCallShiftController, ProjectController, PushSubscriptionController, StopwatchController, TagController, TaskController, TimesheetController, TimesheetEntryController, TimesheetMaterialController};
use Illuminate\Support\Facades\Route;

// Siehe routes/web.php: Projekt-Bindung akzeptiert ID oder "<kunde>/<projekt>".
Route::pattern('project', '[0-9]+|[a-z0-9-]+/[a-z0-9-]+');

Route::middleware('auth:sanctum')->group(function () {
    Route::get('me', MeController::class)->name('api.me');

    Route::get('diary', [DiaryController::class, 'index'])->name('api.diary.index');
    Route::post('diary', [DiaryController::class, 'store'])->name('api.diary.store');
    Route::get('diary/{diary}', [DiaryController::class, 'show'])->name('api.diary.show');
    Route::put('diary/{diary}', [DiaryController::class, 'update'])->name('api.diary.update');
    Route::patch('diary/{diary}', [DiaryController::class, 'update']);
    Route::delete('diary/{diary}', [DiaryController::class, 'destroy'])->name('api.diary.destroy');
    Route::post('diary/{diary}/archive', [DiaryController::class, 'archive'])->name('api.diary.archive');
    Route::post('diary/{diary}/restore', [DiaryController::class, 'restore'])->name('api.diary.restore');

    Route::post('diary/{diary}/comments', [CommentController::class, 'store'])->name('api.diary.comments.store');
    Route::put('comments/{comment}', [CommentController::class, 'update'])->name('api.comments.update');
    Route::delete('comments/{comment}', [CommentController::class, 'destroy'])->name('api.comments.destroy');

    Route::post('attachments/{type}/{id}', [AttachmentController::class, 'store'])
        ->whereIn('type', ['diary', 'comment', 'shift', 'assignment', 'asset'])
        ->name('api.attachments.store');
    Route::get('attachments/{attachment}/download', [AttachmentController::class, 'download'])->name('api.attachments.download');
    Route::delete('attachments/{attachment}', [AttachmentController::class, 'destroy'])->name('api.attachments.destroy');

    Route::apiResource('tags', TagController::class)->except('show')->names('api.tags');

    Route::get('shifts', [OnCallShiftController::class, 'index'])->name('api.shifts.index');
    Route::get('shifts/{shift}', [OnCallShiftController::class, 'show'])->name('api.shifts.show');

    Route::get('assignments', [EmergencyAssignmentController::class, 'index'])->name('api.assignments.index');
    Route::get('assignments/{assignment}', [EmergencyAssignmentController::class, 'show'])->name('api.assignments.show');

    Route::get('dashboard', DashboardController::class)->name('api.dashboard');

    Route::get('assets/{asset}/timeline', AssetTimelineController::class)
        ->name('api.assets.timeline');

    Route::get('assets/{asset}/status-visibility', AssetStatusVisibilityController::class)
        ->name('api.assets.status-visibility');

    Route::get('push/vapid', [PushSubscriptionController::class, 'vapid'])->name('api.push.vapid');
    Route::post('push/subscribe', [PushSubscriptionController::class, 'store'])->name('api.push.subscribe');
    Route::delete('push/unsubscribe', [PushSubscriptionController::class, 'destroy'])->name('api.push.unsubscribe');

    // ── Stundenzettel / Material / Flex / Stoppuhr ─────────────────────────
    Route::get('timesheets', [TimesheetController::class, 'index'])->name('api.timesheets.index');
    Route::post('projects/{project}/timesheets', [TimesheetController::class, 'store'])->name('api.timesheets.store');
    Route::get('timesheets/{timesheet}', [TimesheetController::class, 'show'])->name('api.timesheets.show');
    Route::put('timesheets/{timesheet}', [TimesheetController::class, 'update'])->name('api.timesheets.update');
    Route::delete('timesheets/{timesheet}', [TimesheetController::class, 'destroy'])->name('api.timesheets.destroy');
    Route::post('timesheets/{timesheet}/submit', [TimesheetController::class, 'submit'])->name('api.timesheets.submit');
    Route::post('timesheets/{timesheet}/sign', [TimesheetController::class, 'sign'])->name('api.timesheets.sign');
    Route::get('timesheets/{timesheet}/pdf', [TimesheetController::class, 'pdf'])->name('api.timesheets.pdf');

    Route::get('timesheets/{timesheet}/entries', [TimesheetEntryController::class, 'index'])->name('api.timesheets.entries.index');
    Route::post('timesheets/{timesheet}/entries', [TimesheetEntryController::class, 'store'])->name('api.timesheets.entries.store');
    Route::put('timesheets/{timesheet}/entries/{entry}', [TimesheetEntryController::class, 'update'])->name('api.timesheets.entries.update');
    Route::delete('timesheets/{timesheet}/entries/{entry}', [TimesheetEntryController::class, 'destroy'])->name('api.timesheets.entries.destroy');

    Route::get('timesheets/{timesheet}/materials', [TimesheetMaterialController::class, 'index'])->name('api.timesheets.materials.index');
    Route::post('timesheets/{timesheet}/materials', [TimesheetMaterialController::class, 'store'])->name('api.timesheets.materials.store');
    Route::put('timesheets/{timesheet}/materials/{usage}', [TimesheetMaterialController::class, 'update'])->name('api.timesheets.materials.update');
    Route::delete('timesheets/{timesheet}/materials/{usage}', [TimesheetMaterialController::class, 'destroy'])->name('api.timesheets.materials.destroy');

    Route::get('materials', [MaterialController::class, 'index'])->name('api.materials.index');

    Route::get('stopwatch', [StopwatchController::class, 'current'])->name('api.stopwatch.current');
    Route::post('stopwatch/start', [StopwatchController::class, 'start'])->name('api.stopwatch.start');
    Route::post('stopwatch/stop', [StopwatchController::class, 'stop'])->name('api.stopwatch.stop');

    Route::get('attendance/current', [AttendanceController::class, 'current'])->name('api.attendance.current');
    Route::post('attendance/clock-in', [AttendanceController::class, 'clockIn'])->name('api.attendance.clock-in');
    Route::post('attendance/clock-out', [AttendanceController::class, 'clockOut'])->name('api.attendance.clock-out');

    Route::get('flex/summary', [FlexController::class, 'summary'])->name('api.flex.summary');

    // ── Customers / Projects / Tasks (Kimai-Parity) ────────────────────────
    Route::apiResource('customers', CustomerController::class)->names('api.customers');
    Route::apiResource('projects', ProjectController::class)->names('api.projects');
    Route::get('tasks', [TaskController::class, 'index'])->name('api.tasks.index');
    Route::get('tasks/{task}', [TaskController::class, 'show'])->name('api.tasks.show');
    Route::put('tasks/{task}', [TaskController::class, 'update'])->name('api.tasks.update');
    Route::delete('tasks/{task}', [TaskController::class, 'destroy'])->name('api.tasks.destroy');
    Route::post('projects/{project}/tasks', [TaskController::class, 'store'])->name('api.tasks.store');
});
