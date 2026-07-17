<?php
/*
 * Created on   : Thu Jul 16 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : Completion.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Ai\Providers;

use App\Services\Ai\Dto\AiUsage;

/**
 * Interner Rückgabewert eines LLM-Aufrufs (MVP-407): Rohtext + Verbrauch.
 */
final class Completion {
    public function __construct(
        public readonly string $text,
        public readonly AiUsage $usage,
    ) {}
}
