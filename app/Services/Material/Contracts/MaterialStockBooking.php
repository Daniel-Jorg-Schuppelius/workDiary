<?php
/*
 * Created on   : Thu Nov 12 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : MaterialStockBooking.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Material\Contracts;

use App\Models\Material\MaterialUsage;
use App\Models\Platform\User;

/**
 * Bestandswirkung des Materialverbrauchs (MVP-1679, MVP-863): definiert vom
 * Material, gebunden vom Lager ({@see \App\Services\Inventory\MaterialUsageStockBooking}).
 * Null-Bindung: der Verbrauch bleibt Abrechnungs- und Nachweisposten ohne
 * Lagerbeleg. Eine Lagerregel, die die Buchung verhindert, meldet die
 * Bindung als {@see \App\Exceptions\MaterialStockException}.
 */
interface MaterialStockBooking {
    /**
     * Bringt den Bestand auf den Stand des Verbrauchs: Abgang beim ersten
     * Mal, Differenz nach einer Änderung, Rückbuchung, wenn der Verbrauch
     * nicht mehr lagergeführt ist. Wiederholt bucht es nichts doppelt.
     */
    public function sync(MaterialUsage $usage, User $actor, bool $allowNegative = false): void;

    /** Bucht alles zurück, was der Verbrauch abgebucht hat — vor dem Löschen. */
    public function release(MaterialUsage $usage, ?User $actor): void;
}
