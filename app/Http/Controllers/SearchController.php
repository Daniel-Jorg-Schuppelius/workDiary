<?php
/*
 * Created on   : Sat Jul 18 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : SearchController.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Enums\Search\SearchSourceType;
use App\Models\{ContentCollection, Customer, ForeignCustomer, Project, Tag, User};
use App\Services\Ai\Suggestions\{SearchAnswerSuggestionService, SuggestionViewData};
use App\Services\Collections\{CollectableTypes, ContentCollectionService};
use App\Services\Search\{ActivitySearchCriteria, ActivitySearchService, ActivitySearchVisibility, GlobalSearchService};
use App\Support\Sqid;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\{Auth, Gate};
use Illuminate\View\View;

/**
 * Suchseite `/suche` (Feature 153; vorher Vollergebnisseite aus Vollaudit
 * 2026-07, M8): oben die Tätigkeitsrecherche mit Übersicht je Kunde/Endkunde
 * und seitenweiser Trefferliste, darunter die Stammdaten-Gruppen der globalen
 * Suche. `?domain=` fokussiert eine Stammdaten-Gruppe (bis 200 Treffer).
 */
class SearchController extends Controller {
    private const LIMIT_ENTITIES = 10;

    private const LIMIT_FOCUSED = 200;

    public function index(
        Request $request,
        GlobalSearchService $search,
        ActivitySearchService $activities,
        ActivitySearchVisibility $visibility,
        SuggestionViewData $aiView,
        ContentCollectionService $collections,
        CollectableTypes $collectables,
    ): View {
        /** @var User $user */
        $user = Auth::user();

        $request->validate([
            'domain' => ['nullable', 'string', 'max:32'],
            'focus' => ['nullable', 'boolean'],
            'page' => ['nullable', 'integer', 'min:1'],
        ]);
        $criteria = ActivitySearchCriteria::fromRequest($request, $user);

        $domains = $search->domains();
        $domain = (string) $request->query('domain', '');
        $domain = $domain !== '' && $domain !== GlobalSearchService::ACTIVITIES && array_key_exists($domain, $domains) ? $domain : null;

        $result = $domain === null ? $activities->search($user, $criteria, null, max(1, (int) $request->query('page', 1))) : null;
        $result?->hits->withQueryString();

        // Stammdaten kennen weder Endkunde noch Projekt — mit diesen Filtern
        // entfallen sie, statt still ungefiltert zu erscheinen.
        $groups = mb_strlen($criteria->query) >= 2 && $criteria->foreignCustomerId === null && $criteria->projectId === null
            ? $search->groups($user, $criteria->query, [
                'domain' => $domain,
                'from' => $criteria->from,
                'to' => $criteria->to,
                'person' => $criteria->personId,
                'customer' => $criteria->customerId,
            ], $domain !== null ? self::LIMIT_FOCUSED : self::LIMIT_ENTITIES, withActivities: false)
            : [];

        $mayPickPersons = $user->isAdmin() || Gate::allows('manage-members');

        $activeTagName = null;
        foreach ($result->tagFacets ?? [] as $facet) {
            if ($facet['id'] === $criteria->tagId) {
                $activeTagName = $facet['name'];
            }
        }
        $aiUsable = $aiView->capabilityUsable(SearchAnswerSuggestionService::CAPABILITY);

        return view('search.index', [
            'criteria' => $criteria,
            'result' => $result,
            'groups' => $groups,
            'domains' => $domains,
            'selectedDomain' => $domain,
            'types' => $visibility->types($user),
            'focus' => $request->boolean('focus'),
            'selectablePersons' => $mayPickPersons
                ? User::query()->where('organization_id', $user->organization_id)->orderBy('name')->get(['id', 'name'])
                : null,
            'customers' => Customer::query()->orderBy('name')->limit(500)->get(['id', 'name']),
            'foreignCustomers' => ForeignCustomer::query()
                ->when($criteria->customerId !== null, static fn($q) => $q->where('customer_id', $criteria->customerId))
                ->orderBy('name')
                ->limit(500)
                ->get(['id', 'name', 'customer_id']),
            'project' => $criteria->projectId !== null ? Project::query()->find($criteria->projectId, ['id', 'name']) : null,
            // Facetten (MVP-812): Sammlungen nur mit Leserecht. Den Namen des aktiven
            // Schlagworts liefern nur sichtbare Treffer — eine Sqid in der URL verrät
            // so kein Stichwort vertraulicher Inhalte.
            'collectionOptions' => Gate::allows('viewAny', ContentCollection::class) ? $collections->tree($user) : [],
            // Sammeln aus der Trefferliste (MVP-813): Quelle → Sammlungstyp.
            'mayCollect' => Gate::allows('create', ContentCollection::class),
            'collectableSources' => $this->collectableSources($collectables),
            'activeTag' => $criteria->tagId !== null ? [
                'sqid' => Sqid::encode(Tag::class, $criteria->tagId),
                'name' => $activeTagName,
            ] : null,
            'aiUsable' => $aiUsable,
            'aiAnswer' => $aiUsable
                ? $aiView->openSuggestionsFor($user->getMorphClass(), collect([$user]), SearchAnswerSuggestionService::CAPABILITY)->get($user->id)
                : null,
        ]);
    }

    /** @return array<string, string> Quellwert → Sammlungstyp */
    private function collectableSources(CollectableTypes $collectables): array {
        $map = [];
        foreach (SearchSourceType::cases() as $type) {
            $key = $collectables->keyFor(new ($type->modelClass())());
            if ($key !== null) {
                $map[$type->value] = $key;
            }
        }

        return $map;
    }
}
