<?php
/*
 * Created on   : Fri Jun 19 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ExternalStockMirror.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Inventory;

use App\Enums\Inventory\InventoryMode;
use App\Models\{Organization, StockMovement};

/**
 * Spiegelt lokal gebuchte Bewegungen in die externe Bestands-Outbox (Feature 048,
 * MVP-072), wenn die Organisation extern führt. Bei lokaler Führung passiert
 * nichts. Verbindet damit den Standard-Buchungspfad mit der Outbox; die externe
 * Bestätigung läuft asynchron und idempotent.
 */
class ExternalStockMirror {
    public function __construct(
        private readonly InventoryProviderResolver $resolver,
        private readonly InventoryOutboxService $outbox,
    ) {}

    public function mirror(StockMovement $movement, Organization $organization): void {
        if ($this->resolver->modeFor($organization) === InventoryMode::Local) {
            return;
        }

        $pluginId = data_get($organization->settings, 'inventory_plugin_id');
        $this->outbox->enqueueForMovement($movement, is_string($pluginId) ? $pluginId : null);
    }
}
