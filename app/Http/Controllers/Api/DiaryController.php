<?php
/*
 * Created on   : Sun May 03 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : DiaryController.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Http\Controllers\Api;

use App\Enums\Diary\Status;
use App\Http\Controllers\Controller;
use App\Http\Resources\DiaryEntryResource;
use App\Models\{DiaryEntry, User};
use App\Services\Archive\ArchiveService;
use Illuminate\Http\{JsonResponse, Request};
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\{Auth, Gate};
use OpenApi\Attributes as OA;

class DiaryController extends Controller {
    #[OA\Get(
        path: '/diary',
        summary: 'Aufträge auflisten',
        tags: ['Diary'],
        security: [['bearerAuth' => ['diary:read']]],
        parameters: [
            new OA\Parameter(name: 'status', in: 'query', required: false, description: 'Status-Wert (Integer) oder all', schema: new OA\Schema(type: 'string')),
            new OA\Parameter(name: 'from', in: 'query', required: false, schema: new OA\Schema(type: 'string', format: 'date')),
            new OA\Parameter(name: 'to', in: 'query', required: false, schema: new OA\Schema(type: 'string', format: 'date')),
            new OA\Parameter(name: 'mine', in: 'query', required: false, description: 'Nur eigene Einträge', schema: new OA\Schema(type: 'boolean', default: false)),
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
        $q = DiaryEntry::query()->with(['user:id,name', 'tags']);

        if ($request->filled('status') && $request->status !== 'all') {
            $q->where('status', (int) $request->status);
        }
        if ($request->filled('from')) {
            $q->whereDate('start_at', '>=', $request->from);
        }
        if ($request->filled('to')) {
            $q->whereDate('start_at', '<=', $request->to);
        }
        if ($request->boolean('mine')) {
            $q->where('user_id', Auth::id());
        }
        if (! $request->boolean('archived')) {
            $q->where('is_archived', false);
        }

        $perPage = min(100, max(1, (int) $request->input('per_page', 20)));

        return DiaryEntryResource::collection($q->orderByDesc('start_at')->paginate($perPage));
    }

    #[OA\Get(
        path: '/diary/{diary}',
        summary: 'Auftrag anzeigen',
        tags: ['Diary'],
        security: [['bearerAuth' => ['diary:read']]],
        parameters: [new OA\Parameter(name: 'diary', in: 'path', required: true, description: 'Sqid', schema: new OA\Schema(type: 'string', example: 'k7Qx2Ab'))],
        responses: [
            new OA\Response(response: 200, description: 'OK'),
            new OA\Response(response: 401, description: 'Unauthenticated'),
            new OA\Response(response: 403, description: 'Forbidden'),
            new OA\Response(response: 404, description: 'Not Found'),
        ],
    )]
    public function show(DiaryEntry $diary): DiaryEntryResource {
        // Parität zur Weboberfläche (Sicherheitsscan 2026-08-23, S-39): dort
        // verlangt `show` die Objekt-Policy, über die API fehlte sie. Der
        // OrganizationScope allein sagt nur, dass der Eintrag zum Mandanten
        // gehört — nicht, dass dieser Token ihn sehen darf.
        Gate::authorize('view', $diary);

        $diary->load(['user:id,name', 'tags', 'comments.user:id,name', 'attachments.uploader:id,name']);

        return new DiaryEntryResource($diary);
    }

    #[OA\Post(
        path: '/diary',
        summary: 'Auftrag anlegen',
        tags: ['Diary'],
        security: [['bearerAuth' => ['diary:write']]],
        requestBody: new OA\RequestBody(required: true, content: new OA\JsonContent(required: ['content', 'status'], properties: [
            new OA\Property(property: 'content', type: 'string', maxLength: 65535),
            new OA\Property(property: 'response', type: 'string', maxLength: 65535, nullable: true),
            new OA\Property(property: 'status', type: 'integer', enum: [-1, 1, 2, 3, 4, 5, 6, 7, 8]),
            new OA\Property(property: 'start_at', type: 'string', format: 'date-time', nullable: true),
            new OA\Property(property: 'end_at', type: 'string', format: 'date-time', nullable: true),
            new OA\Property(property: 'tag_ids', type: 'array', description: 'Tag-Sqids', items: new OA\Items(type: 'string', example: 'k7Qx2Ab')),
        ])),
        responses: [
            new OA\Response(response: 201, description: 'Created'),
            new OA\Response(response: 401, description: 'Unauthenticated'),
            new OA\Response(response: 403, description: 'Forbidden'),
            new OA\Response(response: 422, description: 'Validation error'),
        ],
    )]
    public function store(Request $request): JsonResponse {
        $data = $this->validateData($request);
        $data['status'] = Status::Planned->value;
        /** @var User $user */
        $user = Auth::user();
        /** @var DiaryEntry $entry */
        $entry = $user->diaryEntries()->create($data);
        if ($request->filled('tag_ids')) {
            $entry->syncTagsFromInput($this->decodeTagIds($request->input('tag_ids')), []);
        }

        return (new DiaryEntryResource($entry->fresh(['user', 'tags']) ?? $entry))->response()->setStatusCode(201);
    }

    #[OA\Put(
        path: '/diary/{diary}',
        summary: 'Auftrag aktualisieren',
        tags: ['Diary'],
        security: [['bearerAuth' => ['diary:write']]],
        parameters: [new OA\Parameter(name: 'diary', in: 'path', required: true, description: 'Sqid', schema: new OA\Schema(type: 'string', example: 'k7Qx2Ab'))],
        requestBody: new OA\RequestBody(required: true, content: new OA\JsonContent(required: ['content', 'status'], properties: [
            new OA\Property(property: 'content', type: 'string', maxLength: 65535),
            new OA\Property(property: 'response', type: 'string', maxLength: 65535, nullable: true),
            new OA\Property(property: 'status', type: 'integer', enum: [-1, 1, 2, 3, 4, 5, 6, 7, 8]),
            new OA\Property(property: 'start_at', type: 'string', format: 'date-time', nullable: true),
            new OA\Property(property: 'end_at', type: 'string', format: 'date-time', nullable: true),
            new OA\Property(property: 'tag_ids', type: 'array', description: 'Tag-Sqids', items: new OA\Items(type: 'string', example: 'k7Qx2Ab')),
        ])),
        responses: [
            new OA\Response(response: 200, description: 'OK'),
            new OA\Response(response: 401, description: 'Unauthenticated'),
            new OA\Response(response: 403, description: 'Forbidden'),
            new OA\Response(response: 404, description: 'Not Found'),
            new OA\Response(response: 422, description: 'Validation error'),
        ],
    )]
    public function update(Request $request, DiaryEntry $diary): DiaryEntryResource {
        Gate::authorize('update', $diary);
        $data = $this->validateData($request);
        unset($data['status']);
        $diary->update($data);
        if ($request->has('tag_ids')) {
            $diary->syncTagsFromInput($this->decodeTagIds($request->input('tag_ids')), []);
        }

        return new DiaryEntryResource($diary->fresh(['user', 'tags']) ?? $diary);
    }

    #[OA\Delete(
        path: '/diary/{diary}',
        summary: 'Auftrag löschen',
        tags: ['Diary'],
        security: [['bearerAuth' => ['diary:write']]],
        parameters: [new OA\Parameter(name: 'diary', in: 'path', required: true, description: 'Sqid', schema: new OA\Schema(type: 'string', example: 'k7Qx2Ab'))],
        responses: [
            new OA\Response(response: 200, description: 'OK'),
            new OA\Response(response: 401, description: 'Unauthenticated'),
            new OA\Response(response: 403, description: 'Forbidden'),
            new OA\Response(response: 404, description: 'Not Found'),
        ],
    )]
    public function destroy(DiaryEntry $diary): JsonResponse {
        Gate::authorize('delete', $diary);
        $diary->delete();

        return response()->json(['status' => 'deleted']);
    }

    #[OA\Post(
        path: '/diary/{diary}/archive',
        summary: 'Auftrag archivieren',
        tags: ['Diary'],
        security: [['bearerAuth' => ['diary:write']]],
        parameters: [new OA\Parameter(name: 'diary', in: 'path', required: true, description: 'Sqid', schema: new OA\Schema(type: 'string', example: 'k7Qx2Ab'))],
        responses: [
            new OA\Response(response: 200, description: 'OK'),
            new OA\Response(response: 401, description: 'Unauthenticated'),
            new OA\Response(response: 403, description: 'Forbidden'),
            new OA\Response(response: 404, description: 'Not Found'),
        ],
    )]
    public function archive(DiaryEntry $diary, ArchiveService $service): DiaryEntryResource {
        Gate::authorize('archive', $diary);
        $service->archiveEntry($diary);

        return new DiaryEntryResource($diary->fresh(['user', 'tags']) ?? $diary);
    }

    #[OA\Post(
        path: '/diary/{diary}/restore',
        summary: 'Auftrag aus Archiv zurückholen',
        tags: ['Diary'],
        security: [['bearerAuth' => ['diary:write']]],
        parameters: [new OA\Parameter(name: 'diary', in: 'path', required: true, description: 'Sqid', schema: new OA\Schema(type: 'string', example: 'k7Qx2Ab'))],
        responses: [
            new OA\Response(response: 200, description: 'OK'),
            new OA\Response(response: 401, description: 'Unauthenticated'),
            new OA\Response(response: 403, description: 'Forbidden'),
            new OA\Response(response: 404, description: 'Not Found'),
        ],
    )]
    public function restore(DiaryEntry $diary, ArchiveService $service): DiaryEntryResource {
        Gate::authorize('archive', $diary);
        $service->restoreEntry($diary);

        return new DiaryEntryResource($diary->fresh(['user', 'tags']) ?? $diary);
    }

    /** @return array<string, mixed> */
    private function validateData(Request $request): array {
        return $request->validate([
            'content' => ['required', 'string', 'max:65535'],
            'response' => ['nullable', 'string', 'max:65535'],
            'status' => ['required', 'integer', 'in:-1,1,2,3,4,5,6,7,8'],
            'start_at' => ['nullable', 'date'],
            'end_at' => ['nullable', 'date', 'after_or_equal:start_at'],
        ]);
    }

    /**
     * Dekodiert eingehende `tag_ids` (opake Sqids) zu numerischen PKs für
     * {@see \App\Models\Concerns\HasTags::syncTagsFromInput()}. Rohe
     * numerische IDs werden als Backward-Compat-Fallback weiterhin akzeptiert.
     *
     * @return array<int>
     */
    private function decodeTagIds(mixed $raw): array {
        // Kanonische Dekodierung (Vollaudit 2026-07, M40); Org-Prüfung in HasTags.
        return \App\Support\TagInput::ids(is_array($raw) ? $raw : (array) $raw);
    }
}
