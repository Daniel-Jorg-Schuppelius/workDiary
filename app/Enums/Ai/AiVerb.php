<?php
/*
 * Created on   : Thu Jul 16 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : AiVerb.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Enums\Ai;

use App\Enums\Concerns\HasOptions;
use App\Enums\Contracts\HasLabel;

/**
 * Die Capability-Verben des KI-Fundaments (Feature 025, MVP-398; Extrahieren
 * ergänzt mit Feature 088). Jede KI-Einsatzstelle registriert sich auf genau
 * ein Verb; der Familien-Vertrag bildet die Verben als getrennte Methoden
 * ab — es gibt bewusst keinen generischen Prompt-Aufruf. Übersetzen darf
 * sowohl auf die Übersetzungs- als auch auf die LLM-Familie geroutet werden
 * (Prompt-Glossar, nicht deterministisch); alle anderen Verben sind
 * LLM-exklusiv.
 */
enum AiVerb: string implements HasLabel {
    use HasOptions;

    case Formulate = 'formulate';
    case Summarize = 'summarize';
    case Classify = 'classify';
    case Explain = 'explain';
    case Find = 'find';
    case Translate = 'translate';
    case Extract = 'extract';

    public function label(): string {
        return (string) __('enums.ai.verb.' . $this->value);
    }

    /**
     * Provider-Familien, die dieses Verb bedienen dürfen (Reihenfolge ohne
     * Bedeutung — das Routing entscheidet über die konkrete Verbindung).
     *
     * @return list<AiFamily>
     */
    public function allowedFamilies(): array {
        return $this === self::Translate
            ? [AiFamily::Translation, AiFamily::Llm]
            : [AiFamily::Llm];
    }
}
