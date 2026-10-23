<?php
/*
 * Created on   : Fri Oct 23 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : BillbeeOrderSearchDomain.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Plugins\Billbee\Search;

use App\Models\Platform\User;
use App\Plugins\Billbee\BillbeePlugin;
use App\Plugins\Billbee\Models\BillbeeOrder;
use App\Plugins\PluginManager;
use App\Services\Search\Contracts\SearchDomain;
use App\Services\Search\Dto\SearchDomainFilters;
use App\Support\CarbonFmt;

/** Billbee-Bestellungen nach Nummer oder Käufer (Feature 181); sichtbar wie die Bestellliste, also nur für Admins. */
final class BillbeeOrderSearchDomain implements SearchDomain {
    public function __construct(private readonly PluginManager $plugins) {}

    public function key(): string {
        return 'billbee_orders';
    }

    public function label(): string {
        return (string) __('billbee::billbee.search.domain');
    }

    public function icon(): string {
        return 'shopping_bag';
    }

    public function order(): int {
        return 37;
    }

    public function available(User $user): bool {
        return $user->isAdmin() && $this->plugins->enabled()->has(BillbeePlugin::ID);
    }

    public function search(User $user, int $organizationId, string $term, SearchDomainFilters $filters, int $limit): array {
        if ($filters->person !== null) {
            return [];
        }
        $query = BillbeeOrder::query()
            ->where('organization_id', $organizationId)
            ->matching($term)
            ->when($filters->customer !== null, fn ($q) => $q->where('customer_id', $filters->customer))
            ->with('customer:id,name');
        $filters->range($query, 'ordered_at');

        return array_values($query->orderByDesc('ordered_at')->orderByDesc('id')->limit($limit)->get()
            ->map(function (BillbeeOrder $o): array {
                $number = $o->order_number ?? $o->billbee_order_id;

                return [
                    'id' => $o->id,
                    'title' => '#' . $number,
                    'subtitle' => implode(' · ', array_filter([
                        $o->customer->name ?? data_get($o->buyer, 'FullName'),
                        $o->channel,
                        $o->ordered_at !== null ? CarbonFmt::fdate($o->ordered_at) : null,
                    ])) ?: null,
                    'url' => route('admin.billbee.index', ['q' => $number]),
                ];
            })
            ->all());
    }
}
