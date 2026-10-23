<?php
/*
 * Created on   : Fri Oct 23 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : SearchDomainFilters.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Search\Dto;

use App\Support\Query\DateRange;
use Illuminate\Database\Eloquent\Builder;

/** Filter der globalen Suche, die jeder Bereich auf seine Spalten anwendet. */
final readonly class SearchDomainFilters {
    public function __construct(
        public ?string $from = null,
        public ?string $to = null,
        public ?int $person = null,
        public ?int $customer = null,
    ) {}

    /**
     * Zeitraum auf einer Spalte, ohne `whereDate` (indexfreundlich):
     * Zeitstempel halboffen, DATE-Spalten tageweise.
     *
     * @param  Builder<covariant \Illuminate\Database\Eloquent\Model>  $query
     */
    public function range(Builder $query, string $column, bool $dateColumn = false): void {
        if ($this->from !== null) {
            $query->where($column, '>=', $dateColumn ? DateRange::day($this->from) : DateRange::dayStart($this->from));
        }
        if ($this->to !== null) {
            $dateColumn
                ? $query->where($column, '<=', DateRange::day($this->to))
                : $query->where($column, '<', DateRange::dayAfter($this->to));
        }
    }
}
