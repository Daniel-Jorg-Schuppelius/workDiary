<?php
/*
 * Created on   : Sat May 30 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : customer.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

use App\Http\Controllers\CustomerPortal\{AssetController, BillingController, DashboardController, DiaryController, DiaryDetailController, DocumentController, InvitationController, InvoiceController, LoginController, OpenIssueController, PhotoConfirmationController, TimeEntryController, TwoFactorChallengeController, TwoFactorController};
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

    // Einladungs-Annahme (MVP-510): einmaliger, befristeter Link zur
    // Passwortvergabe — bewusst ohne Session/Guard, neutral bei Fehlern.
    Route::get('/invitation/{token}', [InvitationController::class, 'show'])
        ->middleware('throttle:30,1')->name('invitation.show');
    Route::post('/invitation/{token}', [InvitationController::class, 'store'])
        ->middleware('throttle:6,1')->name('invitation.accept');

    // Zweiter Login-Schritt (Zwei-Faktor): session-basiert, kein auth-Guard.
    Route::get('/two-factor-challenge', [TwoFactorChallengeController::class, 'create'])->name('two-factor.login');
    Route::post('/two-factor-challenge', [TwoFactorChallengeController::class, 'store'])->middleware('throttle:login')->name('two-factor.login.attempt');
    Route::post('/two-factor-challenge/email', [TwoFactorChallengeController::class, 'email'])->middleware('throttle:login')->name('two-factor.login.email');
    Route::post('/two-factor-challenge/webauthn/options', [TwoFactorChallengeController::class, 'webauthnOptions'])->name('two-factor.login.webauthn.options');
    Route::post('/two-factor-challenge/webauthn', [TwoFactorChallengeController::class, 'webauthnVerify'])->middleware('throttle:login')->name('two-factor.login.webauthn');

    // Fallakte-PDF über signierten, kurzlebigen Link (Rang 54): teilbar ohne
    // Portal-Session — Schutz ausschließlich über die 24-h-Signatur, Inhalt
    // strikt kundensichtbar.
    Route::get('/diary/{diary}/pdf', [DiaryDetailController::class, 'pdf'])->name('diary.pdf');

    // Anwesenheitsnachweis-PDF (Feature 098): signierter 24-h-Link, Muster wie
    // die Fallakte — bewusst außerhalb des auth-Guards, Inhalt strikt kundensichtbar.
    Route::get('/billing/statement/{statement}/pdf', [BillingController::class, 'pdf'])->name('billing.pdf');

    Route::middleware(['auth:customer', 'two-factor.setup:customer'])->group(function (): void {
        Route::get('/', DashboardController::class)->name('dashboard');

        // Bereichsfreigaben (MVP-511): jede Fachroute hängt hinter dem
        // zentralen Capability-Gate — nicht Freigegebenes antwortet 404,
        // auch bei direkten URLs. Die objektbezogenen Sichtbarkeits-Gates
        // (customer_visible, OpenIssueVisibility, …) bleiben zusätzlich aktiv.
        Route::middleware('portal.capability:diary')->group(function (): void {
            Route::get('/diary', [DiaryController::class, 'index'])->name('diary.index');
            // Auftragsdetail read-only (Rang 54) + Foto-Bestätigung/-Beanstandung (Rang 55).
            Route::get('/diary/{diary}', [DiaryDetailController::class, 'show'])->name('diary.show');
            Route::post('/diary/{diary}/photos/{attachment}/confirm', [PhotoConfirmationController::class, 'confirm'])->name('diary.photos.confirm');
            Route::post('/diary/{diary}/photos/{attachment}/complain', [PhotoConfirmationController::class, 'complain'])->name('diary.photos.complain');
        });

        Route::middleware('portal.capability:time_entries')->group(function (): void {
            Route::get('/time-entries', [TimeEntryController::class, 'index'])->name('time-entries.index');
        });

        Route::middleware('portal.capability:invoices')->group(function (): void {
            Route::get('/invoices', [InvoiceController::class, 'index'])->name('invoices.index');
            // Abrechnungskonto (Feature 098): Monatsübersicht + Monatsdetail, nur
            // für Kunden mit aktivem Konto-Modus-Abrechnungsprofil (sonst 404).
            Route::get('/billing', [BillingController::class, 'index'])->name('billing.index');
            Route::get('/billing/{year}/{month}', [BillingController::class, 'show'])
                ->whereNumber('year')->whereNumber('month')->name('billing.show');
        });

        Route::middleware('portal.capability:open_issues')->group(function (): void {
            Route::get('/open-issues', [OpenIssueController::class, 'index'])->name('open-issues.index');
            // Bekannte Fehler (Feature 065, MVP-156): read-only Known Errors
            // (status=known_error + visibility=customer, org-gescopt).
            Route::get('/known-errors', [\App\Http\Controllers\CustomerPortal\KnownErrorController::class, 'index'])->name('known-errors.index');
        });

        Route::middleware('portal.capability:tickets')->group(function (): void {
            // Portal-Tickets (Feature 065, MVP-160): nur eigene, nur public.
            Route::get('/tickets', [\App\Http\Controllers\CustomerPortal\TicketController::class, 'index'])->name('tickets.index');
            Route::post('/tickets', [\App\Http\Controllers\CustomerPortal\TicketController::class, 'store'])->name('tickets.store');
            Route::get('/tickets/{ticket}', [\App\Http\Controllers\CustomerPortal\TicketController::class, 'show'])->name('tickets.show');
            // Anhang-Download (W5.1): gleiche Scope-Grenze wie die Ticket-Ansicht,
            // Pfade nur aus der DB — Sicherheitsmuster wie customer.documents.download.
            Route::get('/tickets/{ticket}/attachments/{attachment}/download', [\App\Http\Controllers\CustomerPortal\TicketController::class, 'downloadAttachment'])->name('tickets.attachments.download');
            Route::post('/tickets/{ticket}/reply', [\App\Http\Controllers\CustomerPortal\TicketController::class, 'reply'])->name('tickets.reply');
            Route::post('/tickets/{ticket}/accept', [\App\Http\Controllers\CustomerPortal\TicketController::class, 'accept'])->name('tickets.accept');
            Route::post('/tickets/{ticket}/reopen', [\App\Http\Controllers\CustomerPortal\TicketController::class, 'reopen'])->name('tickets.reopen');
            Route::post('/tickets/{ticket}/rate', [\App\Http\Controllers\CustomerPortal\TicketController::class, 'rate'])->name('tickets.rate');
            // Portal-Bestellstrecke Servicekatalog (Feature 065, MVP-154): nur
            // portal-sichtbare Einträge, Bestellung friert Snapshots ein.
            Route::get('/catalog', [\App\Http\Controllers\CustomerPortal\CatalogController::class, 'index'])->name('catalog.index');
            Route::get('/catalog/{item}', [\App\Http\Controllers\CustomerPortal\CatalogController::class, 'show'])->name('catalog.show');
            Route::post('/catalog/{item}/order', [\App\Http\Controllers\CustomerPortal\CatalogController::class, 'order'])->name('catalog.order');
        });

        Route::middleware('portal.capability:claims')->group(function (): void {
            // Reklamationsstatus + Nachreichungen (Feature 072, MVP-256).
            Route::get('/claims', [\App\Http\Controllers\CustomerPortal\ClaimPortalController::class, 'index'])->name('claims.index');
            Route::get('/claims/{claim}', [\App\Http\Controllers\CustomerPortal\ClaimPortalController::class, 'show'])->name('claims.show');
            Route::post('/claims/{claim}/nachreichung', [\App\Http\Controllers\CustomerPortal\ClaimPortalController::class, 'addNote'])->name('claims.note');
        });

        Route::middleware('portal.capability:rentals')->group(function (): void {
            // Verleihvorgänge + Übergabebestätigung (Feature 073, MVP-263/269).
            Route::get('/rentals', [\App\Http\Controllers\CustomerPortal\RentalPortalController::class, 'index'])->name('rentals.index');
            Route::get('/rentals/{rental}', [\App\Http\Controllers\CustomerPortal\RentalPortalController::class, 'show'])->name('rentals.show');
            Route::post('/rentals/{rental}/uebergabe/{report}/bestaetigen', [\App\Http\Controllers\CustomerPortal\RentalPortalController::class, 'confirm'])->name('rentals.confirm');
        });

        Route::middleware('portal.capability:assets')->group(function (): void {
            // Objektakte read-only (Rang 50): eigene Objekte des Kunden.
            Route::get('/assets', [AssetController::class, 'index'])->name('assets.index');
            Route::get('/assets/{asset}', [AssetController::class, 'show'])->name('assets.show');
        });

        Route::middleware('portal.capability:queries')->group(function (): void {
            // Rückfragen/Kommentare (MVP-512): eigene Capability, erweitert nur
            // freigegebene Bereiche — Subjects prüft die zentrale Allowlist.
            Route::get('/queries', [\App\Http\Controllers\CustomerPortal\QueryController::class, 'index'])->name('queries.index');
            Route::get('/queries/create', [\App\Http\Controllers\CustomerPortal\QueryController::class, 'create'])->name('queries.create');
            Route::post('/queries', [\App\Http\Controllers\CustomerPortal\QueryController::class, 'store'])
                ->middleware('throttle:portal-query')->name('queries.store');
            Route::post('/queries/{query}/withdraw', [\App\Http\Controllers\CustomerPortal\QueryController::class, 'withdraw'])->name('queries.withdraw');
        });

        Route::middleware('portal.capability:documents')->group(function (): void {
            // Freigegebene Dokumente (Welle D — Dokument-Spiegelung): NUR fürs
            // Kundenportal freigegebene Dokumente des eigenen Kunden, sicherer
            // Download hinter dem Portal-Guard.
            Route::get('/documents', [DocumentController::class, 'index'])->name('documents.index');
            Route::get('/documents/{document}/download/{version?}', [DocumentController::class, 'download'])->name('documents.download');
        });

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
