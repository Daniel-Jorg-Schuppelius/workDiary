<?php
/*
 * Created on   : Wed Jun 10 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : KnowledgeArticleController.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Http\Controllers\Knowledge;

use App\Enums\Knowledge\ArticleStatus;
use App\Enums\User\Permission as P;
use App\Http\Controllers\Controller;
use App\Http\Controllers\Document\DocumentController;
use App\Models\Asset\Asset;
use App\Models\Classification\Tag;
use App\Models\Customer\Customer;
use App\Models\Diary\DiaryEntry;
use App\Models\Knowledge\{ContentCollection, ContentCollectionItem, ContentReference, KnowledgeArticle};
use App\Models\Platform\User;
use App\Models\Protocol\Protocol;
use App\Services\Attachments\FileAttacher;
use App\Services\Collections\ContentCollectionService;
use App\Services\Knowledge\KnowledgeArticleService;
use App\Support\Sqid;
use Illuminate\Database\Eloquent\{Builder, Model};
use Illuminate\Http\{RedirectResponse, Request};
use Illuminate\Support\Facades\{Auth, Gate};
use Illuminate\View\View;

class KnowledgeArticleController extends Controller {
    /**
     * Whitelist der erlaubten Verknüpfungs-Typen (Problemhistorie).
     * Verhindert, dass Aufrufer beliebige Klassen an `linkable_type`
     * setzen können — analog DocumentController::DOCUMENTABLE_MAP.
     *
     * @var array<string, class-string<Model>>
     */
    private const LINKABLE_MAP = [
        'diary' => DiaryEntry::class,
        'asset' => Asset::class,
        'customer' => Customer::class,
        'protocol' => Protocol::class,
    ];

    public function __construct(
        private readonly KnowledgeArticleService $service,
        private readonly ContentCollectionService $collections,
    ) {}

    public function index(Request $request): View {
        Gate::authorize('viewAny', KnowledgeArticle::class);

        /** @var User $user */
        $user = Auth::user();
        // Redaktion (knowledge.publish) sieht alle Status + Status-Filter;
        // alle anderen sehen Veröffentlichtes plus EIGENE Entwürfe/Archive.
        $canModerate = $user->isAdmin() || $user->can(P::KnowledgePublish->value);

        $filters = [
            'q' => trim((string) $request->query('q', '')),
            'collection' => (string) $request->query('collection', ''),
            // Schlagwort (MVP-821): dieselbe Achse wie im Einstieg, damit der
            // Wechsel in diese Typansicht den Filter nicht verliert.
            'tag' => (string) $request->query('tag', ''),
            'status' => (string) $request->query('status', 'all'),
            'sort' => (string) $request->query('sort', 'newest'),
        ];

        // Einmal formuliert, zweimal gebraucht: für die Liste und für die
        // Schlagwortauswahl der Filterleiste — sonst verriete sie Stichworte
        // aus fremden Entwürfen.
        $visibleToViewer = static function (Builder $articles) use ($canModerate, $user): void {
            if ($canModerate) {
                return;
            }
            $articles->where(static fn (Builder $w) => $w
                ->where('status', ArticleStatus::Published->value)
                ->orWhere('created_by_user_id', $user->id));
        };

        $query = KnowledgeArticle::query()->with(['creator', 'tags'])->where($visibleToViewer);

        if ($canModerate) {
            if (ArticleStatus::tryFrom($filters['status']) !== null) {
                $query->where('status', $filters['status']);
            }
        } else {
            $filters['status'] = 'all';
        }

        if ($filters['q'] !== '') {
            $query->search($filters['q']);
        }
        // Sammlung statt Freitext-Kategorie (MVP-814), samt Untersammlungen.
        $mayViewCollections = Gate::allows('viewAny', ContentCollection::class);
        $collectionId = $mayViewCollections && $filters['collection'] !== ''
            ? Sqid::decodeOrNumeric(ContentCollection::class, $filters['collection'])
            : null;
        if ($collectionId !== null) {
            $collectionIds = $this->collections->visibleSubtreeIds($user, $user->organization_id, $collectionId);
            $query->whereIn('knowledge_articles.id', static fn ($items) => $items
                ->select('collectable_id')
                ->from('collection_items')
                ->where('collectable_type', (new KnowledgeArticle)->getMorphClass())
                ->whereIn('collection_id', $collectionIds === [] ? [0] : $collectionIds));
        } else {
            $filters['collection'] = '';
        }

        $tagId = Sqid::decode(Tag::class, $filters['tag']);
        if ($tagId !== null) {
            $query->whereHas('tags', static fn (Builder $tag) => $tag->whereKey($tagId));
        } else {
            $filters['tag'] = '';
        }

        if ($filters['sort'] === 'helpful') {
            $query->orderByDesc('helpful_count')->orderByDesc('created_at');
        } else {
            $filters['sort'] = 'newest';
            $query->orderByDesc('created_at');
        }

        $articles = $query->paginate(25)->withQueryString();

        $hasActiveFilters = $filters['q'] !== ''
            || $filters['collection'] !== ''
            || $filters['tag'] !== ''
            || $filters['status'] !== 'all'
            || $filters['sort'] !== 'newest';

        return view('knowledge.index', [
            'articles' => $articles,
            'filters' => $filters,
            'collectionTree' => $mayViewCollections ? $this->collections->tree($user) : [],
            'articleCollections' => $mayViewCollections ? $this->collectionsByArticle($user, $articles->getCollection()->modelKeys()) : [],
            'hasActiveFilters' => $hasActiveFilters,
            'canCreate' => Gate::allows('create', KnowledgeArticle::class),
            'canModerate' => $canModerate,
            'tags' => Tag::query()
                ->whereHas('knowledgeArticles', $visibleToViewer)
                ->orderBy('name')
                ->get(['id', 'name']),
        ]);
    }

    public function show(KnowledgeArticle $article): View {
        Gate::authorize('view', $article);

        /** @var User $user */
        $user = Auth::user();
        $article->load(['creator', 'tags', 'links.target', 'links.creator', 'attachments']);

        return view('knowledge.show', [
            'article' => $article,
            'articleCollections' => Gate::allows('viewAny', ContentCollection::class) ? $this->collections->collectionsContaining($article, $user) : collect(),
            'ownFeedback' => $article->feedback()->where('user_id', $user->id)->first(),
            'linkLabels' => $this->linkLabels($article),
        ]);
    }

    public function create(Request $request): View {
        Gate::authorize('create', KnowledgeArticle::class);

        // Optionale Vorbelegung „Artikel aus diesem Auftrag erstellen":
        // Problem = Auftragstitel/-beschreibung, nach dem Speichern wird
        // automatisch verknüpft (link_kind/link_id als Hidden-Felder).
        [$linkKind, $linkId, $prefill] = $this->resolveOptionalSourceFromRequest($request);

        return view('knowledge._form_dialog', [
            'article' => null,
            // Sammlung beim Anlegen (MVP-814, ersetzt die Freitext-Kategorie).
            'collectionOptions' => Gate::allows('create', ContentCollection::class) ? $this->collections->tree($this->currentUser()) : [],
            'linkKind' => $linkKind,
            'linkId' => $linkId,
            'prefill' => $prefill,
        ]);
    }

    public function store(Request $request): RedirectResponse {
        Gate::authorize('create', KnowledgeArticle::class);

        $data = $this->validateArticle($request, includeLink: true);

        /** @var User $creator */
        $creator = Auth::user();
        $article = $this->service->create($creator, $data);

        if (filled($data['link_kind'] ?? null)) {
            $subject = $this->findLinkable((string) $data['link_kind'], (string) ($data['link_id'] ?? ''));
            $this->service->linkTo($article, $subject, $creator);
        }

        if (filled($data['collection'] ?? null) && Gate::allows('create', ContentCollection::class)) {
            $collection = ContentCollection::query()->visibleTo($creator)->whereNull('archived_at')
                ->find(Sqid::decodeOrNumeric(ContentCollection::class, (string) $data['collection']));
            if ($collection !== null && Gate::allows('update', $collection)) {
                $this->collections->addItem($collection, $creator, $article);
            }
        }

        $this->storeUploads($article, $request);

        return redirect()
            ->back()
            ->with('success', __('knowledge.flash.created'));
    }

    public function edit(KnowledgeArticle $article): View {
        Gate::authorize('update', $article);

        return view('knowledge._form_dialog', [
            'article' => $article->load('tags'),
            'collectionOptions' => [],
            'linkKind' => null,
            'linkId' => null,
            'prefill' => [],
        ]);
    }

    public function update(Request $request, KnowledgeArticle $article): RedirectResponse {
        Gate::authorize('update', $article);

        $data = $this->validateArticle($request, includeLink: false);

        /** @var User $actor */
        $actor = Auth::user();
        $this->service->update($article, $actor, $data);

        $this->storeUploads($article, $request);

        return redirect()
            ->back()
            ->with('success', __('knowledge.flash.updated'));
    }

    public function publish(KnowledgeArticle $article): RedirectResponse {
        Gate::authorize('publish', $article);

        /** @var User $actor */
        $actor = Auth::user();
        $this->service->publish($article, $actor);

        return redirect()
            ->back()
            ->with('success', __('knowledge.flash.published'));
    }

    public function archive(KnowledgeArticle $article): RedirectResponse {
        Gate::authorize('archive', $article);

        /** @var User $actor */
        $actor = Auth::user();
        $this->service->archive($article, $actor);

        return redirect()
            ->back()
            ->with('success', __('knowledge.flash.archived'));
    }

    public function destroy(KnowledgeArticle $article): RedirectResponse {
        Gate::authorize('delete', $article);

        /** @var User $actor */
        $actor = Auth::user();
        $this->service->delete($article, $actor);

        return redirect()
            ->route('knowledge.index')
            ->with('success', __('knowledge.flash.deleted'));
    }

    /** Feedback „Hat geholfen / Hat nicht geholfen" (eine Wertung je User). */
    public function feedback(Request $request, KnowledgeArticle $article): RedirectResponse {
        Gate::authorize('feedback', $article);

        $data = $request->validate([
            'value' => ['required', 'string', 'in:helpful,notHelpful'],
        ]);

        /** @var User $user */
        $user = Auth::user();
        $this->service->feedback($article, $user, $data['value'] === 'helpful');

        return redirect()
            ->back()
            ->with('success', __('knowledge.flash.feedback_saved'));
    }

    /** Verknüpft den Artikel mit Auftrag/Asset/Kunde/Protokoll. */
    public function storeLink(Request $request, KnowledgeArticle $article): RedirectResponse {
        Gate::authorize('link', $article);

        $data = $request->validate([
            'subject_kind' => ['required', 'string', 'in:' . implode(',', array_keys(self::LINKABLE_MAP))],
            'subject_id' => ['required', 'string'],
        ]);

        $subject = $this->findLinkable((string) $data['subject_kind'], (string) $data['subject_id']);

        /** @var User $actor */
        $actor = Auth::user();
        $this->service->linkTo($article, $subject, $actor);

        return redirect()
            ->back()
            ->with('success', __('knowledge.flash.linked'));
    }

    public function destroyLink(KnowledgeArticle $article, ContentReference $link): RedirectResponse {
        Gate::authorize('link', $article);

        if ($link->source_type !== $article->getMorphClass() || (int) $link->source_id !== (int) $article->id || $link->kind !== ContentReference::KIND_LINKED) {
            abort(404);
        }

        /** @var User $actor */
        $actor = Auth::user();
        $this->service->unlink($link, $actor);

        return redirect()
            ->back()
            ->with('success', __('knowledge.flash.unlinked'));
    }

    /**
     * @return array<string, mixed>
     */
    private function validateArticle(Request $request, bool $includeLink): array {
        $rules = [
            'title' => ['required', 'string', 'min:3', 'max:180'],
            'problem' => ['required', 'string', 'max:10000'],
            'solution' => ['required', 'string', 'max:20000'],
            'collection' => ['nullable', 'string', 'max:64'],
            'tags' => ['nullable', 'string', 'max:500'],
            // Optionale Anhänge (Bilder/Dokumente) direkt aus dem Dialog —
            // der Dialog wird als FormData (multipart) per AJAX gesendet.
            'attachments' => ['nullable', 'array', 'max:10'],
            'attachments.*' => FileAttacher::rule(),
        ];

        if ($includeLink) {
            $rules['link_kind'] = ['nullable', 'string', 'in:' . implode(',', array_keys(self::LINKABLE_MAP))];
            $rules['link_id'] = ['nullable', 'string', 'required_with:link_kind'];
        }

        return $request->validate($rules);
    }

    /**
     * Hängt optionale, aus dem Dialog mitgesendete Dateien als Anhänge an den
     * Artikel an (Typ/Größe sind über {@see FileAttacher::rule()} in
     * validateArticle() bereits geprüft). Der Dialog wird als FormData
     * (multipart) gesendet, daher funktioniert der Upload auch beim Anlegen.
     */
    private function storeUploads(KnowledgeArticle $article, Request $request): void {
        $files = $request->file('attachments');
        if (! is_array($files)) {
            return;
        }

        $attacher = new FileAttacher();
        $userId = Auth::id() !== null ? (int) Auth::id() : null;

        foreach ($files as $file) {
            if ($file->isValid()) {
                $attacher->store($article, $file, $userId);
            }
        }
    }

    /**
     * Löst kind+Sqid in das Bezugs-Model auf (404 bei unbekanntem Typ,
     * fremder Organisation — globaler Scope — oder kaputter Id).
     */
    private function findLinkable(string $kind, string $rawId): Model {
        $class = self::LINKABLE_MAP[$kind] ?? null;
        if ($class === null) {
            abort(404);
        }

        $id = Sqid::decodeOrNumeric($class, $rawId);
        if ($id === null || $id < 1) {
            abort(404);
        }

        /** @var Model|null $subject */
        $subject = $class::query()->find($id);
        if ($subject === null) {
            abort(404);
        }

        return $subject;
    }

    /**
     * @return array{0: ?string, 1: ?string, 2: array<string, string>}
     */
    private function resolveOptionalSourceFromRequest(Request $request): array {
        $kind = (string) $request->query('source_kind', '');
        if ($kind === '') {
            return [null, null, []];
        }
        if (! array_key_exists($kind, self::LINKABLE_MAP)) {
            abort(404);
        }

        $subject = $this->findLinkable($kind, (string) $request->query('source_id', ''));

        $prefill = [
            'title' => (string) ($subject->getAttribute('title') ?? $subject->getAttribute('name') ?? ''),
            'problem' => trim(implode("\n\n", array_filter([
                (string) ($subject->getAttribute('title') ?? $subject->getAttribute('name') ?? ''),
                (string) ($subject->getAttribute('content') ?? $subject->getAttribute('description') ?? ''),
            ], static fn(string $part): bool => trim($part) !== ''))),
        ];

        return [$kind, Sqid::encode($subject::class, (int) $subject->getKey()), $prefill];
    }

    /**
     * Anzeige-Label je Verknüpfung (Typ-Kürzel für die Artikelseite).
     *
     * @return array<int, string>
     */
    private function linkLabels(KnowledgeArticle $article): array {
        $labels = [];
        foreach ($article->links as $link) {
            // Probleme verknüpft der Helpdesk (Known Error); sie standen bis MVP-811 fälschlich als „Auftrag“ da.
            $kind = array_search($link->target_type, self::LINKABLE_MAP, true)
                ?: ($link->target_type === (new \App\Models\ServiceTicket\Problem)->getMorphClass() ? 'problem' : 'diary');
            $labels[$link->id] = (string) __('knowledge.link_kind.' . $kind);
        }

        return $labels;
    }

    /**
     * Sichtbare Sammlungen je Artikel der Seite — eine Abfrage statt einer je Zeile.
     *
     * @param  array<int, int|string>  $articleIds
     * @return array<int, list<ContentCollection>>
     */
    private function collectionsByArticle(User $user, array $articleIds): array {
        if ($articleIds === []) {
            return [];
        }

        $map = [];
        ContentCollectionItem::query()
            ->where('collectable_type', (new KnowledgeArticle)->getMorphClass())
            ->whereIn('collectable_id', $articleIds)
            ->whereHas('collection', static fn ($q) => $q->visibleTo($user)->whereNull('archived_at'))
            ->with('collection:id,title,visibility,created_by')
            ->get()
            ->each(static function (ContentCollectionItem $entry) use (&$map): void {
                if ($entry->collection !== null) {
                    $map[(int) $entry->collectable_id][] = $entry->collection;
                }
            });

        return $map;
    }

    private function currentUser(): User {
        /** @var User $user */
        $user = Auth::user();

        return $user;
    }
}
