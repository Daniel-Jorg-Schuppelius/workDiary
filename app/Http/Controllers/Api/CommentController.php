<?php
/*
 * Created on   : Sun May 03 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : CommentController.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\CommentResource;
use App\Models\{Comment, DiaryEntry};
use Illuminate\Http\{JsonResponse, Request};
use Illuminate\Support\Facades\{Auth, Gate};
use OpenApi\Attributes as OA;

class CommentController extends Controller {
    #[OA\Post(
        path: '/diary/{diary}/comments',
        summary: 'Kommentar anlegen',
        tags: ['Comments'],
        security: [['bearerAuth' => ['comments:write']]],
        parameters: [new OA\Parameter(name: 'diary', in: 'path', required: true, description: 'Sqid', schema: new OA\Schema(type: 'string', example: 'k7Qx2Ab'))],
        requestBody: new OA\RequestBody(required: true, content: new OA\JsonContent(required: ['body'], properties: [
            new OA\Property(property: 'body', type: 'string', maxLength: 65535),
        ])),
        responses: [
            new OA\Response(response: 201, description: 'Created'),
            new OA\Response(response: 401, description: 'Unauthenticated'),
            new OA\Response(response: 403, description: 'Forbidden'),
            new OA\Response(response: 404, description: 'Not Found'),
            new OA\Response(response: 422, description: 'Validation error'),
        ],
    )]
    public function store(Request $request, DiaryEntry $diary): JsonResponse {
        Gate::authorize('create', Comment::class);
        $data = $request->validate(['body' => ['required', 'string', 'max:65535']]);
        $comment = $diary->comments()->create([
            'user_id' => Auth::id(),
            'body' => $data['body'],
        ]);

        return (new CommentResource($comment->load('user:id,name')))->response()->setStatusCode(201);
    }

    #[OA\Put(
        path: '/comments/{comment}',
        summary: 'Kommentar aktualisieren',
        tags: ['Comments'],
        security: [['bearerAuth' => ['comments:write']]],
        parameters: [new OA\Parameter(name: 'comment', in: 'path', required: true, description: 'Sqid', schema: new OA\Schema(type: 'string', example: 'k7Qx2Ab'))],
        requestBody: new OA\RequestBody(required: true, content: new OA\JsonContent(required: ['body'], properties: [
            new OA\Property(property: 'body', type: 'string', maxLength: 65535),
        ])),
        responses: [
            new OA\Response(response: 200, description: 'OK'),
            new OA\Response(response: 401, description: 'Unauthenticated'),
            new OA\Response(response: 403, description: 'Forbidden'),
            new OA\Response(response: 404, description: 'Not Found'),
            new OA\Response(response: 422, description: 'Validation error'),
        ],
    )]
    public function update(Request $request, Comment $comment): CommentResource {
        Gate::authorize('update', $comment);
        $data = $request->validate(['body' => ['required', 'string', 'max:65535']]);
        $comment->update($data);

        return new CommentResource($comment->fresh('user:id,name') ?? $comment);
    }

    #[OA\Delete(
        path: '/comments/{comment}',
        summary: 'Kommentar löschen',
        tags: ['Comments'],
        security: [['bearerAuth' => ['comments:write']]],
        parameters: [new OA\Parameter(name: 'comment', in: 'path', required: true, description: 'Sqid', schema: new OA\Schema(type: 'string', example: 'k7Qx2Ab'))],
        responses: [
            new OA\Response(response: 200, description: 'OK'),
            new OA\Response(response: 401, description: 'Unauthenticated'),
            new OA\Response(response: 403, description: 'Forbidden'),
            new OA\Response(response: 404, description: 'Not Found'),
        ],
    )]
    public function destroy(Comment $comment): JsonResponse {
        Gate::authorize('delete', $comment);
        $comment->delete();

        return response()->json(['status' => 'deleted']);
    }
}
