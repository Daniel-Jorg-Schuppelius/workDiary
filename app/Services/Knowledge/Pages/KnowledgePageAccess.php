<?php
/*
 * Created on   : Thu Nov 12 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : KnowledgePageAccess.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Knowledge\Pages;

use App\Enums\Knowledge\KnowledgeAudienceKind;
use App\Enums\User\Permission;
use App\Models\Knowledge\{KnowledgePage, KnowledgeSpace, KnowledgeSpaceReader};
use App\Models\Platform\User;
use Illuminate\Database\Eloquent\Builder;
use Spatie\Permission\PermissionRegistrar;

/**
 * Wer welche Wissensseite sieht (Feature 191, MVP-1664/1667) — die eine
 * Stelle für Liste, Suche, Sammlungen, Cockpit und Policy.
 *
 * - Lesen verlangt `knowledge.page.viewAny` und einen lesbaren Bereich:
 *   offene Bereiche für alle, eingeschränkte nur für ihre Leser (Rolle, Team,
 *   Person) sowie für Verwaltende.
 * - Wer Seiten bearbeiten darf, sieht Entwürfe und die aktuelle Fassung; alle
 *   anderen nur Seiten mit freigegebener Fassung, und zwar diese.
 */
final class KnowledgePageAccess {
    /** @var array<int, list<int>> lesbare Bereiche je Nutzer (eine Anfrage) */
    private array $spaces = [];

    public function canEdit(User $user): bool {
        return $user->isAdmin() || $user->can(Permission::KnowledgePageEdit->value);
    }

    public function canManage(User $user): bool {
        return $user->isAdmin() || $user->can(Permission::KnowledgePageManage->value);
    }

    /** @return list<int> */
    public function readableSpaceIds(User $user): array {
        if (isset($this->spaces[$user->id])) {
            return $this->spaces[$user->id];
        }
        $spaces = KnowledgeSpace::query()->where('organization_id', $user->organization_id)->get(['id', 'is_restricted']);
        $manage = $this->canManage($user);
        $restricted = array_values(array_map('intval', $spaces->filter(static fn (KnowledgeSpace $space): bool => $space->is_restricted && ! $manage)->pluck('id')->all()));
        $granted = $restricted === [] ? [] : $this->grantedSpaceIds($user, $restricted);

        return $this->spaces[$user->id] = array_values(array_map('intval', $spaces
            ->filter(static fn (KnowledgeSpace $space): bool => ! $space->is_restricted || $manage || in_array((int) $space->id, $granted, true))
            ->pluck('id')
            ->all()));
    }

    public function canReadSpace(User $user, int $spaceId): bool {
        return in_array($spaceId, $this->readableSpaceIds($user), true);
    }

    /**
     * Sichtbare Seiten — dieselbe Regel wie die Policy, als Abfrage.
     *
     * @param  Builder<KnowledgePage>  $query
     * @return Builder<KnowledgePage>
     */
    public function scope(Builder $query, User $user): Builder {
        if (! $user->isAdmin() && ! $user->can(Permission::KnowledgePageViewAny->value)) {
            return $query->whereRaw('1 = 0');
        }
        $query->whereIn($query->getModel()->qualifyColumn('knowledge_space_id'), $this->readableSpaceIds($user) ?: [0]);
        if (! $this->canEdit($user)) {
            $query->whereNotNull($query->getModel()->qualifyColumn('approved_version'));
        }

        return $query;
    }

    /** Die Fassung, die diese Person sieht: Bearbeitende die aktuelle, alle anderen die freigegebene. */
    public function displayedVersion(KnowledgePage $page, User $user): ?int {
        return $this->canEdit($user) ? $page->version : $page->approved_version;
    }

    /**
     * Rollennamen der Person in ihrer Organisation — im Team-Kontext der
     * Organisation gelesen, damit Konsole, Queue und Web dasselbe sehen.
     *
     * @return list<string>
     */
    public function roleNames(User $user): array {
        $registrar = app(PermissionRegistrar::class);
        $previous = $registrar->getPermissionsTeamId();
        $registrar->setPermissionsTeamId($user->organization_id);
        try {
            return array_values(array_map('strval', $user->roles()->pluck('name')->all()));
        } finally {
            $registrar->setPermissionsTeamId($previous);
        }
    }

    /** @return list<int> */
    public function teamIds(User $user): array {
        return array_values(array_map('intval', $user->teams()->pluck('teams.id')->all()));
    }

    /**
     * @param  list<int>  $restricted
     * @return list<int>
     */
    private function grantedSpaceIds(User $user, array $restricted): array {
        $roles = $this->roleNames($user);
        $teams = $this->teamIds($user);

        return array_values(array_map('intval', KnowledgeSpaceReader::query()
            ->whereIn('knowledge_space_id', $restricted)
            ->where(static function (Builder $match) use ($user, $roles, $teams): void {
                $match->where(static fn (Builder $q) => $q->where('kind', KnowledgeAudienceKind::User->value)->where('user_id', $user->id));
                if ($teams !== []) {
                    $match->orWhere(static fn (Builder $q) => $q->where('kind', KnowledgeAudienceKind::Team->value)->whereIn('team_id', $teams));
                }
                if ($roles !== []) {
                    $match->orWhere(static fn (Builder $q) => $q->where('kind', KnowledgeAudienceKind::Role->value)->whereIn('role_name', $roles));
                }
            })
            ->distinct()
            ->pluck('knowledge_space_id')
            ->all()));
    }
}
