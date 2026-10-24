<?php
/*
 * Created on   : Sat Oct 24 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : routes.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

use App\Plugins\Paperless\Http\Controllers\PaperlessIntakeController;
use Illuminate\Support\Facades\Route;

// Verbinden auf der Seite Cloud-Dokumenteingang; Rechte prüft der Controller, Basis-URL und Token stehen in den Plugin-Einstellungen.
Route::middleware(['web', 'auth'])->group(static function (): void {
    Route::get('admin/cloud-intake/paperless/connect', [PaperlessIntakeController::class, 'connectForm'])
        ->name('admin.cloud-intake.paperless.connect-form');
    Route::post('admin/cloud-intake/paperless/connect', [PaperlessIntakeController::class, 'connect'])
        ->name('admin.cloud-intake.paperless.connect');
});
