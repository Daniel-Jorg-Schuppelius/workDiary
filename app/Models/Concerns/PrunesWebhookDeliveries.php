<?php
/*
 * Created on   : Sun Aug 23 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : PrunesWebhookDeliveries.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Models\Concerns;

use Illuminate\Database\Eloquent\{Builder, MassPrunable};

/**
 * Aufbewahrung der Webhook-Zustellprotokolle (Vollscan 2026-08-23, J9): Die
 * Tabellen dienen der Dublettenerkennung (delivery_hash/delivery_id) und
 * wuchsen unbegrenzt. Nach `integration.delivery_retention_days` (Default 90,
 * weit über jedem Redelivery-Fenster der Anbieter) räumt `model:prune` sie ab.
 * Spalte: `received_at` (alle Zustelltabellen tragen sie).
 */
trait PrunesWebhookDeliveries {
    use MassPrunable;

    /** @return Builder<static> */
    public function prunable(): Builder {
        $days = max(1, (int) config('integration.delivery_retention_days', 90));

        return static::query()->where('received_at', '<', now()->subDays($days));
    }
}
