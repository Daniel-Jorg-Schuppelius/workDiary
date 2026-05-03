<?php

namespace App\Http\Controllers;

use App\Models\DiaryEntry;
use App\Models\Legacy\LegacyDiaryEntry;
use App\Models\Tag;
use App\Models\User;
use App\Services\Archive\ArchiveService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

class DiaryController extends Controller {
    public function index(Request $request): View {
        [$query, $filters] = $this->buildIndexQuery($request);
        $entries = $query->paginate(20)->withQueryString();

        $counts = [
            'all' => DiaryEntry::where('is_archived', false)->count(),
            'open' => DiaryEntry::where('is_archived', false)->where('status', 2)->count(),
            'alert' => DiaryEntry::where('is_archived', false)->where('status', 3)->count(),
            'done' => DiaryEntry::where('is_archived', false)->where('status', -1)->count(),
            'archived' => DiaryEntry::where('is_archived', true)->count(),
        ];

        return view('diary.index', [
            'entries' => $entries,
            'counts' => $counts,
            'filters' => $filters,
            'allTags' => Tag::orderBy('name')->get(['id', 'name', 'color']),
        ]);
    }

    /**
     * Baut die gefilterte Query und gibt zusätzlich das normalisierte Filter-Array zurück.
     *
     * @return array{0: \Illuminate\Database\Eloquent\Builder, 1: array<string,mixed>}
     */
    private function buildIndexQuery(Request $request): array {
        $query = DiaryEntry::query()->with(['user:id,name', 'tags:id,name,color,slug'])->orderByDesc('start_at');

        if ($request->filled('status') && $request->status !== 'all') {
            $query->where('status', (int) $request->status);
        }

        if ($request->filled('from')) {
            $query->whereDate('start_at', '>=', $request->from);
        }
        if ($request->filled('to')) {
            $query->whereDate('start_at', '<=', $request->to);
        }

        if ($request->boolean('mine')) {
            $query->where('user_id', Auth::id());
        }

        $showArchived = $request->boolean('archived');
        if (! $showArchived) {
            $query->where('is_archived', false);
        }

        $tagId = $request->integer('tag');
        if ($tagId > 0) {
            $query->whereHas('tags', fn($q) => $q->where('tags.id', $tagId));
        }

        $projectId = $request->integer('project');
        if ($projectId > 0) {
            $query->where('project_id', $projectId);
        }

        $q = trim((string) $request->query('q', ''));
        if ($q !== '') {
            $like = '%' . str_replace(['%', '_'], ['\\%', '\\_'], $q) . '%';
            $query->where(function ($w) use ($like) {
                $w->where('content', 'like', $like)->orWhere('response', 'like', $like);
            });
        }

        $filters = $request->only('status', 'from', 'to', 'mine', 'archived', 'tag', 'project', 'q');

        return [$query, $filters];
    }

    public function create(Request $request): View {
        $isDialog = $request->boolean('dialog');
        /** @var User $auth */
        $auth = Auth::user();
        $canCreateForOthers = $auth->canCreateEntriesForOthers();

        // Prefills (z. B. aus der Wochenansicht)
        $prefillDate = $this->parsePrefillDate($request->query('date'));
        $prefillUserId = (int) $request->query('user_id', 0);
        if ($prefillUserId > 0 && ! $canCreateForOthers) {
            $prefillUserId = 0;
        }

        return view($isDialog ? 'diary._form_dialog' : 'diary.form', [
            'entry' => null,
            'isEdit' => false,
            'isDialog' => $isDialog,
            'allTags' => Tag::orderBy('name')->get(['id', 'name', 'color']),
            'selectedTagIds' => [],
            'canCreateForOthers' => $canCreateForOthers,
            'assignableUsers' => $canCreateForOthers ? User::orderBy('name')->get(['id', 'name']) : collect(),
            'prefillStartAt' => $prefillDate,
            'prefillUserId' => $prefillUserId,
        ]);
    }

    private function parsePrefillDate(?string $value): ?string {
        if (! $value) {
            return null;
        }
        try {
            return \Carbon\CarbonImmutable::parse($value)->format('Y-m-d\TH:i');
        } catch (\Exception) {
            return null;
        }
    }

