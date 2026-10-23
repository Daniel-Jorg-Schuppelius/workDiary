<?php
/*
 * Created on   : Fri Oct 23 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ContractCockpitPanel.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Contract\Cockpit;

use App\Enums\Contract\{ContractKind, ContractObligationStatus};
use App\Models\Contract\{Contract, ContractObligation};
use App\Models\Customer\Customer;
use App\Models\Platform\User;
use App\Models\Supplier\Supplier;
use App\Services\Cockpit\Contracts\CockpitPanel;
use App\Services\Licensing\FeatureFlagResolver;
use App\Support\CarbonFmt;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Gate;

/**
 * Verträge des Kunden oder Lieferanten mit der nächsten offenen Frist
 * (Kündigung, Verlängerung, Pflichten; Feature 079). Kundenvereinbarungen
 * AVV/NDA zeigt das eigene Panel.
 */
final class ContractCockpitPanel implements CockpitPanel {
    private const LIMIT = 10;

    public function __construct(private readonly FeatureFlagResolver $features) {}

    public function order(): int {
        return 20;
    }

    public function supports(Model $subject): bool {
        return $subject instanceof Customer || $subject instanceof Supplier;
    }

    public function render(Model $subject, User $viewer): ?View {
        $gate = Gate::forUser($viewer);
        if (! $this->features->routeEnabled('contracts.show') || ! $gate->allows('viewAny', Contract::class)) {
            return null;
        }
        $contracts = Contract::query()
            ->where($subject instanceof Customer ? 'customer_id' : 'supplier_id', $subject->getKey())
            ->when($subject instanceof Customer, fn ($q) => $q->whereNotIn('kind', array_map(static fn (ContractKind $k): string => $k->value, ContractKind::signingKinds())))
            ->with(['obligations' => fn ($q) => $q->where('status', ContractObligationStatus::Open)->orderBy('due_on')])
            ->orderByDesc('id')
            ->get()
            ->filter(fn (Contract $c): bool => $gate->allows('view', $c))
            ->values();
        if ($contracts->isEmpty()) {
            return null;
        }

        $rows = [];
        foreach ($contracts->take(self::LIMIT) as $contract) {
            /** @var ContractObligation|null $next */
            $next = $contract->obligations->first();
            $rows[] = [
                'title' => (string) $contract->title,
                'url' => route('contracts.show', $contract),
                'subtitle' => implode(' · ', array_filter([$contract->number, $next?->title])) ?: null,
                'badge' => $contract->status->label(),
                'tone' => $contract->status->isOpen() ? 'info' : 'ghost',
                'date' => $next !== null ? CarbonFmt::fdate($next->due_on) : null,
            ];
        }

        return view('cockpit._list', [
            'id' => 'cockpit-contracts',
            'title' => __('cockpit.contracts.title'),
            'icon' => 'contract',
            'count' => $contracts->count(),
            'rows' => $rows,
        ]);
    }
}
