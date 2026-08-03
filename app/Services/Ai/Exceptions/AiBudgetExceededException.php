<?php
/*
 * Created on   : Thu Jul 16 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : AiBudgetExceededException.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Ai\Exceptions;

use App\Enums\Ai\AiFamily;

/**
 * Monatsbudget der Organisation ist erschöpft (MVP-399) — terminal für
 * diesen Monat, kein Provider-Aufruf, kein Queue-Retry.
 */
class AiBudgetExceededException extends AiException {
    public static function forFamily(AiFamily $family, int $limit): self {
        return new self((string) __('ai.error.budget_exceeded', [
            'family' => $family->value,
            'limit' => $limit,
        ]));
    }
}
