<?php
/*
 * Created on   : Mon Nov 09 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ListsLegacySupportOrders.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Legacy\Http\Concerns;

use App\Legacy\Models\{LegacySupportCustomer, LegacySupportOrder};
use App\Legacy\Support\LegacyConnectivity;
use App\Support\{SortableQuery, Sqid};
use Illuminate\Database\Eloquent\{Builder, Collection};
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/** Filter und Sortierung der Auftrags- und Archivliste des Support-Portals. */
trait ListsLegacySupportOrders {
    /** @var array<string, string> */
    private const array SUPPORT_ORDER_SORTS = [
        'nr' => 'id',
        'datum' => 'datum',
        'kunde' => 'kunde',
        'telefon' => 'telefon',
        'termin' => 'termin',
        'status' => 'status',
        'objekt' => 'objekt',
    ];

    /**
     * Jahr (Vorgabe: jüngstes Jahr mit Aufträgen, `all` für alle), Kunde und
     * Suchtext auf die Abfrage legen.
     *
     * @template TOrder of LegacySupportOrder
     *
     * @param  Builder<TOrder>  $query
     * @return array{years: list<int>, year: int|null, customerId: int|null, search: string, filters: array<string, string>}
     */
    private function applySupportOrderFilters(Request $request, Builder $query): array {
        $years = $this->supportOrderYears($query->getModel()->getTable());

        $rawYear = (string) $request->query('year', '');
        $year = match (true) {
            $rawYear === 'all' => null,
            ctype_digit($rawYear) && in_array((int) $rawYear, $years, true) => (int) $rawYear,
            default => $years[0],
        };
        if ($year !== null) {
            $query->whereBetween('datum', [$year . '-01-01 00:00:00', $year . '-12-31 23:59:59']);
        }

        $customerId = Sqid::decodeOrNumeric(LegacySupportCustomer::class, $request->query('customer'));
        if ($customerId !== null) {
            $query->where('kunde', $customerId);
        }

        $search = trim((string) $request->query('q', ''));
        if ($search !== '') {
            $query->where(fn(Builder $inner) => $inner->whereLikeEscaped('objekt', $search)
                ->orWhereLikeEscaped('auftrag', $search)
                ->orWhereLikeEscaped('telefon', $search)
                ->orWhereLikeEscaped('kname', $search));
        }

        return [
            'years' => $years,
            'year' => $year,
            'customerId' => $customerId,
            'search' => $search,
            'filters' => array_filter([
                'year' => $year === null ? 'all' : (string) $year,
                'customer' => $customerId !== null ? Sqid::encode(LegacySupportCustomer::class, $customerId) : '',
                'q' => $search,
            ], fn(string $value): bool => $value !== ''),
        ];
    }

    /**
     * @template TOrder of LegacySupportOrder
     *
     * @param  Builder<TOrder>  $query
     * @return array{0: string, 1: 'asc'|'desc'}
     */
    private function applySupportOrderSort(Request $request, Builder $query): array {
        [$key, $dir] = SortableQuery::resolve($request, self::SUPPORT_ORDER_SORTS, 'nr', 'desc');

        if ($key === 'kunde') {
            $query->orderBy(
                LegacySupportCustomer::query()->select('kunde')->whereColumn('kp_user.id', $query->getModel()->getTable() . '.kunde'),
                $dir
            );
        } else {
            $query->orderBy(self::SUPPORT_ORDER_SORTS[$key], $dir);
        }

        if ($key !== 'nr') {
            $query->orderByDesc('id');
        }

        return [$key, $dir];
    }

    /** @return Collection<int, LegacySupportCustomer> */
    private function supportCustomersForSelect(): Collection {
        return LegacySupportCustomer::query()->customers()->orderBy('kunde')->get(['id', 'kunde']);
    }

    /**
     * Jahre mit Aufträgen, das jüngste zuerst; ohne Aufträge das laufende Jahr.
     *
     * @return list<int>
     */
    private function supportOrderYears(string $table): array {
        $connection = DB::connection(LegacyConnectivity::CONNECTION);
        $expression = match ($connection->getDriverName()) {
            'sqlite' => "CAST(strftime('%Y', datum) AS INTEGER)",
            'pgsql' => 'CAST(EXTRACT(YEAR FROM datum) AS INTEGER)',
            default => 'YEAR(datum)',
        };

        $years = $connection->table($table)
            ->selectRaw($expression . ' AS order_year')
            ->distinct()
            ->pluck('order_year')
            ->map(fn(mixed $year): int => (int) $year)
            ->filter(fn(int $year): bool => $year > 0)
            ->sortDesc()
            ->all();

        return $years === [] ? [(int) now()->year] : array_values($years);
    }
}
