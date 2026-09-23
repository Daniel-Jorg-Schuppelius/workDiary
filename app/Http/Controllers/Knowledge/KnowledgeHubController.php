<?php
/*
 * Created on   : Thu Sep 17 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : KnowledgeHubController.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Http\Controllers;

use App\Models\{ContentCollection, Customer, Tag, User};
use App\Services\Collections\{CollectableTypes, ContentCollectionService, KnowledgeHubService};
use App\Support\Sqid;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\{Auth, Gate};
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * Einstieg „Wissen“ (MVP-813, Feature 155): Sammlungsbaum links, Inhalte als
 * Liste oder Kacheln rechts, Filter oben. Die bisherigen Einstiege (Notizen,
 * Wissensarchiv, Ideenlandkarten, Sammlungen) bleiben unverändert.
 */
class KnowledgeHubController extends Controller {
    private const PER_PAGE = 24;

    public function __construct(
        private readonly KnowledgeHubService $hub,
        private readonly CollectableTypes $types,
        private readonly ContentCollectionService $collections,
    ) {}

    public function index(Request $request): View {
        /** @var User $user */
        $user = Auth::user();
        $available = $this->types->availableKeys($user);
        abort_if($available === [], 403);

        $data = $request->validate([
            'q' => ['nullable', 'string', 'max:120'],
            'type' => ['nullable', Rule::in($available)],
            'tag' => ['nullable', 'string', 'max:64'],
            'collection' => ['nullable', 'string', 'max:64'],
            'customer' => ['nullable', 'string', 'max:64'],
            'view' => ['nullable', Rule::in(['list', 'tiles'])],
            'page' => ['nullable', 'integer', 'min:1'],
        ]);

        $mayViewCollections = Gate::allows('viewAny', ContentCollection::class);
        $selected = null;
        if ($mayViewCollections && filled($data['collection'] ?? null)) {
            $selected = ContentCollection::query()->visibleTo($user)
                ->find(Sqid::decodeOrNumeric(ContentCollection::class, (string) $data['collection']));
            abort_if($selected === null, 404);
        }
        $tagId = filled($data['tag'] ?? null) ? Sqid::decodeOrNumeric(Tag::class, (string) $data['tag']) : null;
        // Kundenfilter über die Trägerkette (MVP-818); ein unbekannter Kunde
        // filtert auf nichts statt auf alles.
        $customerId = filled($data['customer'] ?? null)
            ? Sqid::decode(Customer::class, (string) $data['customer'])
            : null;

        $rows = $this->hub->items(
            $user,
            trim((string) ($data['q'] ?? '')),
            isset($data['type']) ? (string) $data['type'] : null,
            $tagId,
            $selected !== null ? (int) $selected->id : null,
            $customerId,
        );
        $facets = $this->hub->tagFacets($rows);

        // Den Namen des aktiven Schlagworts nur aus sichtbaren Inhalten nennen.
        $activeTagName = null;
        foreach ($facets as $facet) {
            if ($facet['id'] === $tagId) {
                $activeTagName = $facet['name'];
            }
        }

        $page = max(1, (int) ($data['page'] ?? 1));
        $items = new LengthAwarePaginator(
            array_slice($rows, ($page - 1) * self::PER_PAGE, self::PER_PAGE),
            count($rows),
            self::PER_PAGE,
            $page,
            ['path' => $request->url(), 'pageName' => 'page'],
        );
        $items->withQueryString();

        return view('knowledge-hub.index', [
            'items' => $items,
            'tree' => $mayViewCollections ? $this->collections->tree($user) : [],
            'selected' => $selected,
            'types' => array_map(fn (string $key): array => ['key' => $key, 'label' => $this->types->label($key)], $available),
            'filters' => [
                'q' => trim((string) ($data['q'] ?? '')),
                'type' => $data['type'] ?? null,
                'tag' => $tagId !== null ? Sqid::encode(Tag::class, $tagId) : null,
                'collection' => $selected?->sqid,
                'customer' => $customerId !== null ? Sqid::encode(Customer::class, $customerId) : null,
                'view' => ($data['view'] ?? 'list') === 'tiles' ? 'tiles' : 'list',
            ],
            'tagFacets' => $facets,
            'customers' => Customer::query()->orderBy('name')->get(['id', 'name']),
            'activeTagId' => $tagId,
            'activeTagName' => $activeTagName,
            'mayCollect' => Gate::allows('create', ContentCollection::class),
            // Übernahme aus Obsidian/OneNote (MVP-815): nur Administratoren mit Sammlungsrecht.
            'mayImport' => $user->isAdmin() && Gate::allows('create', ContentCollection::class),
            'oneNoteReady' => $user->isAdmin() && $this->oneNoteReady((int) $user->organization_id),
        ]);
    }

    private function oneNoteReady(int $organizationId): bool {
        if (! \App\Plugins\Msgraph\MsgraphConfig::oneNoteImportEnabled($organizationId)) {
            return false;
        }
        $connection = \App\Models\MsgraphOneNoteConnection::query()->where('organization_id', $organizationId)->first();

        return $connection !== null && $connection->isActive();
    }
}
