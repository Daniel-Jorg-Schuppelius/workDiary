<?php
/*
 * Created on   : Sun May 03 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : OnCallShiftController.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\OnCallShiftResource;
use App\Models\OnCallShift;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use OpenApi\Attributes as OA;

class OnCallShiftController extends Controller {
    #[OA\Get(
        path: '/shifts',
        summary: 'Bereitschaftsschichten auflisten',
        tags: ['Shifts'],
        security: [['bearerAuth' => ['shifts:read']]],
        parameters: [
            new OA\Parameter(name: 'from', in: 'query', required: false, schema: new OA\Schema(type: 'string', format: 'date')),
            new OA\Parameter(name: 'to', in: 'query', required: false, schema: new OA\Schema(type: 'string', format: 'date')),
            new OA\Parameter(name: 'archived', in: 'query', required: false, description: 'Archivierte einschließen', schema: new OA\Schema(type: 'boolean', default: false)),
            new OA\Parameter(name: 'per_page', in: 'query', required: false, schema: new OA\Schema(type: 'integer', default: 20, maximum: 100)),
        ],
        responses: [
            new OA\Response(response: 200, description: 'OK'),
            new OA\Response(response: 401, description: 'Unauthenticated'),
            new OA\Response(response: 403, description: 'Forbidden'),
        ],
    )]
    public function index(Request $request): AnonymousResourceCollection {
        $q = OnCallShift::query()->with('user:id,name');
        if ($request->filled('from')) {
            $q->whereDate('start_at', '>=', $request->from);
        }
        if ($request->filled('to')) {
            $q->whereDate('start_at', '<=', $request->to);
        }
        if (! $request->boolean('archived')) {
            $q->where('is_archived', false);
        }

        return OnCallShiftResource::collection($q->orderBy('start_at')->paginate(min(100, (int) $request->input('per_page', 20))));
    }

    #[OA\Get(
        path: '/shifts/{shift}',
        summary: 'Bereitschaftsschicht anzeigen',
        tags: ['Shifts'],
        security: [['bearerAuth' => ['shifts:read']]],
        parameters: [new OA\Parameter(name: 'shift', in: 'path', required: true, description: 'Sqid', schema: new OA\Schema(type: 'string', example: 'k7Qx2Ab'))],
        responses: [
            new OA\Response(response: 200, description: 'OK'),
            new OA\Response(response: 401, description: 'Unauthenticated'),
            new OA\Response(response: 403, description: 'Forbidden'),
            new OA\Response(response: 404, description: 'Not Found'),
        ],
    )]
    public function show(OnCallShift $shift): OnCallShiftResource {
        return new OnCallShiftResource($shift->load('user:id,name'));
    }
}
