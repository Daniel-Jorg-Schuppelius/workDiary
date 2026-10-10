<?php
/*
 * Created on   : Sun Nov 23 2025
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : GlobalSearchController.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Http\Controllers\Search;

use App\Http\Controllers\Controller;
use App\Models\Platform\User;
use App\Services\Help\{FunctionFinder, HelpCenterCatalog, HelpSearch};
use App\Services\Search\GlobalSearchService;
use Illuminate\Http\{JsonResponse, Request};
use Illuminate\Support\Facades\Auth;

/**
 * Liefert die Treffer für die globale Suche (Command-Palette / Spotlight).
 *
 * Pro Entität werden bis zu 5 Treffer zurückgegeben (Limit je Endpoint-Aufruf
 * insgesamt ≤ 30 Datensätze), gefiltert nach Organisation des angemeldeten
 * Benutzers. Datenschutz: Mitarbeiterliste ist Admins/Approver:innen vorbehalten.
 *
 * Die Gruppen-Queries teilen sich Command-Palette und Vollergebnisseite
 * (Vollaudit 2026-07, M8) im {@see GlobalSearchService}. Davor stehen die
 * Wegweiser der Palette (MVP-1082): Aktionen, Seiten und Hilfethemen.
 */
class GlobalSearchController extends Controller {
    private const PER_TYPE_LIMIT = 5;

    private const HELP_LIMIT = 3;

    public function __invoke(Request $request, GlobalSearchService $search, HelpSearch $help, FunctionFinder $finder, HelpCenterCatalog $catalog): JsonResponse {
        $data = $request->validate([
            'q' => ['nullable', 'string', 'max:120'],
        ]);

        $term = trim((string) ($data['q'] ?? ''));
        if (mb_strlen($term) < 2) {
            return response()->json(['groups' => []]);
        }

        /** @var User $user */
        $user = Auth::user();

        $query = $help->prepare($term);
        $guide = [
            [
                'key' => 'actions',
                'label' => (string) __('search.palette.group.actions'),
                'icon' => 'bolt',
                'items' => array_map(static fn(array $action): array => [
                    'id' => $action['key'],
                    'title' => $action['label'],
                    'subtitle' => $action['hint'],
                    'icon' => $action['icon'],
                    'action' => $action['key'],
                    'url' => null,
                ], $finder->actions($query)),
            ],
            [
                'key' => 'pages',
                'label' => (string) __('search.palette.group.pages'),
                'icon' => 'web',
                'items' => array_map(static fn(array $page): array => [
                    'id' => $page['route'],
                    'title' => $page['label'],
                    'subtitle' => $page['area'],
                    'icon' => $page['icon'],
                    'url' => $page['url'],
                    'modal' => $page['modal'],
                ], $finder->pages($query, self::PER_TYPE_LIMIT)),
            ],
            [
                'key' => 'help',
                'label' => (string) __('search.palette.group.help'),
                'icon' => 'menu_book',
                'items' => $help->search($query, $user)->topics->take(self::HELP_LIMIT)->map(static fn($row): array => [
                    'id' => $row->topic,
                    'title' => $row->title,
                    'subtitle' => (string) __('help.sections.' . $catalog->sectionKeyFor($row->topic) . '.title'),
                    'url' => route('help.center.show', ['topic' => $row->topic]),
                ])->values()->all(),
            ],
        ];

        return response()->json([
            'groups' => [
                ...array_values(array_filter($guide, static fn(array $group): bool => $group['items'] !== [])),
                ...$search->groups($user, $term, [], self::PER_TYPE_LIMIT),
            ],
            'q' => $term,
            // Vollaudit 2026-07 (M8): „alle Treffer →"-Link der Palette.
            'allUrl' => route('search.index', ['q' => $term]),
        ]);
    }
}
