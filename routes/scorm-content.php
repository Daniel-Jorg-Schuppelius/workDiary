<?php
/*
 * Created on   : Mon Sep 14 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : scorm-content.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

use App\Http\Controllers\Learning\ScormContentController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Eigener Inhalts-Host für SCORM (Sicherheitsaudit files-1)
|--------------------------------------------------------------------------
| Registriert von {@see \App\Support\Learning\ScormContentRoutes}, nur unter dem
| konfigurierten Host und ohne Sitzung. Ein Kurs lädt viele Dateien, die Drossel
| ist deshalb großzügig; der Token im Pfad ist die eigentliche Schranke.
*/

Route::get('scorm/{token}/huelle', [ScormContentController::class, 'wrapper'])
    ->middleware('throttle:120,1')
    ->name('learning.scorm-content.wrapper');

Route::get('scorm/{token}/inhalt/{path?}', [ScormContentController::class, 'asset'])
    ->where('path', '.*')
    ->middleware('throttle:1200,1')
    ->name('learning.scorm-content.asset');
