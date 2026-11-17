<?php
/*
 * Created on   : Thu Nov 12 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : KnowledgePageSearchDomain.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Knowledge\Pages\Search;

use App\Models\Knowledge\KnowledgePage;
use App\Models\Platform\User;
use App\Services\Knowledge\Pages\{KnowledgePageAccess, KnowledgePageSearch};
use App\Services\Licensing\FeatureFlagResolver;
use App\Services\Search\Contracts\SearchDomain;
use App\Services\Search\Dto\SearchDomainFilters;
use Illuminate\Support\Facades\Gate;

/**
 * Wissensseiten in der globalen Suche (Feature 180/191, MVP-1667): Titel und
 * Text, mit Glossar-Synonymen, nur lesbare Bereiche und für Leser nur die
 * freigegebene Fassung. Seiten gehören keinem Kunden — ein Kundenfilter
 * ergibt keine Treffer; der Personenfilter trifft die verantwortliche Person.
 */
final class KnowledgePageSearchDomain implements SearchDomain {
    public function __construct(
        private readonly FeatureFlagResolver $features,
        private readonly KnowledgePageAccess $access,
        private readonly KnowledgePageSearch $search,
    ) {}

    public function key(): string {
        return 'knowledge_pages';
    }

    public function label(): string {
        return (string) __('knowledge_pages.title');
    }

    public function icon(): string {
        return 'auto_stories';
    }

    public function order(): int {
        return 65;
    }

    public function available(User $user): bool {
        return $this->features->isEnabled('module.knowledge') && Gate::forUser($user)->allows('viewAny', KnowledgePage::class);
    }

    public function search(User $user, int $organizationId, string $term, SearchDomainFilters $filters, int $limit): array {
        if ($filters->customer !== null) {
            return [];
        }
        $forms = $this->search->expand($organizationId, $term);
        $query = $this->access->scope(KnowledgePage::query()->where('knowledge_pages.organization_id', $organizationId), $user)
            ->with('space:id,title')
            ->when($filters->person !== null, static fn ($q) => $q->where('owner_user_id', $filters->person));
        $this->search->apply($query, $user, $forms);
        $filters->range($query, 'knowledge_pages.updated_at');

        return array_values($query->orderByDesc('updated_at')->limit($limit)->get()
            ->map(fn (KnowledgePage $page): array => [
                'id' => $page->id,
                'title' => $page->title,
                'subtitle' => $page->space?->title,
                'url' => route('knowledge.pages.show', $page),
            ])
            ->all());
    }
}
