<?php
/*
 * Created on   : Mon Sep 14 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : LearningLtiJwksController.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Http\Controllers\Learning;

use App\Http\Controllers\Controller;
use App\Services\Learning\LearningLtiKeyService;
use ELearningToolkit\Lti\Keys;
use Illuminate\Http\JsonResponse;

/** Öffentliche Schlüssel der Instanz für LTI 1.3 (Feature 149) — nie private Anteile. */
final class LearningLtiJwksController extends Controller {
    public function __invoke(LearningLtiKeyService $keys): JsonResponse {
        return response()->json(Keys::publicKeySet($keys->published()), 200, [
            'Cache-Control' => 'public, max-age=300',
            'Access-Control-Allow-Origin' => '*',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }
}
