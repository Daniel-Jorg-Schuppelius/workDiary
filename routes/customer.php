<?php
/*
 * Created on   : Sat May 30 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : customer.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

use App\Http\Controllers\CustomerPortal\{DashboardController, DiaryController, InvoiceController, LoginController, OpenIssueController, TimeEntryController, TwoFactorChallengeController, TwoFactorController};
use Illuminate\Support\Facades\Route;

/**
 * Customer-Portal (Rolle `kunde`). Eigener Guard `customer` mit dediziertem
 * Provider (siehe App\Auth\CustomerUserProvider). Interne Routen sind durch
 * die Provider-Trennung technisch nicht erreichbar.
 */
Route::prefix('customer-portal')->name('customer.')->group(function (): void {
    Route::get('/login', [LoginController::class, 'showLoginForm'])->name('login');
    Route::post('/login', [LoginController::class, 'login'])->middleware('throttle:login')->name('login.attempt');
    Route::post('/logout', [LoginController::class, 'logout'])->name('logout');

    // Zweiter Login-Schritt (Zwei-Faktor): session-basiert, kein auth-Guard.
    Route::get('/two-factor-challenge', [TwoFactorChallengeController::class, 'create'])->name('two-factor.login');
    Route::post('/two-factor-challenge', [TwoFactorChallengeController::class, 'store'])->middleware('throttle:login')->name('two-factor.login.attempt');
    Route::post('/two-factor-challenge/email', [TwoFactorChallengeController::class, 'email'])->middleware('throttle:login')->name('two-factor.login.email');
    Route::post('/two-factor-challenge/webauthn/options', [TwoFactorChallengeController::class, 'webauthnOptions'])->name('two-factor.login.webauthn.options');
    Route::post('/two-factor-challenge/webauthn', [TwoFactorChallengeController::class, 'webauthnVerify'])->middleware('throttle:login')->name('two-factor.login.webauthn');

    Route::middleware(['auth:customer', 'two-factor.setup:customer'])->group(function (): void {
        Route::get('/', DashboardController::class)->name('dashboard');
        Route::get('/diary', [DiaryController::class, 'index'])->name('diary.index');
        Route::get('/time-entries', [TimeEntryController::class, 'index'])->name('time-entries.index');
        Route::get('/invoices', [InvoiceController::class, 'index'])->name('invoices.index');
        Route::get('/open-issues', [OpenIssueController::class, 'index'])->name('open-issues.index');

        // 2FA-Selbstverwaltung.
        Route::get('/two-factor', [TwoFactorController::class, 'show'])->name('2fa.show');
        Route::post('/two-factor', [TwoFactorController::class, 'enable'])->name('2fa.enable');
        Route::post('/two-factor/confirm', [TwoFactorController::class, 'confirm'])->name('2fa.confirm');
        Route::post('/two-factor/recovery-codes', [TwoFactorController::class, 'regenerateRecoveryCodes'])->name('2fa.recovery');
        Route::post('/two-factor/email', [TwoFactorController::class, 'enableEmail'])->name('2fa.email.enable');
        Route::post('/two-factor/email/resend', [TwoFactorController::class, 'resendEmailCode'])->name('2fa.email.resend');
        Route::post('/two-factor/email/confirm', [TwoFactorController::class, 'confirmEmail'])->name('2fa.email.confirm');
        Route::post('/two-factor/webauthn/options', [TwoFactorController::class, 'webauthnOptions'])->name('2fa.webauthn.options');
        Route::post('/two-factor/webauthn', [TwoFactorController::class, 'webauthnRegister'])->name('2fa.webauthn.register');
        Route::delete('/two-factor/credential/{credential}', [TwoFactorController::class, 'removeCredential'])->name('2fa.credential.destroy');
        Route::delete('/two-factor', [TwoFactorController::class, 'disable'])->name('2fa.disable');
    });
});
