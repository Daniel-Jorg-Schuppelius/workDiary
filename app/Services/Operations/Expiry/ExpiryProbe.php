<?php
/*
 * Created on   : Wed Jul 08 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ExpiryProbe.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Operations\Expiry;

use App\Services\Operations\OperationsSignal;

/**
 * Erweiterungspunkt des ExpiryScanners (Feature 041, MVP-057):
 * Konnektoren liefern Ablauf-/Störungssignale (z. B. Connection-Health
 * aus 067-P4), der Scanner übernimmt Dedupe und Auto-Resolve.
 */
interface ExpiryProbe {
    /** @return list<OperationsSignal> */
    public function signals(): array;
}
