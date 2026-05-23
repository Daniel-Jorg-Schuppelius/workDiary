<?php

/*
 * Created on   : Sat May 23 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : AssetStatusMachine.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Services\Asset;

use App\Enums\Asset\AssetStatus;
use App\Exceptions\AssetValidationException;

class AssetStatusMachine {
    /** @var array<string, list<string>> */
    private const TRANSITIONS = [
        'active' => ['inMaintenance', 'inRepair', 'blocked', 'reserved', 'loanOut', 'replaced', 'decommissioned', 'lost'],
        'inMaintenance' => ['active', 'inRepair', 'blocked', 'decommissioned'],
        'inRepair' => ['active', 'blocked', 'decommissioned', 'replaced'],
        'blocked' => ['active', 'inRepair', 'decommissioned', 'replaced'],
        'reserved' => ['active', 'loanOut', 'blocked', 'decommissioned'],
        'loanOut' => ['active', 'blocked', 'lost', 'decommissioned'],
        'replaced' => ['decommissioned'],
        'decommissioned' => [],
        'lost' => ['active', 'decommissioned'],
    ];

    public function canTransition(AssetStatus $from, AssetStatus $to): bool {
        if ($from === $to) {
            return true;
        }

        return in_array($to->value, self::TRANSITIONS[$from->value], true);
    }

    public function ensureTransition(AssetStatus $from, AssetStatus $to): void {
        if (! $this->canTransition($from, $to)) {
            throw AssetValidationException::invalidStatusTransition($from->value, $to->value);
        }
    }
}
