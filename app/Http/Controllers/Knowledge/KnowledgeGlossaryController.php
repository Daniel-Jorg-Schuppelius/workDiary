<?php
/*
 * Created on   : Thu Nov 12 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : KnowledgeGlossaryController.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Http\Controllers\Knowledge;

use App\Http\Controllers\Concerns\ResolvesCurrentOrganization;
use App\Http\Controllers\Controller;
use App\Models\Knowledge\{KnowledgeGlossaryTerm, KnowledgePage, KnowledgeSearchGap};
use App\Models\Search\SearchSynonymGroup;
use App\Services\Knowledge\Pages\KnowledgePageAccess;
use App\Support\{SortableQuery, Sqid};
use CommonToolkit\Helper\Data\StringHelper;
use Illuminate\Http\{RedirectResponse, Request};
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

/**
 * Glossar mit Kürzeln und Synonymen sowie die Suchlücken (Feature 191,
 * MVP-1667). Das Glossar lesen alle Leser, pflegen und die Suchlücken sehen
 * die Verwaltenden.
 */
class KnowledgeGlossaryController extends Controller {
    use ResolvesCurrentOrganization;

    public function __construct(private readonly KnowledgePageAccess $access) {}

    public function index(Request $request): View {
        Gate::authorize('viewAny', KnowledgeGlossaryTerm::class);
        $user = $this->authUser();
        $q = StringHelper::normalizeWhitespace((string) $request->query('q', ''));
        $terms = KnowledgeGlossaryTerm::query()
            ->with('page:id,title,knowledge_space_id,approved_version')
            ->when($q !== '', static fn ($query) => $query->where(static fn ($w) => $w->whereLikeEscaped('term', $q)->orWhereLikeEscaped('synonyms', $q, 'both', true)->orWhereLikeEscaped('description', $q)))
            ->orderBy('term')
            ->paginate(50)
            ->withQueryString();

        return view('knowledge-pages.glossary', [
            'terms' => $terms,
            'q' => $q,
            'canManage' => Gate::allows('create', KnowledgeGlossaryTerm::class),
            'visiblePages' => $this->access->scope(KnowledgePage::query(), $user)->whereKey($terms->getCollection()->pluck('knowledge_page_id')->filter()->all())->pluck('id')->flip()->all(),
        ]);
    }

    public function create(Request $request): View {
        Gate::authorize('create', KnowledgeGlossaryTerm::class);

        return view('knowledge-pages._glossary_dialog', [
            'term' => null,
            'prefill' => StringHelper::truncate((string) $request->query('term', ''), 120, ''),
            'pages' => $this->pageOptions(),
        ]);
    }

    public function store(Request $request): RedirectResponse {
        Gate::authorize('create', KnowledgeGlossaryTerm::class);
        $data = $this->validated($request, null);
        KnowledgeGlossaryTerm::query()->create([
            ...$data,
            'organization_id' => $this->currentOrganization()->id,
            'created_by' => $this->authUser()->id,
            'updated_by' => $this->authUser()->id,
        ]);

        return redirect()->toList('knowledge.glossary.index')->with('success', __('knowledge_pages.flash.glossary_saved'));
    }

    public function edit(KnowledgeGlossaryTerm $term): View {
        Gate::authorize('update', $term);

        return view('knowledge-pages._glossary_dialog', ['term' => $term, 'prefill' => '', 'pages' => $this->pageOptions()]);
    }

    public function update(Request $request, KnowledgeGlossaryTerm $term): RedirectResponse {
        Gate::authorize('update', $term);
        $term->forceFill([...$this->validated($request, $term), 'updated_by' => $this->authUser()->id])->save();

        return redirect()->toList('knowledge.glossary.index')->with('success', __('knowledge_pages.flash.glossary_saved'));
    }

    public function destroy(KnowledgeGlossaryTerm $term): RedirectResponse {
        Gate::authorize('delete', $term);
        $term->delete();

        return back()->with('success', __('knowledge_pages.flash.glossary_deleted'));
    }

    /** Suchlücken: Begriffe ohne Treffer, ohne Person (MVP-1667). */
    public function gaps(Request $request): View {
        Gate::authorize('manageAny', KnowledgePage::class);
        [$sort] = SortableQuery::resolve($request, ['searches', 'recent'], 'searches');
        $showDismissed = $request->boolean('dismissed');
        $gaps = KnowledgeSearchGap::query()
            ->when(! $showDismissed, static fn ($q) => $q->whereNull('dismissed_at'))
            ->when($sort === 'recent', static fn ($q) => $q->orderByDesc('last_searched_at'), static fn ($q) => $q->orderByDesc('searches')->orderByDesc('last_searched_at'))
            ->paginate(50)
            ->withQueryString();

        return view('knowledge-pages.gaps', ['gaps' => $gaps, 'sort' => $sort, 'showDismissed' => $showDismissed]);
    }

    public function dismissGap(KnowledgeSearchGap $gap): RedirectResponse {
        Gate::authorize('manageAny', KnowledgePage::class);
        $gap->forceFill(['dismissed_at' => now()])->save();

        return back()->with('success', __('knowledge_pages.flash.gap_dismissed'));
    }

    /** @return array{term: string, synonyms: list<string>, description: string|null, knowledge_page_id: int|null} */
    private function validated(Request $request, ?KnowledgeGlossaryTerm $current): array {
        $data = $request->validate([
            'term' => ['required', 'string', 'min:2', 'max:120'],
            'synonyms' => ['nullable', 'string', 'max:2000'],
            'description' => ['nullable', 'string', 'max:5000'],
            'page' => ['nullable', 'string', 'max:64'],
        ]);
        $term = StringHelper::normalizeWhitespace((string) $data['term']);
        $ignore = $current?->id;
        $duplicate = KnowledgeGlossaryTerm::query()
            ->when($ignore !== null, static fn ($q) => $q->whereKeyNot($ignore))
            ->get(['id', 'term'])
            ->contains(static fn (KnowledgeGlossaryTerm $other): bool => StringHelper::toLower($other->term) === StringHelper::toLower($term));
        if ($duplicate) {
            throw ValidationException::withMessages(['term' => (string) __('knowledge_pages.glossary.error.duplicate')]);
        }
        $synonyms = array_values(array_filter(
            SearchSynonymGroup::parseTerms((string) ($data['synonyms'] ?? '')),
            static fn (string $synonym): bool => StringHelper::toLower($synonym) !== StringHelper::toLower($term) && mb_strlen($synonym) <= 120,
        ));
        $pageId = Sqid::decode(KnowledgePage::class, (string) ($data['page'] ?? ''));
        $page = $pageId !== null ? KnowledgePage::query()->find($pageId) : null;

        return [
            'term' => $term,
            'synonyms' => array_slice($synonyms, 0, KnowledgeGlossaryTerm::MAX_SYNONYMS),
            'description' => isset($data['description']) && trim((string) $data['description']) !== '' ? trim((string) $data['description']) : null,
            'knowledge_page_id' => $page !== null && Gate::allows('view', $page) ? $page->id : null,
        ];
    }

    /** @return \Illuminate\Database\Eloquent\Collection<int, KnowledgePage> */
    private function pageOptions() {
        return $this->access->scope(KnowledgePage::query(), $this->authUser())->orderBy('title')->limit(500)->get(['id', 'title']);
    }
}
