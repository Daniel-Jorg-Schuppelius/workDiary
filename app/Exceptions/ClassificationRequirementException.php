<?php
/*
 * Created on   : Tue Sep 16 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ClassificationRequirementException.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Exceptions;

use App\Enums\Classification\{ClassificationDomain, ClassificationRequirementPhase};
use App\Services\Classification\RequirementResult;
use RuntimeException;

/**
 * Nicht erfüllte Pflichtklassifikation (Feature 024, Vollscan 2026-09-15
 * `P12-30` / MVP-795). Bis hierher waren Pflichtklassifikationen zwar
 * pflegbar und wurden als Hinweis angezeigt, aber nirgends durchgesetzt:
 * der Prüfer hatte ausserhalb der Hinweisanzeige keinen einzigen Aufrufer.
 *
 * Eigene Klasse statt `ClassificationValidationException` — jene ist die
 * Ausnahme der Stammdatenpflege und wird nur dort gefangen; hier geht es um
 * einen Fachzustand am Auftrag (Architektur-Gate „Exception-Klassen statt
 * Meldungstexte").
 */
class ClassificationRequirementException extends RuntimeException {
    /**
     * @param  list<string>  $messages  Je Lücke eine fertige Meldung.
     * @param  list<string>  $domains   Betroffene Domänen (Enum-Werte).
     */
    public function __construct(
        public readonly ClassificationRequirementPhase $phase,
        private readonly array $messages,
        public readonly array $domains,
        string $message,
    ) {
        parent::__construct($message);
    }

    /**
     * @param  list<RequirementResult>  $results  Nur blockierende Ergebnisse.
     */
    public static function unmet(ClassificationRequirementPhase $phase, array $results): self {
        $messages = [];
        $domains = [];
        $labels = [];

        foreach ($results as $result) {
            $label = ClassificationDomain::tryFrom($result->requiredDomain)?->label() ?? $result->requiredDomain;
            $domains[] = $result->requiredDomain;
            $labels[] = $label;
            $messages[] = $result->actualCount < $result->minCount
                ? (string) __('classification.error.requirementMin', [
                    'domain' => $label,
                    'min' => $result->minCount,
                    'actual' => $result->actualCount,
                ])
                : (string) __('classification.error.requirementMax', [
                    'domain' => $label,
                    'max' => (int) $result->maxCount,
                    'actual' => $result->actualCount,
                ]);
        }

        return new self($phase, $messages, array_values(array_unique($domains)), (string) __(
            'classification.error.requirementUnmet',
            ['domains' => implode(', ', array_unique($labels))],
        ));
    }

    /** @return list<string> */
    public function messages(): array {
        return $this->messages;
    }
}
