<?php
/*
 * Created on   : Fri Oct 23 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : AgreementCockpitPanel.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Contract\Cockpit;

use App\Enums\Contract\ContractKind;
use App\Models\Contract\Contract;
use App\Models\Customer\Customer;
use App\Models\Platform\User;
use App\Services\Cockpit\Contracts\CockpitPanel;
use App\Services\Licensing\FeatureFlagResolver;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Gate;

/** Kundenvereinbarungen AVV/NDA (Feature 157, MVP-822) mit ihrer neuesten Fassung. */
final class AgreementCockpitPanel implements CockpitPanel {
    public function __construct(private readonly FeatureFlagResolver $features) {}

    public function order(): int {
        return 30;
    }

    public function supports(Model $subject): bool {
        return $subject instanceof Customer;
    }

    public function render(Model $subject, User $viewer): ?View {
        if (! $subject instanceof Customer || ! $this->features->isEnabled('module.contracts') || ! Gate::forUser($viewer)->allows('viewAny', Contract::class)) {
            return null;
        }

        return view('customers._agreements_panel', [
            'customer' => $subject,
            'agreements' => Contract::query()
                ->where('customer_id', $subject->id)
                ->whereIn('kind', array_map(static fn (ContractKind $k): string => $k->value, ContractKind::signingKinds()))
                ->with('latestSigningRevision')
                ->orderByDesc('id')
                ->get(),
        ]);
    }
}
