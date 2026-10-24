<?php
/*
 * Created on   : Sat Oct 24 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ShopOrderSearchDomain.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Plugins\Support\ShopOrders;

use App\Models\Platform\User;
use App\Plugins\PluginManager;
use App\Services\Search\Contracts\SearchDomain;
use App\Services\Search\Dto\SearchDomainFilters;
use App\Support\CarbonFmt;
use Illuminate\Database\Eloquent\Model;

/**
 * Shop-Bestellungen in der globalen Suche (Feature 180/181): Bestellnummer,
 * Shop-ID oder Käufer, mit Kunden- und Zeitraumfilter. Sichtbar wie die
 * Bestellliste nur für Admins und nur bei aktivem Plugin.
 */
abstract class ShopOrderSearchDomain implements SearchDomain {
    public function __construct(private readonly PluginManager $plugins) {}

    abstract protected function pluginId(): string;

    /** @return class-string<Model&ShopOrder> */
    abstract protected function orderModel(): string;

    public function key(): string {
        return $this->pluginId() . '_orders';
    }

    public function label(): string {
        return (string) __($this->pluginId() . '::' . $this->pluginId() . '.search.domain');
    }

    public function icon(): string {
        return 'storefront';
    }

    public function order(): int {
        return 38;
    }

    public function available(User $user): bool {
        return $user->isAdmin() && $this->plugins->enabled()->has($this->pluginId());
    }

    public function search(User $user, int $organizationId, string $term, SearchDomainFilters $filters, int $limit): array {
        if ($filters->person !== null) {
            return [];
        }
        $model = $this->orderModel();
        $query = $model::query()
            ->where('organization_id', $organizationId)
            ->where(fn ($q) => $model::whereMatching($q, $term))
            ->when($filters->customer !== null, fn ($q) => $q->where('customer_id', $filters->customer))
            ->with('customer:id,name');
        $filters->range($query, 'ordered_at');

        return array_values($query->orderByDesc('ordered_at')->orderByDesc('id')->limit($limit)->get()
            ->map(fn (Model&ShopOrder $order): array => [
                'id' => $order->getKey(),
                'title' => $order->displayNumber(),
                'subtitle' => implode(' · ', array_filter([
                    $order->customer->name ?? $order->buyerLabel(),
                    $order->getAttribute('status')?->label(),
                    $order->getAttribute('ordered_at') !== null ? CarbonFmt::fdate($order->getAttribute('ordered_at')) : null,
                ])) ?: null,
                'url' => route('admin.' . $this->pluginId() . '.index', ['q' => $order->getAttribute('order_number') ?? $order->getAttribute('remote_order_id')]),
            ])
            ->all());
    }
}
