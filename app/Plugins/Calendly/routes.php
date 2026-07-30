<?php
/*
 * Created on   : Mon Jul 20 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : routes.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

use App\Plugins\Calendly\Http\Controllers\{CalendlyAdminController, CalendlyWebhookController};
use Illuminate\Support\Facades\Route;

/**
 * Plugin-eigene Routen (Feature 095). Geladen vom
 * {@see \App\Plugins\Calendly\CalendlyServiceProvider}.
 */

// Webhook: sessionlos ohne CSRF ('api'-Gruppe). Autorisierung über den opaken
// {token} (schlägt Subscription → Org + signing_key nach) und die HMAC-Signatur
// des Raw-Bodys im Controller. throttle:webhook-ingest gegen Flooding.
Route::middleware(['api', 'throttle:webhook-ingest'])
    ->post('api/webhooks/calendly/{token}', CalendlyWebhookController::class)
    ->name('api.webhooks.calendly');

Route::middleware(['web', 'auth'])->group(function (): void {
    Route::get('admin/calendly', [CalendlyAdminController::class, 'index'])->name('admin.calendly.index');

    // OAuth-Verbindung (eine je Organisation).
    Route::post('admin/calendly/oauth/start', [CalendlyAdminController::class, 'startOAuth'])->name('admin.calendly.oauth.start');
    Route::get('admin/calendly/oauth/callback', [CalendlyAdminController::class, 'oauthCallback'])->name('admin.calendly.oauth.callback');
    Route::post('admin/calendly/disconnect', [CalendlyAdminController::class, 'disconnect'])->name('admin.calendly.disconnect');

    // Webhook-Subscription (an-/abmelden) + manueller Backfill.
    Route::post('admin/calendly/subscribe', [CalendlyAdminController::class, 'subscribe'])->name('admin.calendly.subscribe');
    Route::post('admin/calendly/backfill', [CalendlyAdminController::class, 'backfill'])->name('admin.calendly.backfill');

    // Zweiphasige Bestätigung der Terminwünsche.
    Route::post('admin/calendly/requests/{appointmentRequest}/confirm', [CalendlyAdminController::class, 'confirm'])->name('admin.calendly.requests.confirm');
    Route::post('admin/calendly/requests/{appointmentRequest}/decline', [CalendlyAdminController::class, 'decline'])->name('admin.calendly.requests.decline');

    // Outbound (P5): Einmal-Buchungslink (one_off_event_types) erzeugen.
    Route::post('admin/calendly/booking-link', [CalendlyAdminController::class, 'createBookingLink'])->name('admin.calendly.booking-link');
});
