<?php
/*
 * Created on   : Thu Sep 17 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ContentCollectionController.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\ResolvesCurrentOrganization;
use App\Models\{ContentCollection, ContentCollectionItem, User};
use App\Services\Collections\{CollectableTypes, ContentCollectionService};
use App\Support\Sqid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\{RedirectResponse, Request};
use Illuminate\Support\Facades\{Auth, Gate};
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * Sammlungen (MVP-809, Feature 155): Baum und Inhalte an einer Stelle,
 * Aufnahme aus den Detailseiten der Inhalte.
 */
class ContentCollectionController extends Controller {
    use ResolvesCurrentOrganization;

    public function __construct(
        private readonly ContentCollectionService $service,
        private readonly CollectableTypes $types,
    ) {}

    public function index(Request $request): View {
        Gate::authorize('viewAny', ContentCollection::class);
        $user = $this->user();

        $withArchived = $request->boolean('archived');
        $tree = $this->service->tree($user, $withArchived);

        $selected = null;
        $selectedId = Sqid::decodeOrNumeric(ContentCollection::class, (string) $request->query('collection', ''));
        if ($selectedId !== null) {
            $selected = ContentCollection::query()->visibleTo($user)->find($selectedId);
            abort_if($selected === null, 404);
        } elseif ($tree !== []) {
            $selected = $tree[0]['collection'];
        }

        return view('collections.index', [
            'tree' => $tree,
            'selected' => $selected,
            'items' => $selected !== null ? $this->service->visibleItems($selected, $user) : [],
            'withArchived' => $withArchived,
            'canManage' => Gate::allows('create', ContentCollection::class),
        ]);
    }

    public function create(Request $request): View {
        Gate::authorize('create', ContentCollection::class);

        return view('collections._form_dialog', [
            'collection' => null,
            'parentOptions' => $this->service->tree($this->user()),
            'parentSqid' => (string) $request->query('parent', ''),
        ]);
    }

    public function store(Request $request): RedirectResponse {
        Gate::authorize('create', ContentCollection::class);

        $collection = $this->service->create($this->currentOrganization(), $this->user(), $this->validated($request));

        return redirect()
            ->route('collections.index', ['collection' => $collection->sqid])
            ->with('success', __('collections.flash.created'));
    }

    public function edit(ContentCollection $collection): View {
        $this->authorizeCollection('update', $collection);

        return view('collections._form_dialog', [
            'collection' => $collection,
            'parentOptions' => $this->service->tree($this->user()),
            'parentSqid' => (string) Sqid::encodeOrNull(ContentCollection::class, $collection->parent_id),
        ]);
    }

    public function update(Request $request, ContentCollection $collection): RedirectResponse {
        $this->authorizeCollection('update', $collection);

        $this->service->update($collection, $this->user(), $this->validated($request));

        return redirect()
            ->route('collections.index', ['collection' => $collection->sqid])
            ->with('success', __('collections.flash.updated'));
    }

    public function archive(ContentCollection $collection): RedirectResponse {
        $this->authorizeCollection('update', $collection);

        $this->service->archive($collection);

        return redirect()->route('collections.index')->with('success', __('collections.flash.archived'));
    }

    public function restore(ContentCollection $collection): RedirectResponse {
        $this->authorizeCollection('update', $collection);

        $this->service->restore($collection);

        return redirect()
            ->route('collections.index', ['collection' => $collection->sqid])
            ->with('success', __('collections.flash.restored'));
    }

    /** Dialog „Zur Sammlung hinzufügen“ von der Detailseite eines Inhalts. */
    public function addDialog(Request $request): View {
        Gate::authorize('create', ContentCollection::class);
        $item = $this->resolveItem((string) $request->query('type', ''), (string) $request->query('item', ''));
        $user = $this->user();

        return view('collections._add_dialog', [
            'type' => (string) $request->query('type'),
            'itemSqid' => (string) $request->query('item'),
            'itemTitle' => $this->types->title($item),
            'options' => $this->service->tree($user),
            'memberOf' => $this->service->collectionsContaining($item, $user)->pluck('id')->map(static fn ($id): int => (int) $id)->all(),
        ]);
    }

