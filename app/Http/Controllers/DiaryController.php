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

use App\Http\Controllers\Concerns\{FiltersDiaryEntries, ResolvesGlobalDateRange};
use App\Http\Requests\SaveDiaryEntryRequest;
use App\Legacy\LegacyBridge;
use App\Models\{Customer, DiaryEntry, EntryType, Tag, Tour, User};
use App\Services\Archive\ArchiveService;
use App\Services\SqidEncoder;
use App\Services\Timeline\DiaryEntryTimelineService;
use App\Services\UI\DateRangeContext;
use App\Support\{LookupCache, Sqid};
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\{RedirectResponse, Request};
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\{Auth, Gate};
use Illuminate\View\View;

class DiaryController extends Controller {
    use FiltersDiaryEntries, ResolvesGlobalDateRange;

    public function __construct(private readonly SqidEncoder $sqids) {}

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
                'COUNT(CASE WHEN is_archived = 0 AND status = 2 THEN 1 END) as cnt_planned,' .
                'COUNT(CASE WHEN is_archived = 0 AND status IN (1,3,4,5) THEN 1 END) as cnt_active,' .
                'COUNT(CASE WHEN is_archived = 0 AND status IN (-1,6,7) THEN 1 END) as cnt_done,' .
                'COUNT(CASE WHEN is_archived = 0 AND status = 8 THEN 1 END) as cnt_cancelled,' .
                'COUNT(CASE WHEN is_archived = 1 THEN 1 END) as cnt_archived'
        )->first()?->getAttributes() ?? [];

        $counts = [
            'all' => (int) ($row['cnt_all'] ?? 0),
            'planned' => (int) ($row['cnt_planned'] ?? 0),
            'active' => (int) ($row['cnt_active'] ?? 0),
            'done' => (int) ($row['cnt_done'] ?? 0),
            'cancelled' => (int) ($row['cnt_cancelled'] ?? 0),
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
            ->select($this->diaryListColumns())
            ->with(['user:id,name', 'tags:id,name,color,slug'])
            ->orderByDesc('start_at');

        $range = $this->globalDateRange();
        $filters = $this->applyDiaryFilters($query, $request, $range['from']->toDateString(), $range['to']->toDateString());

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
            'recentTagIds' => $this->recentTagIds((int) $auth->id),
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
        $data['status'] = \App\Enums\Diary\Status::Planned->value;
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
        Gate::authorize('view', $diary);

        $diary->load([
            'user:id,name',
            'assignedUser:id,name',
            'tags:id,name,color,slug',
            'comments.user:id,name',
            'attachments.uploader:id,name',
            'lifecycleEvents.actor:id,name',
            'protocols',
            'entryType:id,slug,label',
        ]);

        // Datenqualität (Feature 024): rein lesende Hinweise auf fehlende
        // Pflichtklassifikationen — abgeleitet aus den am Auftrag bereits
        // persistierten Werten (Auftragsart, Priorität).
        $dataQualityGaps = app(\App\Services\Classification\DataQualityInspector::class)
            ->diaryEntryGaps($diary);

        // Falls der Eintrag aus einem Legacy-Import stammt, auch die Legacy-Daten laden
        $legacyEntry = LegacyBridge::findDiaryEntryWithAuthor($diary->legacy_id);

        if ($request->boolean('dialog')) {
            return view('diary._show_dialog', compact('diary', 'legacyEntry', 'dataQualityGaps'));
        }

        // Auftrags-Timeline „Verlauf" (MVP-010): serverseitiger Typ-Filter +
        // einfaches „mehr laden" über wachsendes Limit.
        /** @var User $viewer */
        $viewer = Auth::user();
        $timelineType = (string) $request->query('timeline_type', '');
        $timelineLimit = max(1, min(500, (int) $request->query('timeline_limit', 50)));
        $timeline = app(DiaryEntryTimelineService::class)->forDiaryEntry(
            $diary,
            $viewer,
            $timelineType !== '' ? [$timelineType] : null,
            $timelineLimit,
        );

        // Prozeduren (Feature 026): bereits laufende Läufe + per
        // ProcedureApplicabilityResolver vorgeschlagene, noch nicht
        // gestartete Vorlagen für diesen Auftrag.
        $procedureRuns = collect();
        $suggestedProcedures = collect();
        if (Gate::allows(\App\Enums\User\Permission::ProcedureRunView->value)) {
            $procedureRuns = \App\Models\ProcedureRun::query()
                ->where('subject_type', $diary->getMorphClass())
                ->where('subject_id', $diary->getKey())
                ->with('templateVersion.template')
                ->orderByDesc('id')
                ->get();

            if (Gate::allows(\App\Enums\User\Permission::ProcedureRunStart->value)) {
                $startedTemplateIds = $procedureRuns
                    ->map(fn($run) => $run->templateVersion?->procedure_template_id)
                    ->filter()
                    ->all();
                $suggestedProcedures = app(\App\Services\Procedure\ProcedureApplicabilityResolver::class)
                    ->suggestFor($diary)
                    ->reject(fn($tpl) => in_array($tpl->id, $startedTemplateIds, true))
                    ->values();
            }
        }

        return view('diary.show', compact('diary', 'legacyEntry', 'dataQualityGaps') + [
            'timelineItems' => $timeline['items'],
            'timelineHasMore' => $timeline['hasMore'],
            'timelineType' => $timelineType,
            'timelineLimit' => $timelineLimit,
            'procedureRuns' => $procedureRuns,
            'suggestedProcedures' => $suggestedProcedures,
        ]);
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
            'selectedTagIds' => $diary->tags->map(fn(Tag $t) => $t->sqid)->all(),
            'recentTagIds' => $this->recentTagIds((int) $auth->id),
            'canCreateForOthers' => $canCreateForOthers,
            'assignableUsers' => $canCreateForOthers ? LookupCache::userDropdown() : collect(),
        ] + $this->entryTypeFormData($diary));
    }

    public function update(SaveDiaryEntryRequest $request, DiaryEntry $diary): RedirectResponse {
        Gate::authorize('update', $diary);

        $data = $request->validated();
        unset($data['user_id'], $data['status']); // Eigentümer und Lebenszyklus werden separat gesteuert
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

        // tag_ids kommen als opake Sqids aus dem Formular; rohe numerische IDs
        // werden als Backward-Compat-Fallback weiterhin akzeptiert.
        return collect($raw)
            ->map(fn($v) => is_scalar($v) ? Sqid::decodeOrNumeric(Tag::class, (string) $v) : null)
            ->filter()
            ->unique()
            ->values()
            ->all();
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
     * Zuletzt vom Nutzer vergebene Tags (für die Schnellauswahl im Formular).
     * Die Pivot `taggables` hat keine Timestamps, daher wird die Reihenfolge
     * über die jüngsten Diary-Einträge des Nutzers abgeleitet.
     *
     * @return array<string> Opake Tag-Sqids (passend zum Formular-Tag-Picker).
     */
    private function recentTagIds(int $userId, int $limit = 8): array {
        return Tag::query()
            ->select('tags.id')
            ->join('taggables', 'taggables.tag_id', '=', 'tags.id')
            ->join('diary_entries', function ($join): void {
                $join->on('diary_entries.id', '=', 'taggables.taggable_id')
                    ->where('taggables.taggable_type', '=', (new DiaryEntry())->getMorphClass());
            })
            ->where('diary_entries.user_id', $userId)
            ->orderByDesc('diary_entries.id')
            ->pluck('tags.id')
            ->map(fn($id) => (int) $id)
            ->unique()
            ->take($limit)
            ->map(fn(int $id) => $this->sqids->encode(Tag::class, $id))
            ->values()
            ->all();
    }

    /**
     * Liefert EntryTypes + Flags-Map + Kunden- & Tour-Optionen für die Formularansicht.
     *
     * @return array{entryTypes: Collection<int, EntryType>, entryTypeFlags: array<string, array<string, mixed>>, customerOptions: Collection<int, Customer>, tourOptions: Collection<int, Tour>}
     */
    private function entryTypeFormData(?DiaryEntry $entry = null): array {
        /** @var Collection<int, EntryType> $types */
        $types = EntryType::query()->active()->ordered()->get();

        // Edit-Fall: Der Ist-Typ des Eintrags bleibt wählbar, auch wenn er
        // inzwischen deaktiviert wurde. Sonst fiele das Select still auf
        // „ohne Typ" zurück (Typverlust beim Speichern) bzw. der Server
        // erzwänge Pflichtfelder, die das Formular gar nicht anzeigt.
        if ($entry?->entry_type_id !== null && ! $types->contains('id', $entry->entry_type_id)) {
            $current = EntryType::query()->find($entry->entry_type_id);
            if ($current instanceof EntryType) {
                $current->label .= ' (' . __('inaktiv') . ')';
                $types->push($current);
            }
        }

        $flagsMap = $types->mapWithKeys(fn(EntryType $t) => [$t->sqid => $t->flagsArray()])->all();

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
