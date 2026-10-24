<?php
/*
 * Created on   : Sat Oct 24 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ShopOrderRetentionPolicies.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Plugins\Support\ShopOrders;

use App\Services\Retention\Contracts\RetentionPolicyProvider;
use App\Services\Retention\RetentionPolicy;
use Illuminate\Database\Eloquent\Model;

/** Käuferdaten gespiegelter Shop-Bestellungen nach der handelsrechtlichen Frist entfernen; Beträge und Kundenbezug bleiben. */
abstract class ShopOrderRetentionPolicies implements RetentionPolicyProvider {
    /** @return class-string<Model> */
    abstract protected function orderModel(): string;

    /** @return list<RetentionPolicy> */
    public function policies(): array {
        $model = $this->orderModel();

        return [
            new RetentionPolicy(
                area: 'marketplace_buyers',
                modelClass: $model,
                overdueQuery: fn ($organization, $cutoff) => $model::query()
                    ->withoutGlobalScopes()
                    ->where('organization_id', $organization->id)
                    ->where('ordered_at', '<', $cutoff)
                    ->where(static fn ($query) => $query->whereNotNull('buyer')->orWhereNotNull('buyer_external_id')),
                purge: static function (Model $subject): void {
                    $subject->forceFill(['buyer' => null, 'buyer_external_id' => null])->save();
                },
            ),
        ];
    }
}
