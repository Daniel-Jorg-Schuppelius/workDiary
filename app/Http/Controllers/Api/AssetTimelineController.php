<?php
/*
 * Created on   : Sat May 23 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : AssetTimelineController.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Asset;
use App\Services\Asset\AssetTimelineService;
use Illuminate\Http\{JsonResponse, Request};
use Illuminate\Support\Facades\Gate;
use OpenApi\Attributes as OA;

class AssetTimelineController extends Controller {
    #[OA\Get(
        path: '/assets/{asset}/timeline',
        summary: 'Zeitleiste eines Assets',
        tags: ['Assets'],
        security: [['bearerAuth' => ['assets:read']]],
        parameters: [
            new OA\Parameter(name: 'asset', in: 'path', required: true, description: 'Sqid', schema: new OA\Schema(type: 'string', example: 'k7Qx2Ab')),
            new OA\Parameter(name: 'limit', in: 'query', required: false, schema: new OA\Schema(type: 'integer', default: 120, minimum: 1, maximum: 300)),
        ],
        responses: [
            new OA\Response(response: 200, description: 'OK'),
            new OA\Response(response: 401, description: 'Unauthenticated'),
            new OA\Response(response: 403, description: 'Forbidden'),
            new OA\Response(response: 404, description: 'Not Found'),
        ],
    )]
    public function __invoke(Request $request, Asset $asset, AssetTimelineService $timeline): JsonResponse {
        Gate::authorize('view', $asset);

        $limit = max(1, min(300, (int) $request->integer('limit', 120)));

        return response()->json([
            'data' => $timeline->build($asset, $limit),
        ]);
    }
}
