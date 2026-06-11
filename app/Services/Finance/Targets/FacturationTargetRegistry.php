<?php
/*
 * Created on   : Wed Jun 10 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : FacturationTargetRegistry.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Services\Finance\Targets;

use App\Enums\Finance\TransferTarget;
use RuntimeException;

/**
 * Löst den passenden Ziel-Adapter für ein {@see TransferTarget} auf.
 * Reihenfolge = Priorität; der erste Adapter mit supports() gewinnt.
 * `datev` läuft bewusst über den {@see FileTarget} (Übergabepaket als CSV),
 * bis der DATEV-Desktop-API-Adapter existiert (eigenes Inkrement).
 */
class FacturationTargetRegistry {
    /** @var list<FacturationTarget> */
    private readonly array $targets;

    public function __construct(LexofficeTarget $lexoffice, FileTarget $file) {
        $this->targets = [$lexoffice, $file];
    }

    public function for(TransferTarget $target): FacturationTarget {
        foreach ($this->targets as $adapter) {
            if ($adapter->supports($target)) {
                return $adapter;
            }
        }

        throw new RuntimeException('No facturation target adapter for: ' . $target->value);
    }
}
