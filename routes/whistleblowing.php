<?php
/*
 * Created on   : Mon Jun 08 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : whistleblowing.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

use App\Http\Controllers\Whistleblowing\{PublicPortalController, PublicReportController, ReporterMailboxController};
use App\Http\Middleware\Whistleblowing\{EnsureMailboxSession, ResolvePortal};
use Illuminate\Support\Facades\Route;

/**
 * Oeffentliches Meldeportal (Phase 2). Laeuft im schlanken `whistleblowing`-
 * Middleware-Stack (kein Auth/Org-Context/Locale/2FA) – siehe bootstrap/app.php.
 * Organisation wird ausschliesslich ueber den Portal-Slug aufgeloest.
 */
// Neutrale Landingpage ohne Slug: erklaert das Portal generisch und verlinkt
// das Postfach – gibt bewusst keine Organisation oder Portal-Existenz preis.
Route::get('melden', [PublicPortalController::class, 'landing'])
    ->middleware('throttle:wb-view')
    ->name('whistleblowing.landing');

// Anonymes Postfach (Phase 4). Login NUR per Geheimnis, Cookie-Sitzung (kein
// Pfad-Token). MUSS vor den {portal}-Routen stehen, damit „postfach" nicht als
// Portal-Slug interpretiert wird.
Route::prefix('melden/postfach')
    ->name('whistleblowing.mailbox.')
    ->group(function (): void {
        Route::get('/', [ReporterMailboxController::class, 'login'])
            ->middleware('throttle:wb-view')->name('login');
        Route::post('/', [ReporterMailboxController::class, 'authenticate'])
            ->middleware('throttle:wb-login')->name('authenticate');

        Route::middleware(EnsureMailboxSession::class)->group(function (): void {
            Route::get('nachrichten', [ReporterMailboxController::class, 'show'])->name('show');
            Route::post('nachrichten', [ReporterMailboxController::class, 'message'])
                ->middleware('throttle:wb-submit')->name('message.store');
            Route::post('anhaenge', [ReporterMailboxController::class, 'attachment'])
                ->middleware('throttle:wb-submit')->name('attachment.store');
            Route::post('abmelden', [ReporterMailboxController::class, 'logout'])->name('logout');
        });
    });

Route::prefix('melden')
    ->name('whistleblowing.')
    ->middleware(ResolvePortal::class)
    ->group(function (): void {
        Route::get('{portal}', [PublicPortalController::class, 'show'])
            ->middleware('throttle:wb-view')
            ->name('portal');

        Route::post('{portal}', [PublicReportController::class, 'store'])
            ->middleware('throttle:wb-submit')
            ->name('report.store');

        Route::get('{portal}/erfolg', [PublicReportController::class, 'receipt'])
            ->middleware('throttle:wb-view')
            ->name('receipt');
    });
