<?php
/*
 * Created on   : Sat Oct 24 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ShopCustomerOrdersPanel.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Plugins\Support\ShopOrders;

use App\Models\Customer\Customer;
use App\Models\Platform\User;
use App\Plugins\PluginManager;
use App\Services\Cockpit\Contracts\CockpitPanel;
use App\Support\CarbonFmt;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Model;

/** Shop-Bestellungen am Kunden-Cockpit (Feature 182), nur für Admins bei aktivem Plugin. */
abstract class ShopCustomerOrdersPanel implements CockpitPanel {
    private const LIMIT = 10;

    public function __construct(private readonly PluginManager $plugins) {}

    abstract protected function pluginId(): string;

    /** @return class-string<Model&ShopOrder> */
    abstract protected function orderModel(): string;

    public function order(): int {
        return 82;
    }

    public function supports(Model $subject): bool {
        return $subject instanceof Customer;
    }

    public function render(Model $subject, User $viewer): ?View {
        if (! $viewer->isAdmin() || ! $this->plugins->enabled()->has($this->pluginId())) {
            return null;
        }
        $model = $this->orderModel();
        $query = $model::query()->where('organization_id', $subject->getAttribute('organization_id'))->where('customer_id', $subject->getKey());
        $count = (clone $query)->count();
        if ($count === 0) {
            return null;
        }

        return view('cockpit._list', [
            'id' => 'cockpit-' . $this->pluginId() . '-orders',
            'title' => __($this->pluginId() . '::' . $this->pluginId() . '.cockpit.title'),
            'icon' => 'storefront',
            'count' => $count,
            'rows' => array_map(fn (Model&ShopOrder $order): array => [
                'title' => $order->displayNumber(),
                'url' => route('admin.' . $this->pluginId() . '.index', ['q' => $order->getAttribute('order_number') ?? $order->getAttribute('remote_order_id')]),
                'subtitle' => $order->getAttribute('gross_amount')?->format(),
                'badge' => $order->getAttribute('remote_deleted_at') !== null ? __('integration.shop_orders.orders.deleted') : $order->getAttribute('status')?->label(),
                'tone' => $order->getAttribute('remote_deleted_at') !== null ? 'ghost' : ($order->getAttribute('status')?->tone() ?? 'ghost'),
                'date' => $order->getAttribute('ordered_at') !== null ? CarbonFmt::fdate($order->getAttribute('ordered_at')) : null,
            ], $query->orderByDesc('ordered_at')->orderByDesc('id')->limit(self::LIMIT)->get()->all()),
        ]);
    }
}
