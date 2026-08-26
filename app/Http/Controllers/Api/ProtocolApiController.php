<?php
/*
 * Created on   : Tue Aug 25 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ProtocolApiController.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Enums\Protocol\{ProtocolStatus, ProtocolType};
use App\Enums\User\Permission;
use App\Http\Controllers\Controller;
use App\Http\Resources\ProtocolResource;
use App\Models\{Protocol, User};
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Gate;
use OpenApi\Attributes as OA;

/**
 * REST-API Protokolle (MVP-718, Vollscan J11): read-only. Ohne
 * protocol.viewAny nur selbst erstellte Protokolle (Liste) bzw. Policy-`view`
 * (Detail). Signatur-Token-Interna werden nie ausgegeben (ProtocolResource).
 * Ability `protocols:read`; Protokolle sind Core (kein Modul-Gating).
 */
class ProtocolApiController extends Controller {
    #[OA\Get(
        path: '/protocols',
        summary: 'Protokolle auflisten',
        tags: ['Protocols'],
        security: [['bearerAuth' => ['protocols:read']]],
        parameters: [
            new OA\Parameter(name: 'type', in: 'query', required: false, schema: new OA\Schema(type: 'string', enum: ['acceptance', 'service', 'maintenance', 'handover', 'defect', 'inspection', 'siteVisit', 'other'])),
            new OA\Parameter(name: 'status', in: 'query', required: false, schema: new OA\Schema(type: 'string', enum: ['draft', 'in_review', 'signed', 'archived', 'superseded'])),
            new OA\Parameter(name: 'from', in: 'query', required: false, description: 'occurred_at ≥ (Datum)', schema: new OA\Schema(type: 'string', format: 'date')),
            new OA\Parameter(name: 'to', in: 'query', required: false, description: 'occurred_at ≤ (Datum)', schema: new OA\Schema(type: 'string', format: 'date')),
            new OA\Parameter(name: 'search', in: 'query', required: false, description: 'Titel (Teilstring)', schema: new OA\Schema(type: 'string')),
            new OA\Parameter(name: 'per_page', in: 'query', required: false, schema: new OA\Schema(type: 'integer', default: 25, maximum: 100)),
        ],
        responses: [
            new OA\Response(response: 200, description: 'OK'),
            new OA\Response(response: 401, description: 'Unauthenticated'),
            new OA\Response(response: 403, description: 'Forbidden'),
        ],
    )]
    public function index(Request $request): AnonymousResourceCollection {
        Gate::authorize('viewAny', Protocol::class);
        /** @var User $user */
        $user = $request->user();

        $query = Protocol::query()
            ->when(! ($user->isAdmin() || $user->can(Permission::ProtocolViewAny->value)), fn($q) => $q->where('created_by_user_id', $user->id))
            ->when(ProtocolType::tryFrom((string) $request->query('type', '')) !== null, fn($q) => $q->where('type', (string) $request->query('type')))
            ->when(ProtocolStatus::tryFrom((string) $request->query('status', '')) !== null, fn($q) => $q->where('status', (string) $request->query('status')))
            ->when($request->filled('from'), fn($q) => $q->whereDate('occurred_at', '>=', (string) $request->query('from')))
            ->when($request->filled('to'), fn($q) => $q->whereDate('occurred_at', '<=', (string) $request->query('to')))
            ->when($request->filled('search'), fn($q) => $q->whereLikeEscaped('title', (string) $request->query('search')))
            ->orderByDesc('occurred_at')
            ->orderByDesc('id');

        return ProtocolResource::collection($query->paginate(ArticleApiController::perPage($request)));
    }

    #[OA\Get(
        path: '/protocols/{protocol}',
        summary: 'Protokoll anzeigen (inkl. Punkte und Signaturen ohne Interna)',
        tags: ['Protocols'],
        security: [['bearerAuth' => ['protocols:read']]],
        parameters: [new OA\Parameter(name: 'protocol', in: 'path', required: true, description: 'Sqid', schema: new OA\Schema(type: 'string', example: 'Pr5Vn3'))],
        responses: [
            new OA\Response(response: 200, description: 'OK'),
            new OA\Response(response: 401, description: 'Unauthenticated'),
            new OA\Response(response: 403, description: 'Forbidden'),
            new OA\Response(response: 404, description: 'Not Found'),
        ],
    )]
    public function show(Protocol $protocol): ProtocolResource {
        Gate::authorize('view', $protocol);

        return new ProtocolResource($protocol->load([
            'items' => fn($q) => $q->orderBy('sort_order')->orderBy('id'),
            'signatures' => fn($q) => $q->orderBy('signed_at'),
        ]));
    }
}
