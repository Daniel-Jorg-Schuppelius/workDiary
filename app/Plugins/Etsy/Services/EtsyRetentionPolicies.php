<?php
/*
 * Created on   : Wed Oct 21 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : EtsyRetentionPolicies.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Plugins\Etsy\Services;

use App\Plugins\Etsy\Models\EtsyReceipt;
use App\Services\Retention\Contracts\RetentionPolicyProvider;
use App\Services\Retention\RetentionPolicy;

/**
 * Käuferdaten gespiegelter Etsy-Bestellungen (Vollscan 2026-09-15, P8-41):
 * nach der Frist werden Name, Anschrift und Rohdaten entfernt; Betrag, Datum
 * und Bezug zum Kunden bleiben für Auswertung und Abgleich.
 */
final class EtsyRetentionPolicies implements RetentionPolicyProvider {
    /** @return list<RetentionPolicy> */
    public function policies(): array {
        return [
            new RetentionPolicy(
                area: 'marketplace_buyers',
                modelClass: EtsyReceipt::class,
                overdueQuery: fn ($organization, $cutoff) => EtsyReceipt::query()
                    ->withoutGlobalScopes()
                    ->where('organization_id', $organization->id)
                    ->where('ordered_at', '<', $cutoff)
                    ->where(static fn ($query) => $query->whereNotNull('buyer')->orWhereNotNull('raw')->orWhereNotNull('buyer_external_id')),
                purge: static function (EtsyReceipt $subject): void {
                    $subject->forceFill(['buyer' => null, 'buyer_external_id' => null, 'raw' => null])->save();
                },
            ),
        ];
    }
}
