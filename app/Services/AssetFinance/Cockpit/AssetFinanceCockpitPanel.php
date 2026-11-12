<?php
/*
 * Created on   : Wed Nov 11 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : AssetFinanceCockpitPanel.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\AssetFinance\Cockpit;

use App\Models\Asset\Asset;
use App\Models\AssetFinance\AssetFinanceContract;
use App\Models\Platform\User;
use App\Models\Supplier\Supplier;
use App\Services\Cockpit\Contracts\CockpitPanel;
use App\Services\Licensing\FeatureFlagResolver;
use App\Support\CarbonFmt;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\{Builder, Model};
use Illuminate\Support\Facades\Gate;

/**
 * Leasing- und Finanzierungsverträge (Feature 074) am Asset und beim
 * Leasinggeber (MVP-1617): laufende zuerst, mit Laufzeitende.
 */
final class AssetFinanceCockpitPanel implements CockpitPanel {
    private const int LIMIT = 10;

    public function __construct(private readonly FeatureFlagResolver $features) {}

    public function order(): int {
        return 25;
    }

    public function supports(Model $subject): bool {
        return $subject instanceof Asset || $subject instanceof Supplier;
    }

    public function render(Model $subject, User $viewer): ?View {
        $gate = Gate::forUser($viewer);
        if (! $this->features->routeEnabled('asset-finance.show') || ! $gate->allows('viewAny', AssetFinanceContract::class)) {
            return null;
        }
        $contracts = AssetFinanceContract::query()
            ->when($subject instanceof Asset, fn (Builder $q) => $q->whereHas('contractAssets', fn (Builder $a) => $a->where('asset_id', $subject->getKey())))
            ->when($subject instanceof Supplier, fn (Builder $q) => $q->where('supplier_id', $subject->getKey()))
            ->orderByDesc('starts_on')
            ->orderByDesc('id')
            ->get()
            ->filter(fn (AssetFinanceContract $c): bool => $gate->allows('view', $c))
            ->sortBy(fn (AssetFinanceContract $c): int => $c->status->isOpen() ? 0 : 1)
            ->values();
        if ($contracts->isEmpty()) {
            return null;
        }

        return view('cockpit._list', [
            'id' => 'cockpit-asset-finance',
            'title' => __('cockpit.asset_finance.title'),
            'icon' => 'request_quote',
            'count' => $contracts->count(),
            'rows' => array_map(static fn (AssetFinanceContract $c): array => [
                'title' => implode(' · ', array_filter([(string) $c->number, $c->kind->label()])),
                'url' => route('asset-finance.show', $c),
                'subtitle' => $c->partner_name,
                'badge' => $c->status->label(),
                'tone' => $c->status->isOpen() ? 'info' : 'ghost',
                'date' => $c->ends_on !== null ? CarbonFmt::fdate($c->ends_on) : null,
            ], $contracts->take(self::LIMIT)->all()),
        ]);
    }
}
