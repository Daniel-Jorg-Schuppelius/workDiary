<?php
/*
 * Created on   : Wed Nov 11 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : SupplierCatalogCockpitPanel.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Procurement\Cockpit;

use App\Enums\User\Permission;
use App\Models\Platform\User;
use App\Models\Supplier\{Supplier, SupplierCatalogSource};
use App\Services\Cockpit\Contracts\CockpitPanel;
use App\Services\Licensing\FeatureFlagResolver;
use App\Support\CarbonFmt;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Gate;

/** Kataloge des Lieferanten (Feature 050) im Lieferanten-360° (MVP-1617) — Rechte wie die Katalogliste. */
final class SupplierCatalogCockpitPanel implements CockpitPanel {
    private const int LIMIT = 10;

    public function __construct(private readonly FeatureFlagResolver $features) {}

    public function order(): int {
        return 12;
    }

    public function supports(Model $subject): bool {
        return $subject instanceof Supplier;
    }

    public function render(Model $subject, User $viewer): ?View {
        $gate = Gate::forUser($viewer);
        if (! $this->features->routeEnabled('supplier-catalogs.show')
            || ! ($gate->allows(Permission::InventoryViewAny->value) || $gate->allows(Permission::InventoryPost->value))) {
            return null;
        }
        $query = SupplierCatalogSource::query()->where('supplier_id', $subject->getKey());
        $count = (clone $query)->count();
        if ($count === 0) {
            return null;
        }

        return view('cockpit._list', [
            'id' => 'cockpit-supplier-catalogs',
            'title' => __('cockpit.supplier_catalogs.title'),
            'icon' => 'menu_book',
            'count' => $count,
            'rows' => array_map(static fn (SupplierCatalogSource $source): array => [
                'title' => (string) $source->name,
                'url' => route('supplier-catalogs.show', $source),
                'subtitle' => trans_choice('cockpit.supplier_catalogs.items', (int) $source->items_count, ['count' => (int) $source->items_count]),
                'badge' => $source->active ? __('cockpit.supplier_catalogs.active') : __('cockpit.supplier_catalogs.inactive'),
                'tone' => $source->active ? 'info' : 'ghost',
                'date' => $source->last_imported_at !== null ? CarbonFmt::fdate($source->last_imported_at) : null,
            ], $query->withCount('items')->orderByDesc('active')->orderBy('name')->limit(self::LIMIT)->get()->all()),
        ]);
    }
}
