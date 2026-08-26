<?php
/*
 * Created on   : Sat May 23 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : AssetStatusVisibilityController.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Asset;
use App\Services\Asset\AssetStatusVisibilityService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Gate;
use OpenApi\Attributes as OA;

class AssetStatusVisibilityController extends Controller {
    #[OA\Get(
        path: '/assets/{asset}/status-visibility',
        summary: 'Status-Sichtbarkeit eines Assets',
        tags: ['Assets'],
        security: [['bearerAuth' => ['assets:read']]],
        parameters: [new OA\Parameter(name: 'asset', in: 'path', required: true, description: 'Sqid', schema: new OA\Schema(type: 'string', example: 'k7Qx2Ab'))],
        responses: [
            new OA\Response(response: 200, description: 'OK'),
            new OA\Response(response: 401, description: 'Unauthenticated'),
            new OA\Response(response: 403, description: 'Forbidden'),
            new OA\Response(response: 404, description: 'Not Found'),
        ],
    )]
    public function __invoke(Asset $asset, AssetStatusVisibilityService $visibility): JsonResponse {
        Gate::authorize('view', $asset);

        return response()->json([
            'data' => $visibility->summarize($asset),
        ]);
    }
}
