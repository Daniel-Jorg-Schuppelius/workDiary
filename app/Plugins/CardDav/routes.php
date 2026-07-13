<?php
/*
 * Created on   : Sun Jul 12 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : routes.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

use App\Plugins\CardDav\Http\Controllers\CardDavAdminController;
use Illuminate\Support\Facades\Route;

/**
 * Plugin-eigene Routen (Bauturbo A9): Admin-Panel (Anbindung, Discovery +
 * Adressbuch-Wahl, manueller Sync). Admin-Autorisierung wird im Controller
 * geprüft. Kein öffentlicher/unauthentifizierter Endpunkt — CardDAV ist rein
 * ausgehend (lesender Sync).
 */
Route::middleware(['web', 'auth'])->group(function (): void {
    Route::get('admin/carddav', [CardDavAdminController::class, 'index'])->name('admin.carddav.index');

    Route::post('admin/carddav/connection', [CardDavAdminController::class, 'store'])->name('admin.carddav.connection.store');
    Route::post('admin/carddav/discover', [CardDavAdminController::class, 'discover'])->name('admin.carddav.discover');
    Route::post('admin/carddav/addressbook', [CardDavAdminController::class, 'chooseAddressbook'])->name('admin.carddav.addressbook');
    Route::post('admin/carddav/disconnect', [CardDavAdminController::class, 'disconnect'])->name('admin.carddav.disconnect');

    // Manueller Sync (auditierter Admin-Vorgang; Scheduler-Äquivalent).
    Route::post('admin/carddav/sync', [CardDavAdminController::class, 'sync'])->name('admin.carddav.sync');
});
