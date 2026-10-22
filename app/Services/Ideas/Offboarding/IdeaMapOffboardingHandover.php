<?php
/*
 * Created on   : Wed Oct 21 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : IdeaMapOffboardingHandover.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Ideas\Offboarding;

use App\Models\Ideas\IdeaMap;
use App\Models\Platform\User;
use App\Services\Ideas\IdeaMapService;
use App\Services\Org\Contracts\OffboardingHandover;
use Illuminate\Database\Eloquent\Builder;

/**
 * Ideenkarten beim Austritt (Entscheidung 2026-10-21): Die verwaltende Person
 * sieht nur die Anzahl, nie Titel oder Inhalt; die Übertragung ist auditiert.
 */
final class IdeaMapOffboardingHandover implements OffboardingHandover {
    public function __construct(private readonly IdeaMapService $maps) {}

    public function key(): string {
        return 'idea_maps';
    }

    public function label(int $count): string {
        return trans_choice('ideas.offboarding.maps', $count, ['count' => $count]);
    }

    public function count(User $member): int {
        return $this->owned($member)->count();
    }

    public function handOver(User $member, User $successor, User $actor): int {
        $maps = $this->owned($member)->get();
        foreach ($maps as $map) {
            $this->maps->transferOwnership($map, $successor, $actor);
        }

        return $maps->count();
    }

    /** @return Builder<IdeaMap> */
    private function owned(User $member): Builder {
        return IdeaMap::query()->withoutGlobalScopes()->withTrashed()
            ->where('organization_id', $member->organization_id)
            ->where('owner_user_id', $member->id);
    }
}
