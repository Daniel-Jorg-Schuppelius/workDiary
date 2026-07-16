<?php
/*
 * Created on   : Thu Jul 16 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : AiFamily.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Enums\Ai;

use App\Enums\Concerns\HasOptions;
use App\Enums\Contracts\HasLabel;

/**
 * Provider-Familien des KI-Fundaments (Feature 025, MVP-398): LLM-Dienste
 * (Verben Formulieren/Zusammenfassen/Klassifizieren/Erklären/Finden, plus
 * Übersetzen per Prompt) und dedizierte Übersetzungsdienste mit
 * deterministischer Glossar-Erzwingung. Budget-Einheiten unterscheiden
 * sich je Familie (Token vs. Zeichen).
 */
enum AiFamily: string implements HasLabel {
    use HasOptions;

    case Llm = 'llm';
    case Translation = 'translation';

    public function label(): string {
        return (string) __('enums.ai.family.' . $this->value);
    }
}
