<?php
/*
 * Created on   : Sat Jul 05 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : routes.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

use App\Plugins\Zammad\Http\Controllers\{ZammadAdminController, ZammadWebhookController};
use Illuminate\Support\Facades\Route;

/**
 * Plugin-eigene Routen (Feature 060): Admin-Panel (Anbindung + manueller Sync)
 * und Webhook-Empfang. Admin-Autorisierung wird im Controller geprüft.
 */

// Webhook (MVP-129): sessionlos ohne CSRF ('api'-Gruppe) — Autorisierung über
// HMAC-Signatur (X-Hub-Signature) des Raw-Bodys im Controller, Org-Zuordnung
// über die Anbindungs-ID im Pfad. Polling heilt Ausfälle; throttle gegen
// Flooding des unauthentifizierten Endpunkts.
Route::middleware(['api', 'throttle:30,1'])
    ->post('api/webhooks/zammad/{connection}', ZammadWebhookController::class)
    ->name('api.webhooks.zammad');

Route::middleware(['web', 'auth'])->group(function (): void {
    Route::get('admin/zammad', [ZammadAdminController::class, 'index'])->name('admin.zammad.index');

    // Anbindung je Organisation anlegen/aktualisieren (Basis-URL, Token, Queue-Map).
    Route::post('admin/zammad/connection', [ZammadAdminController::class, 'store'])->name('admin.zammad.connection.store');
    Route::post('admin/zammad/disconnect', [ZammadAdminController::class, 'disconnect'])->name('admin.zammad.disconnect');

    // Manueller Ticket-Import (auditierter Admin-Vorgang; Polling-Äquivalent).
    Route::post('admin/zammad/sync', [ZammadAdminController::class, 'sync'])->name('admin.zammad.sync');
});
