<?php
/*
 * Created on   : Thu May 14 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : TimesheetMaterialController.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\SaveMaterialUsageRequest;
use App\Http\Resources\MaterialUsageResource;
use App\Models\{Material, MaterialUsage, Timesheet};
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Gate;
use OpenApi\Attributes as OA;

class TimesheetMaterialController extends Controller {
    #[OA\Get(
        path: '/timesheets/{timesheet}/materials',
        summary: 'Materialverbrauch eines Stundenzettels',
        tags: ['Materials'],
        security: [['bearerAuth' => ['timesheets:read']]],
        parameters: [new OA\Parameter(name: 'timesheet', in: 'path', required: true, description: 'Sqid', schema: new OA\Schema(type: 'string', example: 'k7Qx2Ab'))],
        responses: [
            new OA\Response(response: 200, description: 'OK'),
            new OA\Response(response: 401, description: 'Unauthenticated'),
            new OA\Response(response: 403, description: 'Forbidden'),
            new OA\Response(response: 404, description: 'Not Found'),
        ],
    )]
    public function index(Timesheet $timesheet): AnonymousResourceCollection {
        Gate::authorize('view', $timesheet);

        return MaterialUsageResource::collection($timesheet->materialUsages()->get());
    }

    #[OA\Post(
        path: '/timesheets/{timesheet}/materials',
        summary: 'Materialverbrauch anlegen',
        tags: ['Materials'],
        security: [['bearerAuth' => ['timesheets:write']]],
        parameters: [new OA\Parameter(name: 'timesheet', in: 'path', required: true, description: 'Sqid', schema: new OA\Schema(type: 'string', example: 'k7Qx2Ab'))],
        requestBody: new OA\RequestBody(required: true, content: new OA\JsonContent(required: ['description', 'quantity', 'unit'], properties: [
            new OA\Property(property: 'material_id', type: 'string', description: 'Sqid', example: 'k7Qx2Ab', nullable: true),
            new OA\Property(property: 'description', type: 'string', maxLength: 255),
            new OA\Property(property: 'quantity', type: 'number', minimum: 0.001, maximum: 99999.999),
            new OA\Property(property: 'unit', type: 'string', maxLength: 20),
            new OA\Property(property: 'unit_price', type: 'number', minimum: 0, nullable: true),
            new OA\Property(property: 'tax_rate', type: 'number', minimum: 0, maximum: 100, nullable: true),
        ])),
        responses: [
            new OA\Response(response: 201, description: 'Created'),
            new OA\Response(response: 401, description: 'Unauthenticated'),
            new OA\Response(response: 403, description: 'Forbidden'),
            new OA\Response(response: 404, description: 'Not Found'),
            new OA\Response(response: 422, description: 'Validation error'),
        ],
    )]
    public function store(Timesheet $timesheet, SaveMaterialUsageRequest $request): MaterialUsageResource {
        Gate::authorize('update', $timesheet);
        $data = $request->validated();
        if (! empty($data['material_id'])) {
            $m = Material::find((int) $data['material_id']);
            if ($m) {
                $data['unit_price'] = $data['unit_price'] ?? $m->default_unit_price;
                $data['tax_rate'] = $data['tax_rate'] ?? $m->tax_rate;
                $data['unit'] = $data['unit'] ?: $m->unit;
                $data['description'] = $data['description'] ?: $m->name;
            }
        }

        return new MaterialUsageResource($timesheet->materialUsages()->create($data));
    }

    #[OA\Put(
        path: '/timesheets/{timesheet}/materials/{usage}',
        summary: 'Materialverbrauch aktualisieren',
        tags: ['Materials'],
        security: [['bearerAuth' => ['timesheets:write']]],
        parameters: [
            new OA\Parameter(name: 'timesheet', in: 'path', required: true, description: 'Sqid', schema: new OA\Schema(type: 'string', example: 'k7Qx2Ab')),
            new OA\Parameter(name: 'usage', in: 'path', required: true, description: 'Sqid', schema: new OA\Schema(type: 'string', example: 'k7Qx2Ab')),
        ],
        requestBody: new OA\RequestBody(required: true, content: new OA\JsonContent(required: ['description', 'quantity', 'unit'], properties: [
            new OA\Property(property: 'material_id', type: 'string', description: 'Sqid', example: 'k7Qx2Ab', nullable: true),
            new OA\Property(property: 'description', type: 'string', maxLength: 255),
            new OA\Property(property: 'quantity', type: 'number', minimum: 0.001, maximum: 99999.999),
            new OA\Property(property: 'unit', type: 'string', maxLength: 20),
            new OA\Property(property: 'unit_price', type: 'number', minimum: 0, nullable: true),
            new OA\Property(property: 'tax_rate', type: 'number', minimum: 0, maximum: 100, nullable: true),
        ])),
        responses: [
            new OA\Response(response: 200, description: 'OK'),
            new OA\Response(response: 401, description: 'Unauthenticated'),
            new OA\Response(response: 403, description: 'Forbidden'),
            new OA\Response(response: 404, description: 'Not Found'),
            new OA\Response(response: 422, description: 'Validation error'),
        ],
    )]
    public function update(Timesheet $timesheet, MaterialUsage $usage, SaveMaterialUsageRequest $request): MaterialUsageResource {
        Gate::authorize('update', $timesheet);
        abort_unless((int) $usage->timesheet_id === (int) $timesheet->id, 404);
        $usage->update($request->validated());

        return new MaterialUsageResource($usage);
    }

    #[OA\Delete(
        path: '/timesheets/{timesheet}/materials/{usage}',
        summary: 'Materialverbrauch löschen',
        tags: ['Materials'],
        security: [['bearerAuth' => ['timesheets:write']]],
        parameters: [
            new OA\Parameter(name: 'timesheet', in: 'path', required: true, description: 'Sqid', schema: new OA\Schema(type: 'string', example: 'k7Qx2Ab')),
            new OA\Parameter(name: 'usage', in: 'path', required: true, description: 'Sqid', schema: new OA\Schema(type: 'string', example: 'k7Qx2Ab')),
        ],
        responses: [
            new OA\Response(response: 204, description: 'No Content'),
            new OA\Response(response: 401, description: 'Unauthenticated'),
            new OA\Response(response: 403, description: 'Forbidden'),
            new OA\Response(response: 404, description: 'Not Found'),
        ],
    )]
    public function destroy(Timesheet $timesheet, MaterialUsage $usage): Response {
        Gate::authorize('update', $timesheet);
        abort_unless((int) $usage->timesheet_id === (int) $timesheet->id, 404);
        $usage->delete();

        return response()->noContent();
    }
}
