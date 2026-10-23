<?php
/*
 * Created on   : Fri Oct 23 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : PurchaseOrderCockpitPanel.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Procurement\Cockpit;

use App\Enums\Procurement\PurchaseOrderStatus;
use App\Enums\User\Permission;
use App\Models\Platform\User;
use App\Models\Procurement\PurchaseOrder;
use App\Models\Supplier\Supplier;
use App\Services\Cockpit\Contracts\CockpitPanel;
use App\Services\Licensing\FeatureFlagResolver;
use App\Support\CarbonFmt;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Gate;

/** Letzte Bestellungen beim Lieferanten (Einkauf) — Rechte wie die Bestellliste. */
final class PurchaseOrderCockpitPanel implements CockpitPanel {
    private const LIMIT = 10;

    public function __construct(private readonly FeatureFlagResolver $features) {}

    public function order(): int {
        return 10;
    }

    public function supports(Model $subject): bool {
        return $subject instanceof Supplier;
    }

    public function render(Model $subject, User $viewer): ?View {
        $gate = Gate::forUser($viewer);
        if (! $this->features->routeEnabled('purchase-orders.show')
            || ! ($gate->allows(Permission::InventoryViewAny->value) || $gate->allows(Permission::InventoryPost->value))) {
            return null;
        }
        $query = PurchaseOrder::query()->where('supplier_id', $subject->getKey());
        $count = (clone $query)->count();
        if ($count === 0) {
            return null;
        }

        return view('cockpit._list', [
            'id' => 'cockpit-purchase-orders',
            'title' => __('cockpit.purchase_orders.title'),
            'icon' => 'shopping_cart',
            'count' => $count,
            'rows' => array_map(static fn (PurchaseOrder $o): array => [
                'title' => (string) $o->number,
                'url' => route('purchase-orders.show', $o),
                'subtitle' => null,
                'badge' => $o->status->label(),
                'tone' => in_array($o->status, [PurchaseOrderStatus::Ordered, PurchaseOrderStatus::PartiallyReceived], true) ? 'info' : 'ghost',
                'date' => $o->created_at !== null ? CarbonFmt::fdate($o->created_at) : null,
            ], $query->orderByDesc('created_at')->orderByDesc('id')->limit(self::LIMIT)->get()->all()),
        ]);
    }
}
