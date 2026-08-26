<?php
/*
 * Created on   : Tue Aug 25 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ArticleApiController.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Enums\Article\{ArticleStatus, ArticleType};
use App\Http\Controllers\Controller;
use App\Http\Resources\{ArticleResource, ArticleVariantResource};
use App\Models\Article;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Gate;
use OpenApi\Attributes as OA;

/**
 * REST-API Artikel (MVP-718, Vollscan J11): read-only — Liste mit Filtern,
 * Detail inkl. Varianten, Varianten-Liste. Ability `articles:read`, Plan-Gating
 * `module.lager` (config plans.routes), ArticlePolicy wie im Web.
 */
class ArticleApiController extends Controller {
    #[OA\Get(
        path: '/articles',
        summary: 'Artikel auflisten',
        tags: ['Articles'],
        security: [['bearerAuth' => ['articles:read']]],
        parameters: [
            new OA\Parameter(name: 'search', in: 'query', required: false, description: 'Name/Nummer/GTIN (Teilstring)', schema: new OA\Schema(type: 'string')),
            new OA\Parameter(name: 'status', in: 'query', required: false, schema: new OA\Schema(type: 'string', enum: ['draft', 'active', 'retired'])),
            new OA\Parameter(name: 'type', in: 'query', required: false, schema: new OA\Schema(type: 'string')),
            new OA\Parameter(name: 'stockable', in: 'query', required: false, schema: new OA\Schema(type: 'boolean')),
            new OA\Parameter(name: 'per_page', in: 'query', required: false, schema: new OA\Schema(type: 'integer', default: 25, maximum: 100)),
        ],
        responses: [
            new OA\Response(response: 200, description: 'OK'),
            new OA\Response(response: 401, description: 'Unauthenticated'),
            new OA\Response(response: 403, description: 'Forbidden (Ability/Policy)'),
            new OA\Response(response: 423, description: 'Modul nicht lizenziert'),
        ],
    )]
    public function index(Request $request): AnonymousResourceCollection {
        Gate::authorize('viewAny', Article::class);

        $query = Article::query()
            ->when($request->filled('search'), function ($q) use ($request): void {
                $term = (string) $request->query('search');
                $q->where(fn($w) => $w->whereLikeEscaped('name', $term)
                    ->orWhereLikeEscaped('number', $term)
                    ->orWhereLikeEscaped('gtin', $term));
            })
            ->when(ArticleStatus::tryFrom((string) $request->query('status', '')) !== null, fn($q) => $q->where('status', (string) $request->query('status')))
            ->when(ArticleType::tryFrom((string) $request->query('type', '')) !== null, fn($q) => $q->where('type', (string) $request->query('type')))
            ->when($request->has('stockable'), fn($q) => $q->where('stockable', $request->boolean('stockable')))
            ->orderBy('name')
            ->orderBy('id');

        return ArticleResource::collection($query->paginate(self::perPage($request)));
    }

    #[OA\Get(
        path: '/articles/{article}',
        summary: 'Artikel anzeigen (inkl. Varianten)',
        tags: ['Articles'],
        security: [['bearerAuth' => ['articles:read']]],
        parameters: [new OA\Parameter(name: 'article', in: 'path', required: true, description: 'Sqid', schema: new OA\Schema(type: 'string', example: 'k7Qx2Ab'))],
        responses: [
            new OA\Response(response: 200, description: 'OK'),
            new OA\Response(response: 401, description: 'Unauthenticated'),
            new OA\Response(response: 403, description: 'Forbidden'),
            new OA\Response(response: 404, description: 'Not Found'),
        ],
    )]
    public function show(Article $article): ArticleResource {
        Gate::authorize('view', $article);

        return new ArticleResource($article->load(['variants' => fn($q) => $q->orderByDesc('is_default')->orderBy('sku')]));
    }

    #[OA\Get(
        path: '/articles/{article}/variants',
        summary: 'Varianten eines Artikels auflisten',
        tags: ['Articles'],
        security: [['bearerAuth' => ['articles:read']]],
        parameters: [
            new OA\Parameter(name: 'article', in: 'path', required: true, description: 'Sqid', schema: new OA\Schema(type: 'string', example: 'k7Qx2Ab')),
            new OA\Parameter(name: 'status', in: 'query', required: false, schema: new OA\Schema(type: 'string', enum: ['draft', 'active', 'retired'])),
        ],
        responses: [
            new OA\Response(response: 200, description: 'OK'),
            new OA\Response(response: 401, description: 'Unauthenticated'),
            new OA\Response(response: 403, description: 'Forbidden'),
            new OA\Response(response: 404, description: 'Not Found'),
        ],
    )]
    public function variants(Article $article, Request $request): AnonymousResourceCollection {
        Gate::authorize('view', $article);

        $variants = $article->variants()
            ->when(ArticleStatus::tryFrom((string) $request->query('status', '')) !== null, fn($q) => $q->where('status', (string) $request->query('status')))
            ->orderByDesc('is_default')
            ->orderBy('sku')
            ->get();

        return ArticleVariantResource::collection($variants);
    }

    public static function perPage(Request $request, int $default = 25): int {
        return max(1, min(100, (int) $request->input('per_page', $default)));
    }
}
