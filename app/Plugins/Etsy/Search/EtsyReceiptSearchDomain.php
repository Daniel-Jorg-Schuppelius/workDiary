<?php
/*
 * Created on   : Fri Oct 23 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : EtsyReceiptSearchDomain.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Plugins\Etsy\Search;

use App\Models\Platform\User;
use App\Plugins\Etsy\EtsyPlugin;
use App\Plugins\Etsy\Models\EtsyReceipt;
use App\Plugins\PluginManager;
use App\Services\Search\Contracts\SearchDomain;
use App\Services\Search\Dto\SearchDomainFilters;
use App\Support\CarbonFmt;

/** Etsy-Bestellungen nach Nummer oder Käufer (Feature 181); sichtbar wie die Bestellliste, also nur für Admins. */
final class EtsyReceiptSearchDomain implements SearchDomain {
    public function __construct(private readonly PluginManager $plugins) {}

    public function key(): string {
        return 'etsy_orders';
    }

    public function label(): string {
        return (string) __('etsy::etsy.search.domain');
    }

    public function icon(): string {
        return 'storefront';
    }

    public function order(): int {
        return 37;
    }

    public function available(User $user): bool {
        return $user->isAdmin() && $this->plugins->enabled()->has(EtsyPlugin::ID);
    }

    public function search(User $user, int $organizationId, string $term, SearchDomainFilters $filters, int $limit): array {
        if ($filters->person !== null) {
            return [];
        }
        $query = EtsyReceipt::query()
            ->where('organization_id', $organizationId)
            ->matching($term)
            ->when($filters->customer !== null, fn ($q) => $q->where('customer_id', $filters->customer))
            ->with('customer:id,name');
        $filters->range($query, 'ordered_at');

        return array_values($query->orderByDesc('ordered_at')->limit($limit)->get()
            ->map(fn (EtsyReceipt $r) => [
                'id' => $r->id,
                'title' => '#' . $r->receipt_id,
                'subtitle' => implode(' · ', array_filter([
                    $r->customer->name ?? data_get($r->buyer, 'name'),
                    $r->status,
                    $r->ordered_at !== null ? CarbonFmt::fdate($r->ordered_at) : null,
                ])) ?: null,
                'url' => route('admin.etsy.index', ['q' => (string) $r->receipt_id]),
            ])
            ->all());
    }
}
