<?php
/*
 * Created on   : Sun May 03 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : TagController.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\TagResource;
use App\Models\Tag;
use App\Support\LookupCache;
use Illuminate\Http\{JsonResponse, Request};
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use OpenApi\Attributes as OA;

class TagController extends Controller {
    #[OA\Get(
        path: '/tags',
        summary: 'Tags auflisten',
        tags: ['Tags'],
        security: [['bearerAuth' => ['tags:read']]],
        responses: [
            new OA\Response(response: 200, description: 'OK'),
            new OA\Response(response: 401, description: 'Unauthenticated'),
            new OA\Response(response: 403, description: 'Forbidden'),
        ],
    )]
    public function index(): AnonymousResourceCollection {
        return TagResource::collection(LookupCache::tagOptions());
    }

    #[OA\Post(
        path: '/tags',
        summary: 'Tag anlegen',
        tags: ['Tags'],
        security: [['bearerAuth' => ['tags:write']]],
        requestBody: new OA\RequestBody(required: true, content: new OA\JsonContent(required: ['name'], properties: [
            new OA\Property(property: 'name', type: 'string', maxLength: 64),
            new OA\Property(property: 'color', type: 'string', maxLength: 16, nullable: true),
        ])),
        responses: [
            new OA\Response(response: 201, description: 'Created'),
            new OA\Response(response: 401, description: 'Unauthenticated'),
            new OA\Response(response: 403, description: 'Forbidden'),
            new OA\Response(response: 422, description: 'Validation error'),
        ],
    )]
    public function store(Request $request): JsonResponse {
        Gate::authorize('create', Tag::class);
        $data = $request->validate([
            'name' => ['required', 'string', 'max:64', 'unique:tags,name'],
            'color' => ['nullable', 'string', 'max:16'],
        ]);
        $tag = Tag::create([
            'name' => $data['name'],
            'slug' => Str::slug($data['name']),
            'color' => $data['color'] ?? null,
        ]);

        return (new TagResource($tag))->response()->setStatusCode(201);
    }

    #[OA\Put(
        path: '/tags/{tag}',
        summary: 'Tag aktualisieren',
        tags: ['Tags'],
        security: [['bearerAuth' => ['tags:write']]],
        parameters: [new OA\Parameter(name: 'tag', in: 'path', required: true, description: 'Sqid', schema: new OA\Schema(type: 'string', example: 'k7Qx2Ab'))],
        requestBody: new OA\RequestBody(required: true, content: new OA\JsonContent(required: ['name'], properties: [
            new OA\Property(property: 'name', type: 'string', maxLength: 64),
            new OA\Property(property: 'color', type: 'string', maxLength: 16, nullable: true),
        ])),
        responses: [
            new OA\Response(response: 200, description: 'OK'),
            new OA\Response(response: 401, description: 'Unauthenticated'),
            new OA\Response(response: 403, description: 'Forbidden'),
            new OA\Response(response: 404, description: 'Not Found'),
            new OA\Response(response: 422, description: 'Validation error'),
        ],
    )]
    public function update(Request $request, Tag $tag): TagResource {
        Gate::authorize('update', $tag);
        $data = $request->validate([
            'name' => ['required', 'string', 'max:64', 'unique:tags,name,' . $tag->id],
            'color' => ['nullable', 'string', 'max:16'],
        ]);
        $tag->update([
            'name' => $data['name'],
            'slug' => Str::slug($data['name']),
            'color' => $data['color'] ?? null,
        ]);

        return new TagResource($tag);
    }

    #[OA\Delete(
        path: '/tags/{tag}',
        summary: 'Tag löschen',
        tags: ['Tags'],
        security: [['bearerAuth' => ['tags:write']]],
        parameters: [new OA\Parameter(name: 'tag', in: 'path', required: true, description: 'Sqid', schema: new OA\Schema(type: 'string', example: 'k7Qx2Ab'))],
        responses: [
            new OA\Response(response: 200, description: 'OK'),
            new OA\Response(response: 401, description: 'Unauthenticated'),
            new OA\Response(response: 403, description: 'Forbidden'),
            new OA\Response(response: 404, description: 'Not Found'),
        ],
    )]
    public function destroy(Tag $tag): JsonResponse {
        Gate::authorize('delete', $tag);
        $tag->delete();

        return response()->json(['status' => 'deleted']);
    }
}
