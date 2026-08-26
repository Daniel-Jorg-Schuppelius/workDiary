<?php
/*
 * Created on   : Thu May 14 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : MaterialController.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\MaterialResource;
use App\Models\Material;
use App\Services\Material\MaterialProviderRegistry;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Gate;
use OpenApi\Attributes as OA;

class MaterialController extends Controller {
    #[OA\Get(
        path: '/materials',
        summary: 'Materialien suchen',
        tags: ['Materials'],
        security: [['bearerAuth' => ['materials:read']]],
        parameters: [
            new OA\Parameter(name: 'q', in: 'query', required: false, description: 'Suchbegriff', schema: new OA\Schema(type: 'string')),
            new OA\Parameter(name: 'provider', in: 'query', required: false, description: 'Provider-Schlüssel oder * für alle', schema: new OA\Schema(type: 'string', default: 'local')),
            new OA\Parameter(name: 'limit', in: 'query', required: false, schema: new OA\Schema(type: 'integer', default: 25)),
        ],
        responses: [
            new OA\Response(response: 200, description: 'OK'),
            new OA\Response(response: 401, description: 'Unauthenticated'),
            new OA\Response(response: 403, description: 'Forbidden'),
        ],
    )]
    public function index(Request $request, MaterialProviderRegistry $registry): AnonymousResourceCollection {
        Gate::authorize('viewAny', Material::class);
        $q = $request->string('q')->toString();
        $provider = $request->string('provider', 'local')->toString();

        $items = $provider === '*'
            ? $registry->searchAll($q, (int) $request->input('limit', 25))
            : ($registry->get($provider)?->search($q, (int) $request->input('limit', 25)) ?? collect());

        return MaterialResource::collection($items);
    }
}
