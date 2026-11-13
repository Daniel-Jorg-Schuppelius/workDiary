<?php
/*
 * Created on   : Thu Nov 12 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : KnowledgePageController.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Http\Controllers\Knowledge;

use App\Enums\Knowledge\{KnowledgePageKind, KnowledgePageStatus};
use App\Http\Controllers\Concerns\ResolvesCurrentOrganization;
use App\Http\Controllers\Controller;
use App\Models\Knowledge\{ContentCollection, KnowledgeGlossaryTerm, KnowledgePage, KnowledgePageVersion, KnowledgeSection, KnowledgeSpace};
use App\Models\Platform\User;
use App\Services\Attachments\FileAttacher;
use App\Services\Collections\ContentCollectionService;
use App\Services\Knowledge\Pages\{KnowledgePageAccess, KnowledgePageContent, KnowledgePageDiff, KnowledgePageLinks, KnowledgePagePdfRenderer, KnowledgePageSearch, KnowledgePageService, KnowledgePageVisits, KnowledgeReadingService};
use App\Support\{CarbonFmt, Sqid};
use Carbon\CarbonImmutable;
use CommonToolkit\Helper\Data\StringHelper;
use Illuminate\Database\Eloquent\{Builder, Collection};
use Illuminate\Http\{RedirectResponse, Request, Response};
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\{Rule, ValidationException};
use Illuminate\View\View;

/**
 * Interne Wissensdatenbank (Feature 191, MVP-1664–1668): Baum aus Bereichen,
 * Rubriken und Seiten, Suche mit Glossar und Suchlücken, Seite mit Fassung,
 * Freigabe, Prüfung, Pflichtlektüre und Verweisen, Fassungsvergleich,
 * Papierkorb und PDF.
 */
class KnowledgePageController extends Controller {
    use ResolvesCurrentOrganization;

    /** Filter der Liste (neben Bereich, Rubrik und Suche). */
    public const array FILTERS = ['mine', 'changed', 'review', 'draft', 'emergency'];

    /** Höchstens so viele Seiten trägt der Baum links. */
    private const int TREE_LIMIT = 1500;

    public function __construct(
        private readonly KnowledgePageService $pages,
        private readonly KnowledgePageAccess $access,
        private readonly KnowledgePageContent $content,
        private readonly KnowledgePageSearch $search,
        private readonly KnowledgePageVisits $visits,
        private readonly KnowledgeReadingService $readings,
    ) {}

    public function index(Request $request): View {
        Gate::authorize('viewAny', KnowledgePage::class);
        $user = $this->authUser();
        $organization = $this->currentOrganization();

        $spaces = KnowledgeSpace::query()
            ->whereIn('id', $this->access->readableSpaceIds($user) ?: [0])
            ->with('sections')
            ->orderBy('position')
            ->orderBy('title')
            ->get();
        $space = $this->spaceFromQuery($spaces, (string) $request->query('space', ''));
        $section = $space !== null ? $space->sections->firstWhere('sqid', (string) $request->query('section', '')) : null;
        $filter = in_array($request->query('filter'), self::FILTERS, true) ? (string) $request->query('filter') : '';
        $term = StringHelper::truncate(StringHelper::normalizeWhitespace((string) $request->query('q', '')), 120, '');

        $treePages = $this->access->scope(KnowledgePage::query(), $user)
            ->orderBy('position')
            ->orderBy('title')
            ->limit(self::TREE_LIMIT)
            ->get(['id', 'knowledge_space_id', 'knowledge_section_id', 'parent_id', 'title', 'kind', 'status', 'version', 'approved_version', 'position']);

        $list = $this->access->scope(KnowledgePage::query(), $user)->with(['space:id,title', 'section:id,title', 'owner:id,name', 'tags:id,name']);
        $forms = [];
        if ($term !== '') {
            $forms = $this->search->expand((int) $organization->id, $term);
            $this->search->apply($list, $user, $forms);
        }
        if ($space !== null) {
            $list->where('knowledge_space_id', $space->id);
        }
        if ($section !== null) {
            $list->where('knowledge_section_id', $section->id);
        }
        $this->applyFilter($list, $filter, $user);
        $pages = $list->orderByDesc('updated_at')->paginate(30)->withQueryString();

        if ($term !== '' && $pages->total() === 0 && $space === null && $filter === '') {
            $this->search->recordGap((int) $organization->id, $term);
        }

        return view('knowledge-pages.index', [
            'spaces' => $spaces,
            'space' => $space,
            'section' => $section,
            'tree' => $this->tree($spaces, $treePages),
            'pages' => $pages,
            'filter' => $filter,
            'term' => $term,
            'forms' => $forms,
            'snippets' => $term !== '' ? $this->snippets($pages->getCollection(), $user, $forms) : [],
            'changed' => $this->visits->changedSinceLastVisit($pages->getCollection(), $user),
            'openReadings' => count($this->readings->openFor($user)),
            'canEdit' => Gate::allows('create', KnowledgePage::class),
            'canManage' => Gate::allows('manageAny', KnowledgePage::class),
            'canForceDelete' => $user->isAdmin() || $user->can(\App\Enums\User\Permission::KnowledgePageForceDelete->value),
        ]);
    }

