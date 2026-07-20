<?php
/*
 * Created on   : Sun Nov 23 2025
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : GlobalSearchController.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Http\Controllers;

use App\Models\User;
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
 * (Vollaudit 2026-07, M8) im {@see GlobalSearchService}.
 */
class GlobalSearchController extends Controller {
    private const PER_TYPE_LIMIT = 5;

    public function __invoke(Request $request, GlobalSearchService $search): JsonResponse {
        $data = $request->validate([
            'q' => ['nullable', 'string', 'max:120'],
        ]);

        $term = trim((string) ($data['q'] ?? ''));
        if (mb_strlen($term) < 2) {
            return response()->json(['groups' => []]);
        }

        /** @var User $user */
        $user = Auth::user();

        return response()->json([
            'groups' => $search->groups($user, $term, [], self::PER_TYPE_LIMIT),
            'q' => $term,
            // Vollaudit 2026-07 (M8): „alle Treffer →"-Link der Palette.
            'allUrl' => route('search.index', ['q' => $term]),
        ]);
    }
}
