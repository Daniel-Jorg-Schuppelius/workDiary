<?php
/*
 * Created on   : Tue Aug 25 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : VehicleApiController.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Enums\Vehicle\{VehicleOwnership, VehiclePropulsion, VehicleType};
use App\Http\Controllers\Controller;
use App\Http\Resources\VehicleResource;
use App\Models\Vehicle;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Gate;
use OpenApi\Attributes as OA;

/**
 * REST-API Fahrzeuge (MVP-718, Vollscan J11): read-only, VehiclePolicy wie im
 * Web, Ability `vehicles:read`, Plan-Gating `module.fuhrpark`.
 */
class VehicleApiController extends Controller {
    #[OA\Get(
        path: '/vehicles',
        summary: 'Fahrzeuge auflisten',
        tags: ['Vehicles'],
        security: [['bearerAuth' => ['vehicles:read']]],
        parameters: [
            new OA\Parameter(name: 'search', in: 'query', required: false, description: 'Kennzeichen/Bezeichnung (Teilstring)', schema: new OA\Schema(type: 'string')),
            new OA\Parameter(name: 'vehicle_type', in: 'query', required: false, schema: new OA\Schema(type: 'string')),
            new OA\Parameter(name: 'propulsion', in: 'query', required: false, schema: new OA\Schema(type: 'string')),
            new OA\Parameter(name: 'ownership', in: 'query', required: false, schema: new OA\Schema(type: 'string')),
            new OA\Parameter(name: 'archived', in: 'query', required: false, description: 'Archivierte einschließen', schema: new OA\Schema(type: 'boolean', default: false)),
            new OA\Parameter(name: 'per_page', in: 'query', required: false, schema: new OA\Schema(type: 'integer', default: 25, maximum: 100)),
        ],
        responses: [
            new OA\Response(response: 200, description: 'OK'),
            new OA\Response(response: 401, description: 'Unauthenticated'),
            new OA\Response(response: 403, description: 'Forbidden'),
            new OA\Response(response: 423, description: 'Modul nicht lizenziert'),
        ],
    )]
    public function index(Request $request): AnonymousResourceCollection {
        Gate::authorize('viewAny', Vehicle::class);

        $query = Vehicle::query()
            ->when(! $request->boolean('archived'), fn($q) => $q->whereNull('archived_at'))
            ->when($request->filled('search'), function ($q) use ($request): void {
                $term = (string) $request->query('search');
                $q->where(fn($w) => $w->whereLikeEscaped('license_plate', $term)->orWhereLikeEscaped('label', $term));
            })
            ->when(VehicleType::tryFrom((string) $request->query('vehicle_type', '')) !== null, fn($q) => $q->where('vehicle_type', (string) $request->query('vehicle_type')))
            ->when(VehiclePropulsion::tryFrom((string) $request->query('propulsion', '')) !== null, fn($q) => $q->where('propulsion', (string) $request->query('propulsion')))
            ->when(VehicleOwnership::tryFrom((string) $request->query('ownership', '')) !== null, fn($q) => $q->where('ownership', (string) $request->query('ownership')))
            ->orderBy('license_plate')
            ->orderBy('id');

        return VehicleResource::collection($query->paginate(ArticleApiController::perPage($request)));
    }

    #[OA\Get(
        path: '/vehicles/{vehicle}',
        summary: 'Fahrzeug anzeigen',
        tags: ['Vehicles'],
        security: [['bearerAuth' => ['vehicles:read']]],
        parameters: [new OA\Parameter(name: 'vehicle', in: 'path', required: true, description: 'Sqid', schema: new OA\Schema(type: 'string', example: 'Fz6Tn2'))],
        responses: [
            new OA\Response(response: 200, description: 'OK'),
            new OA\Response(response: 401, description: 'Unauthenticated'),
            new OA\Response(response: 403, description: 'Forbidden'),
            new OA\Response(response: 404, description: 'Not Found'),
        ],
    )]
    public function show(Vehicle $vehicle): VehicleResource {
        Gate::authorize('view', $vehicle);

        return new VehicleResource($vehicle);
    }
}
