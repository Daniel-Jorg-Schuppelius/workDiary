<?php
/*
 * Created on   : Sat Jul 18 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : AssertsStatusTransition.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Concerns;

use App\Enums\Contracts\HasStatusTransitions;
use RuntimeException;

/**
 * Statusübergangs-Guard mit RuntimeException-Semantik (Vollaudit 2026-07,
 * M44): wörtlich identische Kopien aus AssetFinanceService/ContractService
 * (+ Claims-Variante) extrahiert. Bewusst getrennt von der ISMS-Familie
 * ({@see \App\Services\Isms\Concerns\AssertsIsmsTransition}), die
 * ValidationException für Formular-Feedback wirft.
 */
trait AssertsStatusTransition {
    /** @throws RuntimeException wenn der Übergang nicht erlaubt ist. */
    private function assertStatusTransition(HasStatusTransitions $current, HasStatusTransitions $target): void {
        if (in_array($target, $current->allowedTransitions(), true)) {
            return;
        }

        throw new RuntimeException((string) __('Statuswechsel von :from nach :to ist nicht zulässig.', [
            'from' => $current->label(),
            'to' => $target->label(),
        ]));
    }
}
