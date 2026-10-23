<?php
/*
 * Created on   : Fri Oct 23 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : PurchaseOrderSearchDomain.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Procurement\Search;

use App\Enums\User\Permission;
use App\Models\Platform\User;
use App\Models\Procurement\PurchaseOrder;
use App\Services\Licensing\FeatureFlagResolver;
use App\Services\Search\Contracts\SearchDomain;
use App\Services\Search\Dto\SearchDomainFilters;
use Illuminate\Support\Facades\Gate;

/** Bestellungen nach Nummer und Lieferant; Leserecht wie die Bestellliste (Lager sehen oder buchen). */
final class PurchaseOrderSearchDomain implements SearchDomain {
    public function __construct(private readonly FeatureFlagResolver $features) {}

    public function key(): string {
        return 'purchase_orders';
    }

    public function label(): string {
        return (string) __('search.domain.purchase_orders');
    }

    public function icon(): string {
        return 'shopping_cart';
    }

    public function order(): int {
        return 38;
    }

    public function available(User $user): bool {
        return $this->features->routeEnabled('purchase-orders.show') && (Gate::forUser($user)->allows(Permission::InventoryViewAny->value) || Gate::forUser($user)->allows(Permission::InventoryPost->value));
    }

    public function search(User $user, int $organizationId, string $term, SearchDomainFilters $filters, int $limit): array {
        if ($filters->person !== null || $filters->customer !== null) {
            return [];
        }
        $query = PurchaseOrder::query()
            ->where('organization_id', $organizationId)
            ->where(fn ($q) => $q->whereLikeEscaped('number', $term)
                ->orWhereHas('supplier', fn ($s) => $s->whereLikeEscaped('name', $term)))
            ->with('supplier:id,name');
        $filters->range($query, 'created_at');

        return array_values($query->orderByDesc('created_at')->limit($limit)->get()
            ->map(fn (PurchaseOrder $o) => [
                'id' => $o->id,
                'title' => (string) ($o->number ?: '#' . $o->id),
                'subtitle' => implode(' · ', array_filter([$o->supplier?->name, $o->status->label()])) ?: null,
                'url' => route('purchase-orders.show', $o),
            ])
            ->all());
    }
}
