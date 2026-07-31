<?php
/*
 * Created on   : Thu Jul 31 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : routes.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

use App\Plugins\Fritzbox\Http\Controllers\FritzboxController;
use Illuminate\Support\Facades\Route;

/**
 * Plugin-eigene Routen (FritzBox-Anruflisten-Import). Geladen vom
 * {@see \App\Plugins\Fritzbox\FritzboxServiceProvider}. Admin-Berechtigung wird
 * im Controller geprüft. Unbekannte Nummern werden in der universellen
 * Zuordnungs-Inbox (admin.integration.inbox) aufgelöst.
 */
Route::middleware(['web', 'auth'])->group(function (): void {
    Route::get('admin/fritzbox', [FritzboxController::class, 'index'])->name('admin.fritzbox.index');
    Route::post('admin/fritzbox/import-csv', [FritzboxController::class, 'uploadCsv'])->name('admin.fritzbox.import-csv');
});
