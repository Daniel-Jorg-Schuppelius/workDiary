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

use App\Http\Controllers\Controller;
use App\Http\Resources\DiaryEntryResource;
use App\Models\{DiaryEntry, Tag, User};
use App\Services\Archive\ArchiveService;
use App\Support\Sqid;
use Illuminate\Http\{JsonResponse, Request};
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\{Auth, Gate};

class DiaryController extends Controller {
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

    public function show(DiaryEntry $diary): DiaryEntryResource {
        $diary->load(['user:id,name', 'tags', 'comments.user:id,name', 'attachments.uploader:id,name']);

        return new DiaryEntryResource($diary);
    }

    public function store(Request $request): JsonResponse {
        $data = $this->validateData($request);
        /** @var User $user */
        $user = Auth::user();
        /** @var DiaryEntry $entry */
        $entry = $user->diaryEntries()->create($data);
        if ($request->filled('tag_ids')) {
            $entry->syncTagsFromInput($this->decodeTagIds($request->input('tag_ids')), []);
        }

        return (new DiaryEntryResource($entry->fresh(['user', 'tags']) ?? $entry))->response()->setStatusCode(201);
    }

    public function update(Request $request, DiaryEntry $diary): DiaryEntryResource {
        Gate::authorize('update', $diary);
        $data = $this->validateData($request);
        $diary->update($data);
        if ($request->has('tag_ids')) {
            $diary->syncTagsFromInput($this->decodeTagIds($request->input('tag_ids')), []);
        }

        return new DiaryEntryResource($diary->fresh(['user', 'tags']) ?? $diary);
    }

    public function destroy(DiaryEntry $diary): JsonResponse {
        Gate::authorize('delete', $diary);
        $diary->delete();

        return response()->json(['status' => 'deleted']);
    }

    public function archive(DiaryEntry $diary, ArchiveService $service): DiaryEntryResource {
        Gate::authorize('archive', $diary);
        $service->archiveEntry($diary);

        return new DiaryEntryResource($diary->fresh(['user', 'tags']) ?? $diary);
    }

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
            'status' => ['required', 'integer', 'in:-1,1,2,3'],
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
        return collect((array) $raw)
            ->map(fn($v) => is_scalar($v) ? Sqid::decodeOrNumeric(Tag::class, (string) $v) : null)
            ->filter()
            ->unique()
            ->values()
            ->all();
    }
}