    public function create(Request $request): View {
        Gate::authorize('create', KnowledgePage::class);
        $user = $this->authUser();
        $spaces = $this->editableSpaces($user);
        $parent = $this->pageFromSqid((string) $request->query('parent', ''), $user);

        return view('knowledge-pages._form_dialog', [
            'page' => null,
            'spaces' => $spaces,
            'selectedSpace' => $parent !== null ? (string) $parent->space?->sqid : (string) $request->query('space', ''),
            'selectedSection' => (string) $request->query('section', ''),
            'parent' => $parent,
            'kind' => KnowledgePageKind::tryFrom((string) $request->query('kind', '')) ?? KnowledgePageKind::Page,
            'title' => (string) $request->query('title', ''),
            'canApprove' => true,
            'collectionOptions' => Gate::allows('create', ContentCollection::class) ? app(ContentCollectionService::class)->tree($user) : [],
        ]);
    }

    public function store(Request $request): RedirectResponse {
        Gate::authorize('create', KnowledgePage::class);
        $user = $this->authUser();
        $data = $request->validate([
            'space' => ['required', 'string', 'max:64'],
            'section' => ['nullable', 'string', 'max:64'],
            'parent' => ['nullable', 'string', 'max:64'],
            'kind' => ['required', Rule::enum(KnowledgePageKind::class)],
            ...$this->contentRules(),
            'approve' => ['nullable', 'boolean'],
            'collection' => ['nullable', 'string', 'max:64'],
        ]);

        $space = $this->spaceForWrite((string) $data['space'], $user);
        $section = $this->sectionOf($space, (string) ($data['section'] ?? ''));
        $parent = $this->pageFromSqid((string) ($data['parent'] ?? ''), $user);
        $page = $this->pages->create($space, $section, $parent, $user, [
            'title' => (string) $data['title'],
            'body' => $data['body'] ?? null,
            'kind' => (string) $data['kind'],
            'tags' => $data['tags'] ?? null,
            'note' => $data['note'] ?? null,
        ]);
        $this->storeUploads($page, $request, $user);
        $this->placeInCollection($page, (string) ($data['collection'] ?? ''), $user);
        if ((bool) ($data['approve'] ?? false) && Gate::allows('approve', $page)) {
            $this->pages->approve($page, $user);
        }

        return redirect()->route('knowledge.pages.show', $page)->with('success', __('knowledge_pages.flash.created'));
    }

    public function show(Request $request, KnowledgePage $page): View {
        Gate::authorize('view', $page);
        $user = $this->authUser();
        $page->load(['space', 'section', 'parent:id,title,parent_id', 'owner:id,name', 'approver:id,name', 'reviewer:id,name', 'creator:id,name', 'updater:id,name', 'tags', 'attachments']);

        $requested = $request->integer('version') ?: null;
        $olderVersion = $this->access->canEdit($user) && $requested !== null && $requested !== $page->version;
        $content = $this->content->forViewer($page, $user, $olderVersion ? $requested : null);
        // Wer bewusst eine ältere Fassung aufruft, hat die gezeigte nicht „besucht“.
        $seen = $olderVersion ? null : $this->visits->record($page, $user);
        $shown = $this->access->displayedVersion($page, $user);
        $required = $this->readings->isRequiredFor($page, $user);

        return view('knowledge-pages.show', [
            'page' => $page,
            'content' => $content,
            'html' => $this->content->html($page, $content['body']),
            'steps' => array_map(fn (array $step): array => [
                'body' => $step['body'],
                'image' => $this->content->stepImage($step['image'], $step['markers'], false),
                'markers' => $step['markers'],
            ], $content['steps']),
            'changedSince' => $seen !== null && $shown !== null && $seen < $shown ? $seen : null,
            'children' => $this->access->scope($page->children()->getQuery(), $user)->orderBy('position')->orderBy('title')->get(['id', 'title', 'status', 'kind']),
            'links' => app(KnowledgePageLinks::class)->subjectsOf($page, $user),
            'readingRequired' => $required,
            'readingConfirmed' => $required && $this->readings->hasConfirmedCurrent($page, $user),
            'glossary' => KnowledgeGlossaryTerm::query()->where('knowledge_page_id', $page->id)->orderBy('term')->get(),
            'isEditor' => $this->access->canEdit($user),
            'attachments' => $page->attachments->whereNull('meta_type')->values(),
        ]);
    }

