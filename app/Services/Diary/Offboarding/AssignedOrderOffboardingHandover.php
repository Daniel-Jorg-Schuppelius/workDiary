<?php
/*
 * Created on   : Thu Nov 12 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : AssignedOrderOffboardingHandover.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Diary\Offboarding;

use App\Models\Diary\DiaryEntry;
use App\Models\Platform\User;
use App\Models\Scopes\OrganizationScope;
use App\Services\Diary\OrderAssignment;
use App\Services\Org\Contracts\OffboardingHandover;
use Illuminate\Database\Eloquent\Builder;

/**
 * Offene, der Person zugewiesene Aufträge an eine Nachfolge — beim Austritt
 * und bei der endgültigen Übergabe ohne Austritt (Feature 202, MVP-1648).
 * Aufträge, für die der Nachfolge eine Pflichtqualifikation fehlt, bleiben.
 */
final class AssignedOrderOffboardingHandover implements OffboardingHandover {
    public function __construct(private readonly OrderAssignment $assignment) {}

    public function key(): string {
        return 'assigned_orders';
    }

    public function label(int $count): string {
        return trans_choice('delegation.handover.area.assigned_orders', $count, ['count' => $count]);
    }

    public function count(User $member): int {
        return $this->assigned($member)->count();
    }

    public function handOver(User $member, User $successor, User $actor): int {
        $moved = 0;
        foreach ($this->assigned($member)->orderBy('id')->get() as $entry) {
            if ($this->assignment->reassign($entry, $successor)) {
                $moved++;
            }
        }

        return $moved;
    }

    /** @return Builder<DiaryEntry> */
    private function assigned(User $member): Builder {
        return DiaryEntry::query()->withoutGlobalScope(OrganizationScope::class)
            ->where('organization_id', $member->organization_id)
            ->where('assigned_user_id', $member->id)
            ->notArchived()
            ->open();
    }
}
