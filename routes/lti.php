<?php
/*
 * Created on   : Mon Sep 14 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : lti.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

use App\Http\Controllers\Learning\{LearningLtiJwksController, LearningLtiPlatformController, LearningLtiToolController};
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| LTI 1.3 ohne Sitzung (Feature 149)
|--------------------------------------------------------------------------
| Eingehängt in bootstrap/app.php unter `/lti` mit der Gruppe `lti`.
*/

Route::get('jwks', LearningLtiJwksController::class)->middleware('throttle:120,1')->name('jwks');

// WorkDiary als Tool: Login-Anstoß der Plattform — sitzungslos, Zustand im Cache.
Route::match(['get', 'post'], 'tool/login', [LearningLtiToolController::class, 'login'])->middleware('throttle:60,1')->name('tool.login');

// Deep-Linking-Antwort des Tools: ein fremder POST ohne Sitzung; gebunden über den
// signierten Zustand in der Adresse, geprüft gegen die Schlüssel des Tools.
Route::post('deep-linking/rueckkehr', [LearningLtiPlatformController::class, 'deepLinkingReturn'])->middleware('throttle:60,1')->name('deep-linking.return');
