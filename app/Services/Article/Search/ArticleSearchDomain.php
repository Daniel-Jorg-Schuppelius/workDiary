<?php
/*
 * Created on   : Fri Oct 23 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ArticleSearchDomain.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Article\Search;

use App\Models\Article\Article;
use App\Models\Platform\User;
use App\Services\Licensing\FeatureFlagResolver;
use App\Services\Search\Contracts\SearchDomain;
use App\Services\Search\Dto\SearchDomainFilters;
use Illuminate\Support\Facades\Gate;

/** Artikel nach Name, Nummer und GTIN; ohne Personen- und Kundenbezug. */
final class ArticleSearchDomain implements SearchDomain {
    public function __construct(private readonly FeatureFlagResolver $features) {}

    public function key(): string {
        return 'articles';
    }

    public function label(): string {
        return (string) __('search.domain.articles');
    }

    public function icon(): string {
        return 'inventory';
    }

    public function order(): int {
        return 45;
    }

    public function available(User $user): bool {
        return $this->features->routeEnabled('articles.show') && Gate::forUser($user)->allows('viewAny', Article::class);
    }

    public function search(User $user, int $organizationId, string $term, SearchDomainFilters $filters, int $limit): array {
        if ($filters->person !== null || $filters->customer !== null) {
            return [];
        }
        $query = Article::query()
            ->where('organization_id', $organizationId)
            ->where(fn ($q) => $q->whereLikeEscaped('name', $term)
                ->orWhereLikeEscaped('number', $term)
                ->orWhereLikeEscaped('gtin', $term));
        $filters->range($query, 'created_at');

        return array_values($query->orderBy('name')->limit($limit)->get()
            ->map(fn (Article $a) => [
                'id' => $a->id,
                'title' => (string) $a->name,
                'subtitle' => implode(' · ', array_filter([$a->number ? '#' . $a->number : null, $a->status->label()])) ?: null,
                'url' => route('articles.show', $a),
            ])
            ->all());
    }
}
