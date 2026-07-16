<?php
/*
 * Created on   : Thu Jul 16 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : AiUsage.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Ai\Dto;

use App\Enums\Ai\AiFamily;

/**
 * Verbrauchsangabe eines Provider-Aufrufs (MVP-399): LLM-Familien
 * rechnen in Token, Übersetzungs-Familien in Zeichen. Fließt in das
 * Monatsbudget der Organisation ({@see \App\Services\Ai\AiBudgetService}).
 */
final class AiUsage {
    public function __construct(
        public readonly int $inputTokens = 0,
        public readonly int $outputTokens = 0,
        public readonly int $characters = 0,
    ) {}

    public function unitsFor(AiFamily $family): int {
        return $family === AiFamily::Translation
            ? $this->characters
            : $this->inputTokens + $this->outputTokens;
    }
}
