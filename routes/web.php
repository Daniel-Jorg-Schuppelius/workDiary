<?php

use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\AccountPasswordController;
use App\Http\Controllers\ApiTokenController;
use App\Http\Controllers\ArchiveController;
use App\Http\Controllers\AttachmentController;
use App\Http\Controllers\AuditLogController;
use App\Http\Controllers\CommentController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\DiaryController;
use App\Http\Controllers\DiaryExportController;
use App\Http\Controllers\HomeController;
use App\Http\Controllers\KanbanController;
use App\Http\Controllers\LegacyAccountController;
use App\Http\Controllers\LegacyArchiveController;
use App\Http\Controllers\LegacyCallcenterController;
use App\Http\Controllers\LegacyDiaryController;
use App\Http\Controllers\LegacyMigrationController;
use App\Http\Controllers\LegacyNotdienstController;
use App\Http\Controllers\LegacyOnCallController;
use App\Http\Controllers\LegacyUserAdminController;
use App\Http\Controllers\LocaleController;
use App\Http\Controllers\PushSubscriptionController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\ProjectController;
use App\Http\Controllers\TagController;
use App\Http\Controllers\WeekController;
use App\Http\Controllers\OnCallShiftController;
use App\Http\Controllers\EmergencyAssignmentController;
use App\Http\Controllers\DutyController;
use Illuminate\Support\Facades\Route;

// Startseite (öffentlich)
Route::get('/', HomeController::class)->name('home');

// Auth
Route::get('/login', [LoginController::class, 'showLoginForm'])->name('login')->middleware('guest');
Route::post('/login', [LoginController::class, 'login'])->middleware('guest');
Route::post('/logout', [LoginController::class, 'logout'])->name('logout');

Route::post('/locale/{locale}', [LocaleController::class, 'switch'])->name('locale.switch');

Route::prefix('legacy/callcenter')->name('legacy.callcenter.')->group(function (): void {
    Route::get('login', [LegacyCallcenterController::class, 'showLoginForm'])->name('login');
    Route::post('login', [LegacyCallcenterController::class, 'login'])->name('login.submit');
    Route::post('logout', [LegacyCallcenterController::class, 'logout'])->name('logout');
    Route::get('notdienst', [LegacyCallcenterController::class, 'notdienstPlan'])
        ->middleware('legacy.callcenter.auth')
        ->name('notdienst');
});

// Tagebuch (nur für eingeloggte Benutzer)
Route::middleware('auth')->group(function () {
    Route::post('/mode/{mode}', [HomeController::class, 'switchMode'])->name('mode.switch');

    Route::get('dashboard', [DashboardController::class, '__invoke'])->name('dashboard');

    Route::get('account/password', [AccountPasswordController::class, 'edit'])->name('account.password.edit');
    Route::post('account/password', [AccountPasswordController::class, 'update'])->name('account.password.update');

    Route::get('account/profile', [ProfileController::class, 'edit'])->name('account.profile.edit');
    Route::put('account/profile', [ProfileController::class, 'update'])->name('account.profile.update');

    Route::get('diary/export.csv', [DiaryExportController::class, 'csv'])->name('diary.export.csv');
    Route::get('diary/export.pdf', [DiaryExportController::class, 'pdf'])->name('diary.export.pdf');

    Route::get('admin/legacy-migration', [LegacyMigrationController::class, 'index'])->name('admin.legacy-migration.index');
    Route::post('admin/legacy-migration', [LegacyMigrationController::class, 'run'])->name('admin.legacy-migration.run');

    Route::get('legacy/diary/week', [LegacyDiaryController::class, 'week'])->name('legacy.diary.week');
    Route::get('legacy/overview', function () {
        return redirect()->route('legacy.diary.index', ['status' => 2]);
    })->name('legacy.overview.index');

    Route::middleware('legacy.write')->group(function () {
        Route::get('legacy/account/password', [LegacyAccountController::class, 'editPassword'])->name('legacy.account.password.edit');
        Route::post('legacy/account/password', [LegacyAccountController::class, 'updatePassword'])->name('legacy.account.password.update');
        Route::resource('legacy/users', LegacyUserAdminController::class)
            ->except(['show'])
            ->names('legacy.users')
            ->parameters(['users' => 'user']);

        Route::resource('legacy/diary', LegacyDiaryController::class)
            ->names('legacy.diary')
            ->parameters(['diary' => 'entry']);

        Route::post('legacy/diary-bulk', [LegacyDiaryController::class, 'bulk'])->name('legacy.diary.bulk');

        Route::resource('legacy/on-call', LegacyOnCallController::class)
            ->except(['show'])
            ->names('legacy.oncall')
            ->parameters(['on-call' => 'oncall']);

        Route::resource('legacy/notdienst', LegacyNotdienstController::class)
            ->except(['show'])
            ->names('legacy.notdienst')
            ->parameters(['notdienst' => 'notdienst']);
    });

    Route::get('legacy/archive', [LegacyArchiveController::class, 'index'])->name('legacy.archive.index');
    Route::get('legacy/archive/week', [LegacyArchiveController::class, 'week'])->name('legacy.archive.week');
    Route::post('legacy/archive/run', [LegacyArchiveController::class, 'run'])->name('legacy.archive.run');

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
    Route::get('shifts', fn() => redirect()->route('duties.index'))->name('shifts.index');
    Route::get('assignments', fn() => redirect()->route('duties.index', ['tab' => 'notdienst']))->name('assignments.index');
    Route::resource('shifts', OnCallShiftController::class)->except(['show', 'index'])->parameters(['shifts' => 'shift']);
    Route::resource('assignments', EmergencyAssignmentController::class)->except(['show', 'index'])->parameters(['assignments' => 'assignment']);

    Route::resource('tags', TagController::class)->except('show');

    Route::resource('projects', ProjectController::class);

    Route::post('archive/run', [ArchiveController::class, 'run'])->name('archive.run');

    Route::get('audit', [AuditLogController::class, 'index'])->name('audit.index');

    Route::get('push/vapid', [PushSubscriptionController::class, 'vapid'])->name('push.vapid');
    Route::post('push/subscribe', [PushSubscriptionController::class, 'store'])->name('push.subscribe');
    Route::delete('push/unsubscribe', [PushSubscriptionController::class, 'destroy'])->name('push.unsubscribe');

    Route::get('profile/api-tokens', [ApiTokenController::class, 'index'])->name('profile.api-tokens.index');
    Route::post('profile/api-tokens', [ApiTokenController::class, 'store'])->name('profile.api-tokens.store');
    Route::delete('profile/api-tokens/{id}', [ApiTokenController::class, 'destroy'])
        ->whereNumber('id')
        ->name('profile.api-tokens.destroy');
});
