<?php
/*
 * Created on   : Sat Jul 18 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : HasTransitions.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Enums\Concerns;

/**
 * Gemeinsame Statusmaschinen-Mechanik für Status-Enums (Vollaudit 2026-07,
 * N30): das Enum definiert NUR noch allowedTransitions(); canTransitionTo()
 * kommt aus diesem Concern. Fehlersemantik (throw/abort) bleibt bewusst an
 * der Aufrufstelle bzw. in den Service-Traits
 * ({@see \App\Services\Concerns\AssertsStatusTransition},
 * {@see \App\Services\Isms\Concerns\AssertsIsmsTransition}).
 */
trait HasTransitions {
    /**
     * Erlaubte Folgezustände des aktuellen Status.
     *
     * @return list<self>
     */
    abstract public function allowedTransitions(): array;

    public function canTransitionTo(self $target): bool {
        return in_array($target, $this->allowedTransitions(), true);
    }
}
