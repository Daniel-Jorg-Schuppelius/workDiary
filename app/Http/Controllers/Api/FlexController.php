<?php
/*
 * Created on   : Thu May 14 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : FlexController.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\Flextime\FlexCalculator;
use Carbon\CarbonImmutable;
use Illuminate\Http\{JsonResponse, Request};
use OpenApi\Attributes as OA;

class FlexController extends Controller {
    public function __construct(protected FlexCalculator $calc) {}

    #[OA\Get(
        path: '/flex/summary',
        summary: 'Gleitzeit-Monatssaldo',
        tags: ['Flex'],
        security: [['bearerAuth' => ['flex:read']]],
        parameters: [
            new OA\Parameter(name: 'year', in: 'query', required: false, description: 'Standard: aktuelles Jahr', schema: new OA\Schema(type: 'integer')),
            new OA\Parameter(name: 'month', in: 'query', required: false, description: 'Standard: aktueller Monat', schema: new OA\Schema(type: 'integer', minimum: 1, maximum: 12)),
        ],
        responses: [
            new OA\Response(response: 200, description: 'OK'),
            new OA\Response(response: 401, description: 'Unauthenticated'),
            new OA\Response(response: 403, description: 'Forbidden'),
        ],
    )]
    public function summary(Request $request): JsonResponse {
        $year = (int) $request->input('year', CarbonImmutable::now()->year);
        $month = (int) $request->input('month', CarbonImmutable::now()->month);

        return response()->json(['data' => $this->calc->monthlyBalance($this->authUser(), $year, $month)]);
    }
}
