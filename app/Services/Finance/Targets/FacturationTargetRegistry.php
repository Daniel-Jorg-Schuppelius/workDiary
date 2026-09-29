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
    /** @var list<class-string<FacturationTarget>> */
    private array $classes = [FileTarget::class];

    /** @var list<FacturationTarget>|null */
    private ?array $targets = null;

    /**
     * Anbieterziele tragen sich beim Booten ihres Plugins ein (MVP-1032);
     * der Datei-Export (`FileTarget`) gehört dem Kern.
     *
     * @param  class-string<FacturationTarget>  $target
     */
    public function register(string $target): void {
        array_unshift($this->classes, $target);
        $this->targets = null;
    }

    public function for(TransferTarget $target): FacturationTarget {
        $this->targets ??= array_map(static fn (string $class): FacturationTarget => app($class), $this->classes);
        foreach ($this->targets as $adapter) {
            if ($adapter->supports($target)) {
                return $adapter;
            }
        }

        throw new RuntimeException('No facturation target adapter for: ' . $target->value);
    }
}
