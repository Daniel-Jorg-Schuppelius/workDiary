<?php
/*
 * Created on   : Sat Jul 18 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : AssertsIsmsTransition.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Isms\Concerns;

use App\Enums\Contracts\HasStatusTransitions;
use Illuminate\Validation\ValidationException;

/**
 * Gemeinsamer Statusübergangs-Guard der ISMS-Services (Vollaudit 2026-07,
 * M44): identische Kopie aus 8 Services extrahiert — ValidationException mit
 * isms.error.invalid_transition (Formular-Feedback). Die RuntimeException-
 * Familie (AssetFinance/Contract/Claims) bleibt bewusst getrennt
 * ({@see \App\Services\Concerns\AssertsStatusTransition}).
 */
trait AssertsIsmsTransition {
    /** @throws ValidationException wenn der Übergang nicht erlaubt ist. */
    private function assertIsmsTransition(HasStatusTransitions $current, HasStatusTransitions $target): void {
        if (in_array($target, $current->allowedTransitions(), true)) {
            return;
        }

        throw ValidationException::withMessages([
            'status' => __('isms.error.invalid_transition', [
                'from' => $current->label(),
                'to' => $target->label(),
            ]),
        ]);
    }
}
