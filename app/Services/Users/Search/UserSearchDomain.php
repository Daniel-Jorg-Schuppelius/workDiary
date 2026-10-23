<?php
/*
 * Created on   : Fri Oct 23 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : UserSearchDomain.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Users\Search;

use App\Models\Platform\User;
use App\Services\Search\Contracts\SearchDomain;
use App\Services\Search\Dto\SearchDomainFilters;
use Illuminate\Support\Facades\Gate;

/** Mitarbeitende nach Name und E-Mail — nur für Admins und Mitgliederverwaltung; kein Kundenbezug. */
final class UserSearchDomain implements SearchDomain {
    public function key(): string {
        return 'users';
    }

    public function label(): string {
        return (string) __('user.employees');
    }

    public function icon(): string {
        return 'group';
    }

    public function order(): int {
        return 60;
    }

    public function available(User $user): bool {
        return $user->isAdmin() || Gate::forUser($user)->allows('manage-members');
    }

    public function search(User $user, int $organizationId, string $term, SearchDomainFilters $filters, int $limit): array {
        if ($filters->customer !== null) {
            return [];
        }
        $query = User::query()
            ->where('organization_id', $organizationId)
            ->where(fn ($q) => $q->whereLikeEscaped('name', $term)->orWhereLikeEscaped('email', $term))
            ->when($filters->person !== null, fn ($q) => $q->whereKey($filters->person));
        $filters->range($query, 'created_at');
        $url = Gate::forUser($user)->allows('manage-members') ? route('org.members.index') : '#';

        return array_values($query->orderBy('name')->limit($limit)->get()
            ->map(fn (User $u) => [
                'id' => $u->id,
                'title' => (string) $u->name,
                'subtitle' => $u->email,
                'url' => $url,
            ])
            ->all());
    }
}
