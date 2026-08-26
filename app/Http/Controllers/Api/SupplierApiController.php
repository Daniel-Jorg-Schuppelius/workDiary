<?php
/*
 * Created on   : Tue Aug 25 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : SupplierApiController.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\SupplierResource;
use App\Models\Supplier;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Gate;
use OpenApi\Attributes as OA;

/**
 * REST-API Lieferanten (MVP-718, Vollscan J11): read-only, SupplierPolicy wie
 * im Web, Ability `suppliers:read`, Plan-Gating `module.vertrieb`. Ohne
 * Bankverbindung/Steuernummer (SupplierResource).
 */
class SupplierApiController extends Controller {
    #[OA\Get(
        path: '/suppliers',
        summary: 'Lieferanten auflisten',
        tags: ['Suppliers'],
        security: [['bearerAuth' => ['suppliers:read']]],
        parameters: [
            new OA\Parameter(name: 'search', in: 'query', required: false, description: 'Name/Firma/Nummer (Teilstring)', schema: new OA\Schema(type: 'string')),
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
        Gate::authorize('viewAny', Supplier::class);

        $query = Supplier::query()
            ->when(! $request->boolean('archived'), fn($q) => $q->whereNull('archived_at'))
            ->when($request->filled('search'), function ($q) use ($request): void {
                $term = (string) $request->query('search');
                $q->where(fn($w) => $w->whereLikeEscaped('name', $term)
                    ->orWhereLikeEscaped('company', $term)
                    ->orWhereLikeEscaped('number', $term));
            })
            ->orderBy('name')
            ->orderBy('id');

        return SupplierResource::collection($query->paginate(ArticleApiController::perPage($request)));
    }

    #[OA\Get(
        path: '/suppliers/{supplier}',
        summary: 'Lieferant anzeigen',
        tags: ['Suppliers'],
        security: [['bearerAuth' => ['suppliers:read']]],
        parameters: [new OA\Parameter(name: 'supplier', in: 'path', required: true, description: 'Sqid', schema: new OA\Schema(type: 'string', example: 'Lf8Qw1'))],
        responses: [
            new OA\Response(response: 200, description: 'OK'),
            new OA\Response(response: 401, description: 'Unauthenticated'),
            new OA\Response(response: 403, description: 'Forbidden'),
            new OA\Response(response: 404, description: 'Not Found'),
        ],
    )]
    public function show(Supplier $supplier): SupplierResource {
        Gate::authorize('view', $supplier);

        return new SupplierResource($supplier);
    }
}
