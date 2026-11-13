<?php
/*
 * Created on   : Thu Nov 12 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : CustomerDeadlineOffboardingHandover.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Customer\Offboarding;

use App\Models\Customer\{CustomerDeadline, CustomerDeadlineProfile};
use App\Models\Platform\User;
use App\Models\Scopes\OrganizationScope;
use App\Services\Org\Contracts\OffboardingHandover;
use App\Support\Query\DateRange;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

/**
 * Zuständigkeit für Kundenfristen an eine Nachfolge (Feature 197/202,
 * MVP-1648): beim Austritt und bei der endgültigen Übergabe ohne Austritt.
 * Übertragen werden die laufenden und künftigen Fassungen der Fristmerkmale
 * und die offenen Fristen; Vergangenes behält seine zuständige Person.
 * Gezählt wird nach Kunden mit laufender Zuständigkeit.
 */
final class CustomerDeadlineOffboardingHandover implements OffboardingHandover {
    public function key(): string {
        return 'customer_deadlines';
    }

    public function label(int $count): string {
        return trans_choice('customer_deadline.handover.label', $count, ['count' => $count]);
    }

    public function count(User $member): int {
        return $this->customerCount($member);
    }

    public function handOver(User $member, User $successor, User $actor): int {
        return DB::transaction(function () use ($member, $successor): int {
            $customers = $this->customerCount($member);
            foreach ($this->profiles($member)->get() as $profile) {
                $profile->update(['responsible_user_id' => $successor->id]);
            }
            foreach ($this->deadlines($member)->get() as $deadline) {
                $deadline->update(['responsible_user_id' => $successor->id]);
            }

            return $customers;
        });
    }

    /** Kunden mit laufender Zuständigkeit (Fassung oder offene Frist). */
    private function customerCount(User $member): int {
        return $this->profiles($member)->pluck('customer_id')
            ->merge($this->deadlines($member)->pluck('customer_id'))
            ->unique()
            ->count();
    }

    /** @return Builder<CustomerDeadlineProfile> laufende und künftige Fassungen der Person */
    private function profiles(User $member): Builder {
        return CustomerDeadlineProfile::query()->withoutGlobalScope(OrganizationScope::class)
            ->where('organization_id', $member->organization_id)
            ->where('responsible_user_id', $member->id)
            ->where(static fn (Builder $q) => $q->whereNull('valid_until')->orWhere('valid_until', '>=', DateRange::day(CarbonImmutable::today())));
    }

    /** @return Builder<CustomerDeadline> */
    private function deadlines(User $member): Builder {
        return CustomerDeadline::query()->withoutGlobalScope(OrganizationScope::class)
            ->where('organization_id', $member->organization_id)
            ->where('responsible_user_id', $member->id)
            ->open();
    }
}
