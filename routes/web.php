<?php

use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\DiaryController;
use App\Http\Controllers\HomeController;
use App\Http\Controllers\LegacyAccountController;
use App\Http\Controllers\LegacyArchiveController;
use App\Http\Controllers\LegacyCallcenterController;
use App\Http\Controllers\LegacyDiaryController;
use App\Http\Controllers\LegacyNotdienstController;
use App\Http\Controllers\LegacyOnCallController;
use App\Http\Controllers\LegacyUserAdminController;
use App\Http\Controllers\LocaleController;
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

    Route::get('legacy/diary/week', [LegacyDiaryController::class, 'week'])->name('legacy.diary.week');
    Route::get('legacy/overview', function () {
        return redirect()->route('legacy.diary.index', ['zeitpunkt' => 1, 'status' => 2]);
    })->name('legacy.overview.index');
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

    Route::get('legacy/archive', [LegacyArchiveController::class, 'index'])->name('legacy.archive.index');
    Route::get('legacy/archive/week', [LegacyArchiveController::class, 'week'])->name('legacy.archive.week');
    Route::post('legacy/archive/run', [LegacyArchiveController::class, 'run'])->name('legacy.archive.run');

    Route::resource('diary', DiaryController::class)->parameters(['diary' => 'diary']);
});