    public function edit(KnowledgePage $page): View {
        Gate::authorize('update', $page);
        $page->load('tags');

        return view('knowledge-pages._form_dialog', [
            'page' => $page,
            'spaces' => collect(),
            'selectedSpace' => '',
            'selectedSection' => '',
            'parent' => null,
            'kind' => $page->kind,
            'title' => $page->title,
            'canApprove' => Gate::allows('approve', $page),
            'collectionOptions' => [],
        ]);
    }

    public function update(Request $request, KnowledgePage $page): RedirectResponse {
        Gate::authorize('update', $page);
        $user = $this->authUser();
        $data = $request->validate([
            ...$this->contentRules(),
            'base_version' => ['required', 'integer', 'min:1'],
            'approve' => ['nullable', 'boolean'],
        ]);
        $page = $this->pages->update($page, $user, [
            'title' => (string) $data['title'],
            'body' => $data['body'] ?? null,
            'tags' => (string) ($data['tags'] ?? ''),
            'note' => $data['note'] ?? null,
        ], (int) $data['base_version']);
        $this->storeUploads($page, $request, $user);
        if ((bool) ($data['approve'] ?? false) && $page->hasPendingVersion() && Gate::allows('approve', $page)) {
            $this->pages->approve($page, $user);
        }

        return redirect()->route('knowledge.pages.show', $page)->with('success', __('knowledge_pages.flash.updated'));
    }

    public function approve(KnowledgePage $page): RedirectResponse {
        Gate::authorize('approve', $page);
        $this->pages->approve($page, $this->authUser());

        return back()->with('success', __('knowledge_pages.flash.approved', ['version' => $page->refresh()->approved_version]));
    }

    public function outdate(KnowledgePage $page): RedirectResponse {
        Gate::authorize('approve', $page);
        $this->pages->markOutdated($page);

        return back()->with('success', __('knowledge_pages.flash.outdated'));
    }

    /** „Stimmt, aktuell“ (MVP-1665). */
    public function review(KnowledgePage $page): RedirectResponse {
        Gate::authorize('review', $page);
        $page = $this->pages->confirmCurrent($page, $this->authUser());

        return back()->with('success', $page->next_review_on !== null
            ? __('knowledge_pages.flash.reviewed_next', ['date' => CarbonFmt::fdate($page->next_review_on)])
            : __('knowledge_pages.flash.reviewed'));
    }

    public function responsibility(KnowledgePage $page): View {
        Gate::authorize('manage', $page);
        $user = $this->authUser();
        $page->load('space.sections');

        return view('knowledge-pages._responsibility_dialog', [
            'page' => $page,
            'members' => User::query()->forOrganization($this->currentOrganization())->whereNull('customer_id')->whereNull('deactivated_at')->orderBy('name')->get(['id', 'name']),
            'spaces' => $this->editableSpaces($user),
            'parents' => $this->access->scope(KnowledgePage::query(), $user)
                ->where('knowledge_space_id', $page->knowledge_space_id)
                ->whereKeyNot($page->id)
                ->orderBy('title')
                ->get(['id', 'title']),
        ]);
    }

