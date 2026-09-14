<?php
/*
 * Created on   : Mon Sep 14 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ActivitySearchCriteria.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Search;

use App\Enums\Search\SearchSourceType;
use App\Models\{Customer, ForeignCustomer, Project, User};
use App\Support\Sqid;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

/**
 * Suchkriterien der Tätigkeitsrecherche (Feature 153). IDs kommen als Sqid
 * aus der URL; die Personenauswahl anderer ist Admins und Org-Managern
 * vorbehalten — alle anderen filtern höchstens auf sich selbst.
 */
final class ActivitySearchCriteria {
    public const SORT_RELEVANCE = 'relevance';

    public const SORT_DATE = 'date';

    /** @param  list<SearchSourceType>  $types */
    public function __construct(
        public readonly string $query = '',
        public readonly array $types = [],
        public readonly ?string $from = null,
        public readonly ?string $to = null,
        public readonly ?int $personId = null,
        public readonly ?int $customerId = null,
        public readonly ?int $foreignCustomerId = null,
        public readonly ?int $projectId = null,
        public readonly bool $similar = false,
        public readonly string $sort = self::SORT_RELEVANCE,
    ) {}

    public static function fromRequest(Request $request, User $user): self {
        $typeValues = array_map(static fn(SearchSourceType $t): string => $t->value, SearchSourceType::cases());
        $data = $request->validate([
            'q' => ['nullable', 'string', 'max:' . (int) config('search.max_query_length', 200)],
            // `type` kommt aus dem Auswahlfeld, `types[]` aus den Quellen-Links.
            'type' => ['nullable', Rule::in($typeValues)],
            'types' => ['nullable', 'array'],
            'types.*' => ['string', Rule::in($typeValues)],
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date'],
            'person' => ['nullable', 'string', 'max:64'],
            'customer' => ['nullable', 'string', 'max:64'],
            'foreign_customer' => ['nullable', 'string', 'max:64'],
            'project' => ['nullable', 'string', 'max:64'],
            'similar' => ['nullable', 'boolean'],
            'sort' => ['nullable', Rule::in([self::SORT_RELEVANCE, self::SORT_DATE])],
        ]);

        $personId = self::decode(User::class, $data['person'] ?? null);
        if ($personId !== null && $personId !== (int) $user->id && ! ($user->isAdmin() || Gate::forUser($user)->allows('manage-members'))) {
            $personId = (int) $user->id;
        }

        return new self(
            query: trim((string) ($data['q'] ?? '')),
            types: array_values(array_map(
                static fn(string $t): SearchSourceType => SearchSourceType::from($t),
                array_unique(array_filter([...(array) ($data['types'] ?? []), $data['type'] ?? null], static fn($t): bool => is_string($t) && $t !== '')),
            )),
            from: isset($data['from']) ? (string) $data['from'] : null,
            to: isset($data['to']) ? (string) $data['to'] : null,
            personId: $personId,
            customerId: self::decode(Customer::class, $data['customer'] ?? null),
            foreignCustomerId: self::decode(ForeignCustomer::class, $data['foreign_customer'] ?? null),
            projectId: self::decode(Project::class, $data['project'] ?? null),
            similar: (bool) ($data['similar'] ?? false),
            sort: (string) ($data['sort'] ?? self::SORT_RELEVANCE),
        );
    }

    /** Ohne Suchwort oder Bezugsfilter wird nicht gesucht (sonst: „alles"). */
    public function hasScope(): bool {
        return $this->query !== '' || $this->hasEntityFilter();
    }

    public function hasEntityFilter(): bool {
        return $this->personId !== null || $this->customerId !== null || $this->foreignCustomerId !== null || $this->projectId !== null;
    }

    /**
     * URL-Parameter (Sqids); `$overrides` ersetzt oder entfernt (null) einzelne.
     *
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    public function toParameters(array $overrides = []): array {
        $parameters = array_merge([
            'q' => $this->query,
            'types' => array_map(static fn(SearchSourceType $t): string => $t->value, $this->types),
            'from' => $this->from,
            'to' => $this->to,
            'person' => $this->personId !== null ? Sqid::encode(User::class, $this->personId) : null,
            'customer' => $this->customerId !== null ? Sqid::encode(Customer::class, $this->customerId) : null,
            'foreign_customer' => $this->foreignCustomerId !== null ? Sqid::encode(ForeignCustomer::class, $this->foreignCustomerId) : null,
            'project' => $this->projectId !== null ? Sqid::encode(Project::class, $this->projectId) : null,
            'similar' => $this->similar ? 1 : null,
            'sort' => $this->sort !== self::SORT_RELEVANCE ? $this->sort : null,
        ], $overrides);

        return array_filter($parameters, static fn(mixed $v): bool => $v !== null && $v !== '' && $v !== []);
    }

    /** @param  class-string  $class */
    private static function decode(string $class, mixed $value): ?int {
        return is_string($value) && $value !== '' ? Sqid::decodeOrNumeric($class, $value) : null;
    }
}
