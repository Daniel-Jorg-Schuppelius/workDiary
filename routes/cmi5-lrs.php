<?php
/*
 * Created on   : Mon Sep 14 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : cmi5-lrs.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

use App\Http\Controllers\Learning\Cmi5LrsController;
use App\Http\Middleware\Learning\AuthenticateCmi5Session;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| cmi5-LRS (Feature 149, xAPI 1.0.3)
|--------------------------------------------------------------------------
| Eingehängt in bootstrap/app.php unter LearningCmi5Runtime::ENDPOINT_PATH mit
| der sitzungslosen Gruppe `cmi5-lrs`. Außer `about`, Fetch-URL und Preflight
| gilt allein der Token der AU-Sitzung. Der Preflight braucht eine eigene Route:
| Laravels automatische OPTIONS-Antwort liefe ohne die CORS-Header der Gruppe.
*/

Route::options('{path?}', [Cmi5LrsController::class, 'preflight'])->where('path', '.*')->name('preflight');
Route::get('about', [Cmi5LrsController::class, 'about'])->name('about');
Route::post('fetch/{token}', [Cmi5LrsController::class, 'fetch'])->middleware('throttle:60,1')->name('fetch');

Route::middleware(AuthenticateCmi5Session::class)->group(function (): void {
    Route::match(['get', 'put', 'post'], 'statements', [Cmi5LrsController::class, 'statements'])->name('statements');
    Route::match(['get', 'put', 'post', 'delete'], 'activities/state', [Cmi5LrsController::class, 'state'])->name('state');
    Route::match(['get', 'put', 'post', 'delete'], 'agents/profile', [Cmi5LrsController::class, 'agentProfile'])->name('agent-profile');
    Route::get('activities', [Cmi5LrsController::class, 'activity'])->name('activities');
});