    public function storeItem(Request $request): RedirectResponse {
        Gate::authorize('create', ContentCollection::class);

        $data = $request->validate([
            'collection' => ['required', 'string', 'max:64'],
            'type' => ['required', 'string', Rule::in($this->types->keys())],
            'item' => ['required', 'string', 'max:64'],
        ]);

        $collection = ContentCollection::query()
            ->visibleTo($this->user())
            ->whereNull('archived_at')
            ->find(Sqid::decodeOrNumeric(ContentCollection::class, $data['collection']));
        abort_if($collection === null, 404);
        $this->authorizeCollection('update', $collection);

        $item = $this->resolveItem($data['type'], $data['item']);
        $this->service->addItem($collection, $this->user(), $item);

        return redirect()->back()->with('success', __('collections.flash.item_added', ['collection' => $collection->title]));
    }

    /**
     * Mehrere Inhalte in eine Sammlung legen (MVP-813: Einstieg „Wissen“ und
     * Trefferliste). Nicht sichtbare Inhalte fallen still weg.
     */
    public function storeItems(Request $request): RedirectResponse {
        Gate::authorize('create', ContentCollection::class);

        $data = $request->validate([
            'collection' => ['required', 'string', 'max:64'],
            'items' => ['required', 'array', 'min:1', 'max:100'],
            'items.*' => ['string', 'max:96', 'regex:/^[a-z_]+:[A-Za-z0-9]+$/'],
        ]);

        $collection = ContentCollection::query()
            ->visibleTo($this->user())
            ->whereNull('archived_at')
            ->find(Sqid::decodeOrNumeric(ContentCollection::class, $data['collection']));
        abort_if($collection === null, 404);
        $this->authorizeCollection('update', $collection);

        $added = 0;
        foreach (array_unique($data['items']) as $value) {
            [$type, $sqid] = explode(':', (string) $value, 2);
            $class = $this->types->classFor($type);
            $id = $class !== null ? Sqid::decode($class, $sqid) : null;
            $visible = $id !== null ? $this->types->visible($type, $this->user(), [$id]) : [];
            if ($visible === []) {
                continue;
            }
            $this->service->addItem($collection, $this->user(), $visible[0]);
            $added++;
        }

        return redirect()->back()->with('success', trans_choice('collections.flash.items_added', $added, ['count' => $added, 'collection' => $collection->title]));
    }

    public function destroyItem(ContentCollection $collection, ContentCollectionItem $item): RedirectResponse {
        $this->authorizeCollection('update', $collection);
        abort_unless((int) $item->collection_id === (int) $collection->id, 404);

        $this->service->removeItem($item);

        return redirect()
            ->route('collections.index', ['collection' => $collection->sqid])
            ->with('success', __('collections.flash.item_removed'));
    }

    /**
     * @return array{title: string, description: string|null, parent_id: int|null, visibility: string}
     */
    private function validated(Request $request): array {
        $data = $request->validate([
            'title' => ['required', 'string', 'min:2', 'max:180'],
            'description' => ['nullable', 'string', 'max:2000'],
            'parent_id' => ['nullable', 'string', 'max:64'],
            'visibility' => ['required', Rule::in([ContentCollection::VISIBILITY_ORGANIZATION, ContentCollection::VISIBILITY_PRIVATE])],
        ]);

        return [
            'title' => (string) $data['title'],
            'description' => $data['description'] ?? null,
            // Unbekannte Kennung: der Service meldet sie als ungültige Obersammlung.
            'parent_id' => filled($data['parent_id'] ?? null) ? (Sqid::decodeOrNumeric(ContentCollection::class, (string) $data['parent_id']) ?? 0) : null,
            'visibility' => (string) $data['visibility'],
        ];
    }

    /** Inhalt aus Typ und Kennung — nur, wenn die Person ihn sehen darf. */
    private function resolveItem(string $type, string $sqid): Model {
        $class = $this->types->classFor($type);
        abort_if($class === null, 404);
        $id = Sqid::decode($class, $sqid);
        abort_if($id === null, 404);

        $visible = $this->types->visible($type, $this->user(), [$id]);
        abort_if($visible === [], 404);

        return $visible[0];
    }

    /** Private Sammlungen öffnet auch der Admin-Bypass nicht. */
    private function authorizeCollection(string $ability, ContentCollection $collection): void {
        abort_unless($collection->isVisibleTo($this->user()), 404);
        Gate::authorize($ability, $collection);
    }

    private function user(): User {
        /** @var User $user */
        $user = Auth::user();

        return $user;
    }
}
