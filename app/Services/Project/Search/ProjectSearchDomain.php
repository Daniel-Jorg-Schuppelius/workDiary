<?php
/*
 * Created on   : Fri Oct 23 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ProjectSearchDomain.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Project\Search;

use App\Models\Platform\User;
use App\Models\Project\Project;
use App\Services\Licensing\FeatureFlagResolver;
use App\Services\Search\Contracts\SearchDomain;
use App\Services\Search\Dto\SearchDomainFilters;
use Illuminate\Support\Facades\Gate;

/** Projekte nach Name; Kundenfilter über den Projektkunden, kein Personenbezug. */
final class ProjectSearchDomain implements SearchDomain {
    public function __construct(private readonly FeatureFlagResolver $features) {}

    public function key(): string {
        return 'projects';
    }

    public function label(): string {
        return (string) __('Projekte');
    }

    public function icon(): string {
        return 'folder_special';
    }

    public function order(): int {
        return 20;
    }

    public function available(User $user): bool {
        return $this->features->routeEnabled('projects.show') && Gate::forUser($user)->allows('viewAny', Project::class);
    }

    public function search(User $user, int $organizationId, string $term, SearchDomainFilters $filters, int $limit): array {
        if ($filters->person !== null) {
            return [];
        }
        $query = Project::query()
            ->where('organization_id', $organizationId)
            ->where(fn ($q) => $q->whereLikeEscaped('name', $term))
            ->when($filters->customer !== null, fn ($q) => $q->where('customer_id', $filters->customer))
            ->with('customer:id,name');
        $filters->range($query, 'created_at');

        return array_values($query->orderBy('name')->limit($limit)->get()
            ->map(fn (Project $p) => [
                'id' => $p->id,
                'title' => (string) $p->name,
                'subtitle' => $p->customer?->name,
                'url' => route('projects.show', $p),
            ])
            ->all());
    }
}
