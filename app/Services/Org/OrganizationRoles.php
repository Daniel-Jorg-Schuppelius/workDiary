<?php
/*
 * Created on   : Wed Oct 21 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : OrganizationRoles.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Org;

use Illuminate\Database\Eloquent\Collection;
use Spatie\Permission\Models\Role;

/**
 * Rollen einer Organisation nach Namen. Spatie (`Role::findOrCreate`,
 * `assignRole('name')`, `User::role('name')`) sucht mit Teams
 * `team_id IS NULL OR team_id = …` ohne Ordnung und nimmt die erste Zeile;
 * neben jeder Org-Rolle `admin` liegt die globale gleichen Namens.
 */
final class OrganizationRoles {
    /** Zum Vergeben: immer die Zeile der Organisation. */
    public static function named(string $name, int $organizationId): Role {
        return Role::query()->firstOrCreate([
            (string) config('permission.column_names.team_foreign_key', 'team_id') => $organizationId,
            'name' => $name,
            'guard_name' => 'web',
        ]);
    }

    /**
     * Zum Filtern (`User::role(…)`): alle Zeilen, die in der Organisation
     * gelten — Bestandskonten tragen `admin` teils an der globalen Zeile.
     *
     * @param list<string> $names
     * @return Collection<int, Role>
     */
    public static function matching(array $names, int $organizationId): Collection {
        $team = (string) config('permission.column_names.team_foreign_key', 'team_id');

        return Role::query()
            ->whereIn('name', $names)
            ->where('guard_name', 'web')
            ->where(static fn ($q) => $q->whereNull($team)->orWhere($team, $organizationId))
            ->get();
    }
}
