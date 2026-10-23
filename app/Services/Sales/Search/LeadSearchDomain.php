<?php
/*
 * Created on   : Fri Oct 23 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : LeadSearchDomain.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Sales\Search;

use App\Enums\User\Permission;
use App\Models\Platform\User;
use App\Models\Sales\Lead;
use App\Services\Licensing\FeatureFlagResolver;
use App\Services\Search\Contracts\SearchDomain;
use App\Services\Search\Dto\SearchDomainFilters;
use Illuminate\Support\Facades\Gate;

/** Leads nach Firma, Ansprechpartner und E-Mail; Person = Verantwortliche, Kunde = konvertierter Kunde. */
final class LeadSearchDomain implements SearchDomain {
    public function __construct(private readonly FeatureFlagResolver $features) {}

    public function key(): string {
        return 'leads';
    }

    public function label(): string {
        return (string) __('search.domain.leads');
    }

    public function icon(): string {
        return 'person_search';
    }

    public function order(): int {
        return 18;
    }

    public function available(User $user): bool {
        return $this->features->routeEnabled('leads.show') && Gate::forUser($user)->allows(Permission::CustomerViewAny->value) && Gate::forUser($user)->allows(Permission::CustomerView->value);
    }

    public function search(User $user, int $organizationId, string $term, SearchDomainFilters $filters, int $limit): array {
        $query = Lead::query()
            ->where('organization_id', $organizationId)
            ->where(fn ($q) => $q->whereLikeEscaped('company', $term)
                ->orWhereLikeEscaped('contact_name', $term)
                ->orWhereLikeEscaped('email', $term))
            ->when($filters->person !== null, fn ($q) => $q->where('responsible_user_id', $filters->person))
            ->when($filters->customer !== null, fn ($q) => $q->where('customer_id', $filters->customer));
        $filters->range($query, 'created_at');

        return array_values($query->orderByDesc('created_at')->limit($limit)->get()
            ->map(fn (Lead $l) => [
                'id' => $l->id,
                'title' => (string) ($l->company ?: $l->contact_name ?: $l->email),
                'subtitle' => implode(' · ', array_filter([$l->company ? $l->contact_name : null, $l->status->label()])) ?: null,
                'url' => route('leads.show', $l),
            ])
            ->all());
    }
}
