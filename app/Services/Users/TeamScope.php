<?php
/*
 * Created on   : Fri Oct 23 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : TeamScope.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Users;

use App\Enums\User\Permission;
use App\Models\Platform\User;

/**
 * Wen ein Team-Recht zeigt (Entscheidung 2026-10-23): die Mitglieder der
 * eigenen Teams und sich selbst. `null` heißt ganze Organisation — mit dem
 * Organisationsrecht, als Admin oder ohne Team-Recht (dann grenzt allein das
 * Fachrecht, etwa das Freigaberecht, nichts ein).
 */
final class TeamScope {
    /** @var array<int, list<int>> */
    private array $members = [];

    /** @return list<int>|null */
    public function userIds(User $viewer, Permission $team, Permission $organization): ?array {
        if ($viewer->isAdmin() || $viewer->can($organization->value) || ! $viewer->can($team->value)) {
            return null;
        }

        return $this->members[(int) $viewer->id] ??= array_values(array_unique([
            (int) $viewer->id,
            ...User::query()
                ->where('organization_id', $viewer->organization_id)
                ->whereHas('teams', fn ($q) => $q->whereIn('teams.id', $viewer->teams()->pluck('teams.id')))
                ->pluck('id')
                ->map(static fn ($id): int => (int) $id)
                ->all(),
        ]));
    }

    public function covers(User $viewer, int $userId, Permission $team, Permission $organization): bool {
        $ids = $this->userIds($viewer, $team, $organization);

        return $ids === null || in_array($userId, $ids, true);
    }
}
