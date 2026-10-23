<?php
/*
 * Created on   : Fri Oct 23 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : DomainCockpitPanel.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Domain\Cockpit;

use App\Enums\User\Permission;
use App\Models\Customer\Customer;
use App\Models\Domain\{DomainProjection, DomainResellerAccount};
use App\Models\Platform\User;
use App\Services\Cockpit\Contracts\CockpitPanel;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Gate;

/**
 * Domains des Kunden (Feature 083, MVP-394): direkt zugeordnete und die der
 * zugeordneten Reseller-Accounts (Subuser).
 */
final class DomainCockpitPanel implements CockpitPanel {
    public function order(): int {
        return 60;
    }

    public function supports(Model $subject): bool {
        return $subject instanceof Customer;
    }

    public function render(Model $subject, User $viewer): ?View {
        if (! $subject instanceof Customer || ! Gate::forUser($viewer)->allows(Permission::DomainViewAny->value)) {
            return null;
        }
        $resellerAccountIds = DomainResellerAccount::query()->where('customer_id', $subject->id)->pluck('id');
        $domains = DomainProjection::query()
            ->with('foreignCustomer:id,name')
            ->where(function ($q) use ($subject, $resellerAccountIds): void {
                $q->where('customer_id', $subject->id);
                if ($resellerAccountIds->isNotEmpty()) {
                    $q->orWhereIn('reseller_account_id', $resellerAccountIds->all());
                }
            })
            ->orderBy('external_domain')
            ->limit(200)
            ->get();

        return view('customers._domains_panel', ['customer' => $subject, 'customerDomains' => $domains]);
    }
}
