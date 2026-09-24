<?php
/*
 * Created on   : Thu Sep 24 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : RmaStockHandler.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Claims\Contracts;

use App\Enums\Claims\ClaimRmaDisposition;
use App\Models\Claims\ClaimRmaReturn;
use App\Models\Customer\Customer;
use App\Models\Platform\User;

/**
 * Bestandswirkung von Reklamationsrückläufern (MVP-863): definiert von den
 * Reklamationen, gebunden vom Lager ({@see \App\Services\Inventory\Claims\InventoryRmaStockHandler}).
 * Null-Bindung: keine Lagerbuchung, keine Seriennummernprüfung — der Fall
 * dokumentiert nur.
 */
interface RmaStockHandler {
    /** Wurde die Seriennummer je an den Fall-Kunden geliefert? */
    public function wasShippedTo(int $organizationId, string $serialNo, Customer $customer): bool;

    /** Wareneingang: Menge idempotent in den Quarantäne-Zustand buchen, Seriennummer als Rückläufer markieren. */
    public function bookReturn(ClaimRmaReturn $rma, string $state, User $actor): void;

    /** Bestandswirkung der Verwendungsentscheidung als idempotente Ledger-Buchungen. */
    public function applyDisposition(ClaimRmaReturn $rma, ClaimRmaDisposition $disposition, User $actor): void;
}
