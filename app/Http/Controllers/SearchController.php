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

use App\Models\{Customer, User};
use App\Services\Search\GlobalSearchService;
use App\Support\Sqid;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\{Auth, Gate};
use Illuminate\View\View;

/**
 * Vollergebnisseite der globalen Suche (globale-suche.md AK 2–3; Vollaudit
 * 2026-07, M8): dieselben rechte- und org-sicheren Gruppen-Queries wie die
 * Command-Palette ({@see GlobalSearchService}), aber mit Filtern (Domäne,
 * Zeitraum, Person, Kunde) und höherem Limit — ohne Domänen-Filter bis zu
 * 25 Treffer je Gruppe, mit Fokus auf EINE Domäne bis zu 200.
 */
class SearchController extends Controller {
    private const LIMIT_ALL = 25;

    private const LIMIT_FOCUSED = 200;

    public function index(Request $request, GlobalSearchService $search): View {
        $data = $request->validate([
            'q' => ['nullable', 'string', 'max:120'],
            'domain' => ['nullable', 'string', 'max:32'],
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date'],
            'person' => ['nullable', 'string', 'max:64'],
            'customer' => ['nullable', 'string', 'max:64'],
        ]);

        /** @var User $user */
        $user = Auth::user();

        $term = trim((string) ($data['q'] ?? ''));
        $domains = $search->domains();
        $domain = isset($data['domain']) && array_key_exists((string) $data['domain'], $domains)
            ? (string) $data['domain']
            : null;

        // Personen-Filter: Fremdauswahl nur für Admin/Org-Manager — alle
        // anderen dürfen ausschließlich auf sich selbst filtern.
        $personId = null;
        if (($data['person'] ?? '') !== '') {
            $personId = Sqid::decodeOrNumeric(User::class, (string) $data['person']);
            if ($personId !== null && $personId !== (int) $user->id
                && ! ($user->isAdmin() || Gate::allows('manage-members'))) {
                $personId = (int) $user->id;
            }
        }

        $customerId = ($data['customer'] ?? '') !== ''
            ? Sqid::decodeOrNumeric(Customer::class, (string) $data['customer'])
            : null;

        $filters = [
            'domain' => $domain,
            'from' => $data['from'] ?? null,
            'to' => $data['to'] ?? null,
            'person' => $personId,
            'customer' => $customerId,
        ];

        $groups = mb_strlen($term) >= 2
            ? $search->groups($user, $term, $filters, $domain !== null ? self::LIMIT_FOCUSED : self::LIMIT_ALL)
            : [];

        return view('search.index', [
            'q' => $term,
            'groups' => $groups,
            'domains' => $domains,
            'selectedDomain' => $domain,
            'from' => $data['from'] ?? '',
            'to' => $data['to'] ?? '',
            'selectablePersons' => ($user->isAdmin() || Gate::allows('manage-members'))
                ? User::query()->when($user->organization_id !== null, fn($q) => $q->where('organization_id', $user->organization_id))->orderBy('name')->get(['id', 'name'])
                : null,
            'selectedPersonId' => $personId,
            'customers' => Customer::query()->orderBy('name')->limit(500)->get(['id', 'name']),
            'selectedCustomerId' => $customerId,
            'total' => array_sum(array_map(static fn(array $g): int => count($g['items']), $groups)),
        ]);
    }
}
