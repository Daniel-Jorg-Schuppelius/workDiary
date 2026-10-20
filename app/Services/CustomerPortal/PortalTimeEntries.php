<?php
/*
 * Created on   : Tue Oct 20 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : PortalTimeEntries.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\CustomerPortal;

use App\Models\Customer\Customer;
use App\Models\Time\TimeEntry;
use App\Support\Query\DateRange;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

/**
 * Grundmenge der Portalzeiten (MVP-1237): Zeiten der Kundenprojekte im
 * freigegebenen Scope innerhalb der Jahresgrenze. Die EINZIGE Stelle, die
 * entscheidet, welche Zeiten ein Kunde sieht — Liste, Dashboard-Zähler,
 * Summen und Auswertungen bauen darauf auf, damit keine Zahl von der
 * Liste abweicht. Detailstufe und Freigabe prüft der Aufrufer über
 * {@see PortalVisibility}.
 */
final class PortalTimeEntries {
    public function __construct(private readonly PortalVisibility $visibility) {}

    /**
     * @return Builder<TimeEntry>
     */
    public function baseQuery(Customer $customer): Builder {
        $query = TimeEntry::query()
            ->whereHas('project', fn (Builder $q) => $q->where('customer_id', $customer->id))
            ->when(
                $this->visibility->timeScope($customer) === PortalVisibility::TIME_SCOPE_PUBLISHED,
                fn (Builder $q) => $q->whereNotNull('time_entries.customer_visible_at'),
            );

        $oldest = $this->oldestVisibleDate($customer);
        if ($oldest !== null) {
            $query->where('time_entries.date', '>=', DateRange::day($oldest));
        }

        return $query;
    }

    /**
     * @return Builder<TimeEntry> Grundmenge eines Kalenderjahres.
     */
    public function yearQuery(Customer $customer, int $year): Builder {
        return $this->baseQuery($customer)
            ->whereBetween('time_entries.date', DateRange::days(sprintf('%04d-01-01', $year), sprintf('%04d-12-31', $year)));
    }

    /** Erster sichtbarer Tag (1. Januar des ältesten Jahres) oder null = unbegrenzt. */
    public function oldestVisibleDate(Customer $customer): ?CarbonImmutable {
        $years = $this->visibility->timeYears($customer);
        if ($years === PortalVisibility::TIME_YEARS_UNLIMITED) {
            return null;
        }

        return CarbonImmutable::now()->startOfYear()->subYears($years - 1);
    }

    /**
     * Jahre mit Einträgen innerhalb der Grenze, neueste zuerst. SUBSTR statt
     * YEAR(): `date` liegt als 'Y-m-d …' vor und läuft so auf MariaDB wie SQLite.
     *
     * @return list<int>
     */
    public function years(Customer $customer): array {
        $expression = 'SUBSTR(time_entries.date, 1, 4)';

        return array_values($this->baseQuery($customer)
            ->toBase()
            ->selectRaw($expression . ' as year')
            ->groupBy(DB::raw($expression))
            ->orderByDesc(DB::raw($expression))
            ->pluck('year')
            ->map(static fn (mixed $year): int => (int) $year)
            ->all());
    }
}
