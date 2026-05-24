<?php
/*
 * Created on   : Wed Apr 29 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : DiaryController.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Http\Controllers;

use App\Enums\Diary\{LocationMode, Mode};
use App\Http\Controllers\Concerns\ResolvesGlobalDateRange;
use App\Http\Requests\SaveDiaryEntryRequest;
use App\Legacy\Models\LegacyDiaryEntry;
use App\Models\{Customer, DiaryEntry, EntryType, Tag, Tour, User};
use App\Services\Archive\ArchiveService;
use App\Services\UI\DateRangeContext;
use App\Support\LookupCache;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\{RedirectResponse, Request};
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\{Auth, Gate};
use Illuminate\View\View;

class DiaryController extends Controller {
    use ResolvesGlobalDateRange;

    public function index(Request $request): View|RedirectResponse {
        // Backward-Compat: alte Bookmarks mit ?from=&to= setzen den globalen
        // Range einmalig auf Custom und leiten dann auf die saubere URL um.
        if ($request->filled('from') || $request->filled('to')) {
            app(DateRangeContext::class)->set(
                DateRangeContext::PRESET_CUSTOM,
                (string) $request->query('from', ''),
                (string) $request->query('to', ''),
            );

            return redirect()->route('diary.index', $request->except(['from', 'to']));
        }

        [$query, $filters] = $this->buildIndexQuery($request);
        $entries = $query->paginate(20)->withQueryString();

        $row = DiaryEntry::query()->selectRaw(
            'COUNT(CASE WHEN is_archived = 0 THEN 1 END) as cnt_all,' .
                'COUNT(CASE WHEN is_archived = 0 AND status = 2 THEN 1 END) as cnt_open,' .
                'COUNT(CASE WHEN is_archived = 0 AND status = 3 THEN 1 END) as cnt_alert,' .
                'COUNT(CASE WHEN is_archived = 0 AND status = -1 THEN 1 END) as cnt_done,' .
                'COUNT(CASE WHEN is_archived = 1 THEN 1 END) as cnt_archived'
        )->first()?->getAttributes() ?? [];

        $counts = [
            'all' => (int) ($row['cnt_all'] ?? 0),
            'open' => (int) ($row['cnt_open'] ?? 0),
            'alert' => (int) ($row['cnt_alert'] ?? 0),
            'done' => (int) ($row['cnt_done'] ?? 0),
            'archived' => (int) ($row['cnt_archived'] ?? 0),
        ];

        return view('diary.index', [
            'entries' => $entries,
            'counts' => $counts,
            'filters' => $filters,
            'allTags' => $this->allTags(),
            'entryTypes' => EntryType::query()->active()->ordered()->get(),
        ]);
    }

    /**
     * Baut die gefilterte Query und gibt zusätzlich das normalisierte Filter-Array zurück.
     *
     * @return array{0: Builder<DiaryEntry>, 1: array<string,mixed>}
     */
    private function buildIndexQuery(Request $request): array {
        $query = DiaryEntry::query()
            ->select([
                'id',
                'user_id',
                'content',
                'status',
                'is_archived',
                'start_at',
                'end_at',
                'created_at',
                'mode',
                'due_date',
                'window_start_date',
                'window_end_date',
                'location_mode',
            ])
            ->with(['user:id,name', 'tags:id,name,color,slug'])
            ->orderByDesc('start_at');

        if ($request->filled('status') && $request->status !== 'all') {
            $query->where('status', (int) $request->status);
        }

        $range = $this->globalDateRange();
        $from = $range['from']->toDateString();
        $to = $range['to']->toDateString();

        // Datumsbereich gilt modus-abhängig: fixed → start_at, deadline → due_date,
        // window → Overlap mit window_*_date. Backlog/recurring haben kein Datum
        // und werden immer mitgenommen, damit sie nicht im Range verschwinden.
        $query->where(function ($q) use ($from, $to): void {
            $q->where(function ($sub) use ($from, $to): void {
                $sub->where('mode', Mode::Fixed->value)
                    ->whereDate('start_at', '>=', $from)
                    ->whereDate('start_at', '<=', $to);
            });
            $q->orWhere(function ($sub) use ($from, $to): void {
                $sub->where('mode', Mode::Deadline->value)
                    ->whereDate('due_date', '>=', $from)
                    ->whereDate('due_date', '<=', $to);
            });
            $q->orWhere(function ($sub) use ($from, $to): void {
                $sub->where('mode', Mode::Window->value)
                    ->whereDate('window_end_date', '>=', $from)
                    ->whereDate('window_start_date', '<=', $to);
            });
            $q->orWhereIn('mode', [Mode::Backlog->value, Mode::Recurring->value]);
        });

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

        $entryTypeId = $request->integer('entry_type');
        if ($entryTypeId > 0) {
            $query->where('entry_type_id', $entryTypeId);
        }

        $projectId = $request->integer('project');
        if ($projectId > 0) {
            $query->where('project_id', $projectId);
        }

        $modeFilter = (string) $request->query('mode', '');
        if ($modeFilter !== '' && in_array($modeFilter, Mode::values(), true)) {
            $query->where('mode', $modeFilter);
        }

        $locationFilter = (string) $request->query('location', '');
        if ($locationFilter !== '' && in_array($locationFilter, LocationMode::values(), true)) {
            $query->where('location_mode', $locationFilter);
        }

        $q = trim((string) $request->query('q', ''));
        if ($q !== '') {
            $like = '%' . str_replace(['%', '_'], ['\\%', '\\_'], $q) . '%';
            $query->where(function ($w) use ($like) {
                $w->where('content', 'like', $like)->orWhere('response', 'like', $like);
            });
        }

        $filters = $request->only('status', 'mine', 'archived', 'tag', 'project', 'q', 'entry_type', 'mode', 'location');
        $filters['from'] = $from;
        $filters['to'] = $to;

        return [$query, $filters];
    }

    public function create(Request $request): View {
        /** @var User $auth */
        $auth = Auth::user();
        $canCreateForOthers = $auth->canCreateEntriesForOthers();

        // Prefills (z. B. aus der Wochenansicht)
        $prefillDate = $this->parsePrefillDate($request->query('date'));
        $prefillUserId = (int) $request->query('user_id', 0);
        if ($prefillUserId > 0 && ! $canCreateForOthers) {
            $prefillUserId = 0;
        }
        $prefillEntryTypeId = (int) $request->query('entry_type_id', 0);

        return view('diary._form_dialog', [
            'entry' => null,
            'isEdit' => false,
            'isDialog' => true,
            'allTags' => $this->allTags(),
            'selectedTagIds' => [],
            'canCreateForOthers' => $canCreateForOthers,
            'assignableUsers' => $canCreateForOthers ? LookupCache::userDropdown() : collect(),
            'prefillStartAt' => $prefillDate,
            'prefillUserId' => $prefillUserId,
            'prefillEntryTypeId' => $prefillEntryTypeId,
        ] + $this->entryTypeFormData());
    }

    private function parsePrefillDate(?string $value): ?string {
        if (! $value) {
            return null;
        }
        try {
            return CarbonImmutable::parse($value)->format('Y-m-d\TH:i');
        } catch (\Exception) {
            return null;
        }
    }

    public function store(SaveDiaryEntryRequest $request): RedirectResponse {
        $data = $request->validated();
        $tagIds = $this->extractTagIds($request);
        $newTagNames = $this->extractNewTagNames($request);

        /** @var User $auth */
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

        /** @var User $auth */
        $auth = Auth::user();
        $canCreateForOthers = $auth->canCreateEntriesForOthers();

        return view('diary._form_dialog', [
            'entry' => $diary,
            'isEdit' => true,
            'isDialog' => true,
            'allTags' => $this->allTags(),
            'selectedTagIds' => $diary->tags->pluck('id')->all(),
            'canCreateForOthers' => $canCreateForOthers,
            'assignableUsers' => $canCreateForOthers ? LookupCache::userDropdown() : collect(),
        ] + $this->entryTypeFormData());
    }

    public function update(SaveDiaryEntryRequest $request, DiaryEntry $diary): RedirectResponse {
        Gate::authorize('update', $diary);

        $data = $request->validated();
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

        return collect(preg_split('/[,;\n]+/', $raw) ?: [])
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

    /** @return Collection<int, Tag> */
    private function allTags(): Collection {
        return LookupCache::tagOptions();
    }

    /**
     * Liefert EntryTypes + Flags-Map + Kunden- & Tour-Optionen für die Formularansicht.
     *
     * @return array{entryTypes: Collection<int, EntryType>, entryTypeFlags: array<int, array<string, mixed>>, customerOptions: Collection<int, Customer>, tourOptions: Collection<int, Tour>}
     */
    private function entryTypeFormData(): array {
        /** @var Collection<int, EntryType> $types */
        $types = EntryType::query()->active()->ordered()->get();
        $flagsMap = $types->mapWithKeys(fn(EntryType $t) => [$t->id => $t->flagsArray()])->all();

        /** @var Collection<int, Customer> $customers */
        $customers = Customer::query()->orderBy('name')->limit(500)->get(['id', 'name', 'company']);

        /** @var Collection<int, Tour> $tours */
        $tours = Tour::query()
            ->orderByDesc('tour_date')
            ->limit(100)
            ->get(['id', 'tour_date', 'name']);

        return [
            'entryTypes' => $types,
            'entryTypeFlags' => $flagsMap,
            'customerOptions' => $customers,
            'tourOptions' => $tours,
        ];
    }
}
