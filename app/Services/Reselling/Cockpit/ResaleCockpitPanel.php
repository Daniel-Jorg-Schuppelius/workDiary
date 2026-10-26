<?php
/*
 * Created on   : Fri Oct 23 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ResaleCockpitPanel.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Reselling\Cockpit;

use App\Enums\User\Permission;
use App\Models\Customer\Customer;
use App\Models\Platform\{Organization, User};
use App\Models\Reselling\{ResaleLicenseAssignment, ResalePeriod, ResaleSubscription};
use App\Services\Cockpit\Contracts\CockpitPanel;
use App\Services\Licensing\FeatureFlagResolver;
use App\Services\Reselling\Mirror\InvoiceMirror;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Gate;

/**
 * Abos und Lizenzen des Kunden und seiner Fremdkunden (Feature 152, MVP-758,
 * MVP-1024) mit der Zahl fälliger Perioden — ohne Lizenzschlüssel.
 */
final class ResaleCockpitPanel implements CockpitPanel {
    public function __construct(private readonly FeatureFlagResolver $features, private readonly InvoiceMirror $mirror) {}

    public function order(): int {
        return 70;
    }

    public function supports(Model $subject): bool {
        return $subject instanceof Customer;
    }

    public function render(Model $subject, User $viewer): ?View {
        if (! $subject instanceof Customer || ! $this->features->isEnabled('module.reselling') || ! Gate::forUser($viewer)->allows(Permission::ResellingView->value)) {
            return null;
        }
        $today = ResalePeriod::today();
        $licenses = ResaleLicenseAssignment::query()
            ->whereNull('ended_at')
            ->where('customer_id', $subject->id)
            ->with(['unit.batch.product', 'foreignCustomer:id,name'])
            ->orderByDesc('sold_on')
            ->limit(200)
            ->get();
        $organization = $subject->getRelationValue('organization');
        if ($organization instanceof Organization) {
            $this->mirror->preload($organization, $licenses);
        }

        return view('customers._resale_panel', [
            'customer' => $subject,
            'resaleModuleActive' => true,
            'customerSubscriptions' => ResaleSubscription::query()
                ->with(['foreignCustomer:id,name'])
                ->withCount(['periods as open_periods_count' => static fn ($q) => $q->due($today)])
                ->forCustomer($subject)
                ->planning()
                ->orderBy('label')
                ->limit(200)
                ->get(),
            'customerLicenses' => $licenses,
        ]);
    }
}
