<?php
/*
 * Created on   : Tue Aug 04 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : routes.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

use App\Plugins\Etsy\Http\Controllers\{EtsyAdminController, EtsyWebhookController};
use Illuminate\Support\Facades\Route;

/**
 * Plugin-eigene Routen (Feature 101). Geladen vom
 * {@see \App\Plugins\Etsy\EtsyServiceProvider}.
 */

// Webhook: sessionlos ohne CSRF ('api'-Gruppe). Autorisierung über den opaken
// {token} (schlägt die Connection → Org + Webhook-Secret nach; Org NIE aus
// dem Payload) und die Svix-HMAC-Signatur des Raw-Bodys im Controller.
// throttle:webhook-ingest gegen Flooding.
Route::middleware(['api', 'throttle:webhook-ingest'])
    ->post('api/webhooks/etsy/{token}', EtsyWebhookController::class)
    ->name('api.webhooks.etsy');

Route::middleware(['web', 'auth', \App\Http\Middleware\EnforcePlanModules::class])
    ->prefix('admin/etsy')
    ->name('admin.etsy.')
    ->group(static function (): void {
        Route::get('/', [EtsyAdminController::class, 'index'])->name('index');

        // OAuth-Verbindung (eine je Organisation, org-eigene Seller-App).
        Route::post('oauth/start', [EtsyAdminController::class, 'startOAuth'])->name('oauth.start');
        Route::get('oauth/callback', [EtsyAdminController::class, 'oauthCallback'])->name('oauth.callback');
        Route::post('disconnect', [EtsyAdminController::class, 'disconnect'])->name('disconnect');

        // Manueller Abgleich + Versandmeldung (MVP-497).
        Route::post('sync', [EtsyAdminController::class, 'syncNow'])->name('sync');
        Route::post('receipts/{etsyReceipt}/ship', [EtsyAdminController::class, 'ship'])->name('receipts.ship');
    });
