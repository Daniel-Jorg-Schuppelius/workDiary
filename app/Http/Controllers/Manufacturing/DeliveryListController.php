<?php
/*
 * Created on   : Tue Sep 29 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : DeliveryListController.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Http\Controllers\Manufacturing;

use App\Http\Controllers\Concerns\ResolvesGlobalDateRange;
use App\Http\Controllers\Controller;
use App\Models\Inventory\StockDelivery;
use App\Models\Manufacturing\ManufacturingOrder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

/** Lieferscheinliste (Feature 047/128, MVP-1013): alle Auslieferungen ohne Umweg über den Fertigungsauftrag. */
class DeliveryListController extends Controller {
    use ResolvesGlobalDateRange;

    public const SHIPPING_FILTERS = ['all', 'open', 'shipped'];

    public function index(Request $request): View {
        Gate::authorize('viewAny', ManufacturingOrder::class);
        $shipping = in_array($request->query('shipping'), self::SHIPPING_FILTERS, true) ? (string) $request->query('shipping') : 'all';
        $search = trim((string) $request->query('q', ''));
        $range = $this->globalDateRange();

        $deliveries = StockDelivery::query()
            ->with(['customer:id,name,company,country', 'order:id,number', 'shipment'])
            ->whereBetween('delivered_at', [$range['from']->startOfDay(), $range['to']->endOfDay()])
            ->when($shipping === 'open', fn ($query) => $query->whereDoesntHave('shipment'))
            ->when($shipping === 'shipped', fn ($query) => $query->whereHas('shipment'))
            ->when($search !== '', fn ($query) => $query->where(fn ($inner) => $inner
                ->whereLikeEscaped('name_snapshot', $search)
                ->orWhereLikeEscaped('sku_snapshot', $search)
                ->orWhereHas('customer', fn ($customer) => $customer->whereLikeEscaped('name', $search)->orWhereLikeEscaped('company', $search))))
            ->orderByDesc('delivered_at')->orderByDesc('id')
            ->paginate(50)
            ->withQueryString();

        return view('manufacturing.deliveries.index', [
            'deliveries' => $deliveries,
            'shipping' => $shipping,
            'search' => $search,
        ]);
    }
}