    public function updateResponsibility(Request $request, KnowledgePage $page): RedirectResponse {
        Gate::authorize('manage', $page);
        $user = $this->authUser();
        $data = $request->validate([
            'owner' => ['nullable', 'string', 'max:64'],
            'review_interval_months' => ['nullable', 'integer', 'min:1', 'max:60'],
            'valid_until' => ['nullable', 'date_format:Y-m-d'],
            'is_emergency' => ['nullable', 'boolean'],
            'space' => ['required', 'string', 'max:64'],
            'section' => ['nullable', 'string', 'max:64'],
            'parent' => ['nullable', 'string', 'max:64'],
        ]);
        $ownerId = Sqid::decode(User::class, (string) ($data['owner'] ?? ''));
        $owner = $ownerId !== null ? User::query()->forOrganization($this->currentOrganization())->whereNull('customer_id')->find($ownerId) : null;
        $space = $this->spaceForWrite((string) $data['space'], $user);

        $this->pages->updateResponsibility($page, $user, [
            'owner_user_id' => $owner?->id,
            'review_interval_months' => isset($data['review_interval_months']) ? (int) $data['review_interval_months'] : null,
            'valid_until' => $data['valid_until'] ?? null,
            'is_emergency' => (bool) ($data['is_emergency'] ?? false),
            'space' => $space,
            'section' => $this->sectionOf($space, (string) ($data['section'] ?? '')),
            'parent' => $this->pageFromSqid((string) ($data['parent'] ?? ''), $user),
        ]);

        return redirect()->route('knowledge.pages.show', $page)->with('success', __('knowledge_pages.flash.responsibility_saved'));
    }

    /** Fassungen mit Vergleich zweier Fassungen (MVP-1664). */
    public function versions(Request $request, KnowledgePage $page, KnowledgePageDiff $diff): View {
        Gate::authorize('update', $page);
        $versions = $page->versions()->with(['creator:id,name', 'approver:id,name'])->get();
        $to = $versions->firstWhere('version', $request->integer('to') ?: $page->version) ?? $versions->first();
        $fromNumber = $request->integer('from') ?: ($to !== null ? $to->version - 1 : 0);
        $from = $versions->firstWhere('version', $fromNumber);

        return view('knowledge-pages.versions', [
            'page' => $page,
            'versions' => $versions,
            'from' => $from,
            'to' => $to,
            'titleChanged' => $from !== null && $to !== null && $from->title !== $to->title,
            'diff' => $from !== null && $to !== null ? $diff->lines($from->body, $to->body) : [],
        ]);
    }

    public function restoreVersion(KnowledgePage $page, KnowledgePageVersion $version): RedirectResponse {
        Gate::authorize('update', $page);
        abort_unless((int) $version->knowledge_page_id === (int) $page->id, 404);
        $page = $this->pages->restoreVersion($page, $version, $this->authUser());

        return redirect()->route('knowledge.pages.show', $page)->with('success', __('knowledge_pages.flash.version_restored', ['version' => $version->version, 'new' => $page->version]));
    }

    public function destroy(KnowledgePage $page): RedirectResponse {
        Gate::authorize('delete', $page);
        $this->pages->trash($page, $this->authUser());

        return redirect()->toList('knowledge.pages.index')->with('success', __('knowledge_pages.flash.trashed', ['days' => KnowledgePage::TRASH_DAYS]));
    }

    /** Papierkorb (MVP-1664): eigene Seiten, mit Verwaltungsrecht alle lesbaren. */
    public function trash(): View {
        Gate::authorize('viewAny', KnowledgePage::class);
        $user = $this->authUser();
        $manage = Gate::allows('manageAny', KnowledgePage::class);
        $pages = KnowledgePage::query()->onlyTrashed()
            ->whereIn('knowledge_space_id', $this->access->readableSpaceIds($user) ?: [0])
            ->when(! $manage, static fn (Builder $q) => $q->where('owner_user_id', $user->id))
            ->with(['space:id,title', 'trasher:id,name'])
            ->orderByDesc('deleted_at')
            ->paginate(30);

        return view('knowledge-pages.trash', [
            'pages' => $pages,
            'trashDays' => KnowledgePage::TRASH_DAYS,
        ]);
    }

    public function restore(string $page): RedirectResponse {
        $trashed = $this->trashedPage($page);
        Gate::authorize('restore', $trashed);
        $this->pages->restore($trashed, $this->authUser());

        return redirect()->route('knowledge.pages.show', $trashed)->with('success', __('knowledge_pages.flash.restored'));
    }

    public function forceDelete(string $page): RedirectResponse {
        $trashed = $this->trashedPage($page);
        Gate::authorize('forceDelete', $trashed);
        $this->pages->forceDelete($trashed, $this->authUser());

        return redirect()->route('knowledge.pages.trash')->with('success', __('knowledge_pages.flash.purged'));
    }

