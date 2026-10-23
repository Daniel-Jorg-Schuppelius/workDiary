<?php
/*
 * Created on   : Fri Oct 23 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : DocumentSearchDomain.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Document\Search;

use App\Models\Document\Document;
use App\Models\Platform\User;
use App\Services\Licensing\FeatureFlagResolver;
use App\Services\Search\Contracts\SearchDomain;
use App\Services\Search\Dto\SearchDomainFilters;
use Illuminate\Support\Facades\Gate;

/** Dokumente (MVP-031) nach Titel; Link auf die vorgefilterte Liste, da es keine Detailseite gibt. */
final class DocumentSearchDomain implements SearchDomain {
    public function __construct(private readonly FeatureFlagResolver $features) {}

    public function key(): string {
        return 'documents';
    }

    public function label(): string {
        return (string) __('document.title.index');
    }

    public function icon(): string {
        return 'folder_open';
    }

    public function order(): int {
        return 70;
    }

    public function available(User $user): bool {
        return $this->features->isEnabled('module.documents') && Gate::forUser($user)->allows('viewAny', Document::class);
    }

    public function search(User $user, int $organizationId, string $term, SearchDomainFilters $filters, int $limit): array {
        if ($filters->person !== null || $filters->customer !== null) {
            return [];
        }
        $query = Document::query()->visibleTo($user)->where('organization_id', $organizationId)->whereLikeEscaped('title', $term);
        $filters->range($query, 'updated_at');

        return array_values($query->latest('updated_at')->limit($limit)->get()
            ->map(fn (Document $d) => [
                'id' => $d->id,
                'title' => (string) $d->title,
                'subtitle' => $d->document_type->label() . ' · ' . $d->effectiveStatus()->label(),
                'url' => route('documents.index', ['q' => $d->title]),
            ])
            ->all());
    }
}
