<?php
/*
 * Created on   : Fri Oct 02 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : TakeoffsWithoutTransfer.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Takeoff\Chain;

use App\Enums\Takeoff\TakeoffStatus;
use App\Enums\User\Permission;
use App\Models\Platform\{Organization, User};
use App\Models\Takeoff\Takeoff;
use App\Services\Billing\Contracts\DocumentChainSource;
use App\Services\Billing\Dto\DocumentChainItem;
use App\Support\CarbonFmt;
use Illuminate\Database\Eloquent\Builder;

/** Belegkette (MVP-1059): abgeschlossene Aufmaße, deren Mengen noch in keinen Beleg übernommen wurden. */
final class TakeoffsWithoutTransfer implements DocumentChainSource {
    public function key(): string {
        return 'takeoffs_to_bill';
    }

    public function label(): string {
        return (string) __('takeoff.chain.label');
    }

    public function icon(): string {
        return 'straighten';
    }

    public function routeName(): string {
        return 'takeoffs.show';
    }

    public function availableFor(User $user): bool {
        return $user->can(Permission::DiaryUpdate->value);
    }

    public function count(Organization $organization): int {
        return $this->query($organization)->count();
    }

    public function items(Organization $organization, int $limit): array {
        return array_values($this->query($organization)->with(['diaryEntry:id,title', 'project:id,name'])->orderBy('measured_on')->limit($limit)->get()
            ->map(fn (Takeoff $takeoff): DocumentChainItem => new DocumentChainItem(
                title: $takeoff->title,
                detail: $takeoff->diaryEntry->title ?? $takeoff->project->name ?? (string) __('takeoff.chain.measured_on', ['date' => $takeoff->measured_on !== null ? CarbonFmt::fdate($takeoff->measured_on) : '—']),
                url: route('takeoffs.show', $takeoff),
                date: $takeoff->measured_on,
            ))->all());
    }

    /** @return Builder<Takeoff> */
    private function query(Organization $organization): Builder {
        return Takeoff::query()
            ->where('organization_id', $organization->id)
            ->where('status', TakeoffStatus::Completed->value)
            ->whereDoesntHave('transfers');
    }
}