    public function pdf(KnowledgePage $page, KnowledgePagePdfRenderer $renderer): Response {
        Gate::authorize('view', $page);
        $page->audit('knowledge_page.exported', ['format' => 'pdf']);

        return response($renderer->page($page, $this->authUser()), 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="' . $renderer->filename($page) . '"',
        ]);
    }

    /** Notfallhandbuch als PDF (MVP-1668): alle lesbaren, freigegebenen Notfallseiten. */
    public function emergencyPdf(KnowledgePagePdfRenderer $renderer): Response {
        Gate::authorize('viewAny', KnowledgePage::class);

        return response($renderer->emergencyHandbook($this->authUser()), 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="' . $renderer->filename(null) . '"',
        ]);
    }

    /** Lesebestätigung der freigegebenen Fassung (MVP-1666). */
    public function confirmRead(KnowledgePage $page): RedirectResponse {
        Gate::authorize('view', $page);
        $read = $this->readings->confirm($page, $this->authUser());

        return back()->with('success', __('knowledge_pages.flash.read_confirmed', ['version' => $read->version]));
    }

    /**
     * Textauszüge der Treffer aus der Fassung, die die Person sieht — Leser
     * bekommen nie Text aus einem Entwurf.
     *
     * @param  Collection<int, KnowledgePage>  $pages
     * @param  list<string>  $forms
     * @return array<int, string>
     */
    private function snippets(Collection $pages, User $user, array $forms): array {
        if ($this->access->canEdit($user)) {
            return $pages->mapWithKeys(fn (KnowledgePage $page): array => [$page->id => $this->search->snippet($page->body, $forms)])->all();
        }
        $versions = KnowledgePageVersion::query()
            ->whereIn('knowledge_page_id', $pages->modelKeys())
            ->whereIn('version', $pages->pluck('approved_version')->filter()->unique()->values()->all())
            ->get(['knowledge_page_id', 'version', 'body']);
        $snippets = [];
        foreach ($pages as $page) {
            $approved = $versions->first(static fn (KnowledgePageVersion $v): bool => (int) $v->knowledge_page_id === (int) $page->id && $v->version === $page->approved_version);
            $snippets[$page->id] = $approved !== null ? $this->search->snippet($approved->body, $forms) : '';
        }

        return $snippets;
    }

    /** @return array<string, list<mixed>> */
    private function contentRules(): array {
        return [
            'title' => ['required', 'string', 'min:2', 'max:180'],
            'body' => ['nullable', 'string', 'max:' . KnowledgePage::MAX_BODY_LENGTH],
            'tags' => ['nullable', 'string', 'max:500'],
            'note' => ['nullable', 'string', 'max:500'],
            'attachments' => ['nullable', 'array', 'max:10'],
            'attachments.*' => FileAttacher::rule(),
        ];
    }

    /** @param  Builder<KnowledgePage>  $query */
    private function applyFilter(Builder $query, string $filter, User $user): void {
        $today = CarbonImmutable::today();
        $shownColumn = $this->access->canEdit($user) ? 'knowledge_pages.version' : 'knowledge_pages.approved_version';
        match ($filter) {
            'mine' => $query->where('owner_user_id', $user->id),
            'changed' => $query->whereExists(static fn ($visit) => $visit->selectRaw('1')->from('knowledge_page_visits')
                ->whereColumn('knowledge_page_visits.knowledge_page_id', 'knowledge_pages.id')
                ->where('knowledge_page_visits.user_id', $user->id)
                ->whereColumn('knowledge_page_visits.seen_version', '<', $shownColumn)),
            'review' => $query->where(static fn (Builder $q) => $q
                ->where('next_review_on', '<', \App\Support\Query\DateRange::dayAfter($today->addDays(14)))
                ->orWhere('status', KnowledgePageStatus::Outdated->value)),
            'draft' => $query->where('status', KnowledgePageStatus::Draft->value),
            'emergency' => $query->where('is_emergency', true),
            default => null,
        };
    }

    /**
     * Baum: Bereich → Rubriken → Seiten mit Unterseiten.
     *
     * @param  Collection<int, KnowledgeSpace>  $spaces
     * @param  Collection<int, KnowledgePage>  $pages
     * @return list<array{space: KnowledgeSpace, loose: list<array<string, mixed>>, sections: list<array{section: KnowledgeSection, pages: list<array<string, mixed>>}>}>
     */
    private function tree(Collection $spaces, Collection $pages): array {
        $byParent = $pages->groupBy(static fn (KnowledgePage $page): string => (string) ($page->parent_id ?? 0));
        $visibleIds = array_flip($pages->modelKeys());
        $branch = function (KnowledgePage $page, int $depth) use (&$branch, $byParent): array {
            return [
                'page' => $page,
                'children' => $depth >= KnowledgePage::MAX_DEPTH ? [] : array_values(array_map(static fn (KnowledgePage $child): array => $branch($child, $depth + 1), ($byParent->get((string) $page->id) ?? new Collection())->all())),
            ];
        };
        // Wurzeln: ohne Eltern oder mit unsichtbarem Elternteil.
        $roots = $pages->filter(static fn (KnowledgePage $page): bool => $page->parent_id === null || ! isset($visibleIds[$page->parent_id]));

        $tree = [];
        foreach ($spaces as $space) {
            $inSpace = $roots->where('knowledge_space_id', $space->id);
            $tree[] = [
                'space' => $space,
                'loose' => array_values(array_map(static fn (KnowledgePage $page): array => $branch($page, 1), $inSpace->whereNull('knowledge_section_id')->all())),
                'sections' => array_values(array_map(static fn (KnowledgeSection $section): array => [
                    'section' => $section,
                    'pages' => array_values(array_map(static fn (KnowledgePage $page): array => $branch($page, 1), $inSpace->where('knowledge_section_id', $section->id)->all())),
                ], $space->sections->all())),
            ];
        }

        return $tree;
    }

    /** @param  Collection<int, KnowledgeSpace>  $spaces */
    private function spaceFromQuery(Collection $spaces, string $sqid): ?KnowledgeSpace {
        return $sqid !== '' ? $spaces->firstWhere('sqid', $sqid) : null;
    }

    /** @return Collection<int, KnowledgeSpace> */
    private function editableSpaces(User $user): Collection {
        return KnowledgeSpace::query()
            ->whereIn('id', $this->access->readableSpaceIds($user) ?: [0])
            ->with('sections')
            ->orderBy('position')
            ->orderBy('title')
            ->get();
    }

    private function spaceForWrite(string $sqid, User $user): KnowledgeSpace {
        $id = Sqid::decode(KnowledgeSpace::class, $sqid);
        $space = $id !== null ? KnowledgeSpace::query()->find($id) : null;
        abort_if(! $space instanceof KnowledgeSpace || ! $this->access->canReadSpace($user, (int) $space->id), 404);

        return $space;
    }

    private function sectionOf(KnowledgeSpace $space, string $sqid): ?KnowledgeSection {
        if ($sqid === '') {
            return null;
        }
        $id = Sqid::decode(KnowledgeSection::class, $sqid);
        $section = $id !== null ? KnowledgeSection::query()->where('knowledge_space_id', $space->id)->find($id) : null;
        if (! $section instanceof KnowledgeSection) {
            throw ValidationException::withMessages(['section' => (string) __('knowledge_pages.error.section_space')]);
        }

        return $section;
    }

    private function pageFromSqid(string $sqid, User $user): ?KnowledgePage {
        if ($sqid === '') {
            return null;
        }
        $id = Sqid::decode(KnowledgePage::class, $sqid);
        $page = $id !== null ? KnowledgePage::query()->with('space')->find($id) : null;
        abort_if(! $page instanceof KnowledgePage || ! Gate::forUser($user)->allows('view', $page), 404);

        return $page;
    }

    private function trashedPage(string $sqid): KnowledgePage {
        $id = Sqid::decode(KnowledgePage::class, $sqid);
        $page = $id !== null ? KnowledgePage::query()->onlyTrashed()->find($id) : null;
        abort_if(! $page instanceof KnowledgePage, 404);

        return $page;
    }

    private function storeUploads(KnowledgePage $page, Request $request, User $user): void {
        $files = $request->file('attachments');
        if (! is_array($files)) {
            return;
        }
        $attacher = app(FileAttacher::class);
        foreach ($files as $file) {
            if ($file->isValid()) {
                $attacher->store($page, $file, $user->id, ['organization_id' => $page->organization_id]);
            }
        }
    }

    private function placeInCollection(KnowledgePage $page, string $sqid, User $user): void {
        if ($sqid === '' || ! Gate::allows('create', ContentCollection::class)) {
            return;
        }
        $collection = ContentCollection::query()->visibleTo($user)->whereNull('archived_at')
            ->find(Sqid::decodeOrNumeric(ContentCollection::class, $sqid));
        if ($collection !== null && Gate::allows('update', $collection)) {
            app(ContentCollectionService::class)->addItem($collection, $user, $page);
        }
    }
}
