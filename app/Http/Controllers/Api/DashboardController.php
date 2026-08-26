<?php
/*
 * Created on   : Sun May 03 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : DashboardController.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\Dashboard\DashboardService;
use Illuminate\Http\{JsonResponse, Request};
use OpenApi\Attributes as OA;

class DashboardController extends Controller {
    #[OA\Get(
        path: '/dashboard',
        summary: 'Dashboard-Zusammenfassung',
        tags: ['Dashboard'],
        security: [['bearerAuth' => ['dashboard:read']]],
        responses: [
            new OA\Response(response: 200, description: 'OK'),
            new OA\Response(response: 401, description: 'Unauthenticated'),
            new OA\Response(response: 403, description: 'Forbidden'),
        ],
    )]
    public function __invoke(Request $request, DashboardService $service): JsonResponse {
        /** @var User $user */
        $user = $request->user();

        return response()->json(['data' => $service->summarize($user)]);
    }
}
