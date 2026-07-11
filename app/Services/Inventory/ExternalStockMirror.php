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

use App\Enums\Inventory\{InventoryMode, StockMovementType, StockState};
use App\Models\{Organization, StockMovement};

/**
 * Spiegelt lokal gebuchte Bewegungen in die externe Bestands-Outbox (Feature 048,
 * MVP-072 / Feature 078, MVP-321), wenn die Organisation extern führt. Bei
 * lokaler UND read-only-Führung passiert nichts — read_only liest nur, es wird
 * nie ins Fremdsystem gebucht.
 *
 * {@see mirrorMovement()} ist der zentrale Einhängepunkt aus
 * {@see InventoryLedger::post()} und filtert, was extern relevant ist:
 * nur physische Bestandsdeltas; Reservierungen verwaltet das Fremdsystem
 * selbst; Kompensations- (`compensate:`) und Übernahme-Buchungen
 * (`takeover:`) dürfen NIE zurückgespiegelt werden (das Fremdsystem hat die
 * Ursprungsbuchung nie bzw. bereits erhalten — Rückspiegelung wäre eine
 * Doppel-/Geisterbuchung).
 */
class ExternalStockMirror {
    public function __construct(
        private readonly InventoryProviderResolver $resolver,
        private readonly InventoryOutboxService $outbox,
    ) {}

    /** Zentraler Einhängepunkt: entscheidet selbst, ob die Bewegung extern relevant ist. */
    public function mirrorMovement(StockMovement $movement): void {
        $key = (string) ($movement->idempotency_key ?? '');
        if (str_starts_with($key, 'compensate:') || str_starts_with($key, 'takeover:')) {
            return;
        }

        if (in_array($movement->movement_type, [StockMovementType::Reserve, StockMovementType::ReleaseReservation], true)) {
            return;
        }

        if ($movement->stock_state !== StockState::Physical) {
            return;
        }

        $organization = $movement->organization;
        if (! $organization instanceof Organization) {
            return;
        }

        $this->mirror($movement, $organization);
    }

    public function mirror(StockMovement $movement, Organization $organization): void {
        if ($this->resolver->modeFor($organization) !== InventoryMode::External) {
            return;
        }

        $pluginId = data_get($organization->settings, 'inventory_plugin_id');
        $this->outbox->enqueueForMovement($movement, is_string($pluginId) ? $pluginId : null);
    }
}