    public function store(Request $request): RedirectResponse {
        $data = $this->validateEntry($request);
        $tagIds = $this->extractTagIds($request);
        $newTagNames = $this->extractNewTagNames($request);

        /** @var \App\Models\User $auth */
        $auth = Auth::user();

        $owner = $auth;
        $requestedUserId = (int) ($data['user_id'] ?? 0);
        if ($requestedUserId > 0 && $requestedUserId !== $auth->id && $auth->canCreateEntriesForOthers()) {
            $owner = User::findOrFail($requestedUserId);
        }
        unset($data['user_id']);

        /** @var DiaryEntry $entry */
        $entry = $owner->diaryEntries()->create($data);
        $entry->syncTagsFromInput($tagIds, $newTagNames);

        return redirect()->route('diary.show', $entry)->with('success', __('Eintrag gespeichert.'));
    }

    public function show(Request $request, DiaryEntry $diary): View {
        $diary->load(['user:id,name', 'tags:id,name,color,slug', 'comments.user:id,name', 'attachments.uploader:id,name']);

        // Falls der Eintrag aus einem Legacy-Import stammt, auch die Legacy-Daten laden
        $legacyEntry = null;
        if ($diary->legacy_id && filled(config('database.connections.legacy.database'))) {
            try {
                $legacyEntry = LegacyDiaryEntry::with('author:id,uname')->find($diary->legacy_id);
            } catch (\Exception) {
                // Legacy nicht erreichbar
            }
        }

        $view = $request->boolean('dialog') ? 'diary._show_dialog' : 'diary.show';

        return view($view, compact('diary', 'legacyEntry'));
    }

    public function edit(Request $request, DiaryEntry $diary): View {
        Gate::authorize('update', $diary);

        $diary->load('tags:id,name,color');
        $isDialog = $request->boolean('dialog');

        return view($isDialog ? 'diary._form_dialog' : 'diary.form', [
            'entry' => $diary,
            'isEdit' => true,
            'isDialog' => $isDialog,
            'allTags' => Tag::orderBy('name')->get(['id', 'name', 'color']),
            'selectedTagIds' => $diary->tags->pluck('id')->all(),
        ]);
    }

    public function update(Request $request, DiaryEntry $diary): RedirectResponse {
        Gate::authorize('update', $diary);

        $data = $this->validateEntry($request);
        unset($data['user_id']); // Eigentümer wird beim Update nicht geändert
        $tagIds = $this->extractTagIds($request);
        $newTagNames = $this->extractNewTagNames($request);

        $diary->update($data);
        $diary->syncTagsFromInput($tagIds, $newTagNames);

        return redirect()->route('diary.show', $diary)->with('success', __('Eintrag aktualisiert.'));
    }

    public function destroy(DiaryEntry $diary): RedirectResponse {
        Gate::authorize('delete', $diary);

        $diary->delete();

        return redirect()->route('diary.index')->with('success', __('Eintrag gelöscht.'));
    }

    /** @return array<string,mixed> */
    private function validateEntry(Request $request): array {
        return $request->validate([
            'content' => ['required', 'string', 'max:65535'],
            'response' => ['nullable', 'string', 'max:65535'],
            'status' => ['required', 'integer', 'in:-1,1,2,3'],
            'start_at' => ['nullable', 'date'],
            'end_at' => ['nullable', 'date', 'after_or_equal:start_at'],
            'user_id' => ['nullable', 'integer', 'exists:users,id'],
            'project_id' => ['nullable', 'integer', 'exists:projects,id'],
        ]);
    }

    /** @return array<int> */
    private function extractTagIds(Request $request): array {
        $raw = $request->input('tag_ids', []);
        if (! is_array($raw)) {
            return [];
        }

        return collect($raw)->filter(fn($v) => is_numeric($v))->map(fn($v) => (int) $v)->all();
    }

    /** @return array<string> */
    private function extractNewTagNames(Request $request): array {
        $raw = (string) $request->input('new_tags', '');
        if ($raw === '') {
            return [];
        }

        return collect(preg_split('/[,;\n]+/', $raw))
            ->map(fn($v) => trim((string) $v))
            ->filter()
            ->take(20)
            ->all();
    }

    public function archive(DiaryEntry $diary, ArchiveService $service): RedirectResponse {
        Gate::authorize('archive', $diary);

        $service->archiveEntry($diary);

        return redirect()->route('diary.show', $diary)->with('success', __('Eintrag archiviert.'));
    }

    public function restore(DiaryEntry $diary, ArchiveService $service): RedirectResponse {
        Gate::authorize('archive', $diary);

        $service->restoreEntry($diary);

        return redirect()->route('diary.show', $diary)->with('success', __('Eintrag wiederhergestellt.'));
    }
}
