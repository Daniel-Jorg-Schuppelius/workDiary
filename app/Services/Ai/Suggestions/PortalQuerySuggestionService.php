<?php
/*
 * Created on   : Wed Aug 26 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : PortalQuerySuggestionService.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Ai\Suggestions;

use App\Models\Ai\AiTextSuggestion;
use App\Models\{CustomerQuery, Organization, User};
use App\Services\Ai\{AiInvocationService, AiMemoryService};
use App\Services\Ai\Dto\{AiTextResult, SummarizeRequest};
use App\Services\Ai\Exceptions\AiException;
use App\Services\Ai\Suggestions\Concerns\DecidesSuggestions;
use App\Services\Ai\Support\CustomerNameMasker;
use App\Services\CustomerPortal\PortalQuerySubjects;

/**
 * KI-Welle 2 — fremdsprachige Portal-Rückfragen verstehen (Feature 148,
 * MVP-732): der Kunde schreibt in seiner Sprache, der Bearbeiter liest in
 * seiner. EIN Zusammenfassungs-Aufruf liefert Übersetzung UND Kurzfassung
 * in der Anzeigesprache des Bearbeiters (das Verb `summarize` trägt die
 * Zielsprache bereits im Vertrag — kein zweiter Übersetzungs-Aufruf).
 *
 * Das Ergebnis ist eine LESEHILFE: es ändert nichts am Vorgang, wird nie
 * automatisch zur Antwort und ist ausdrücklich kein Antwort-Entwurf (das
 * bleibt `portal.answer_translate`, Feature 084). Entsprechend kennt der
 * Vorschlag nur „verwerfen" — es gibt kein Schreibziel, das eine Übernahme
 * rechtfertigen würde.
 */
class PortalQuerySuggestionService {
    use DecidesSuggestions;

    public const CAPABILITY = 'portal.query_understand';

    /** @var list<string> */
    private const RULES = [
        'Zuerst die Rückfrage vollständig in die Zielsprache übertragen, dann in höchstens drei Sätzen zusammenfassen.',
        'Nur wiedergeben, was der Kunde gefragt hat — keine Antwort formulieren, keine Zusagen, keine Empfehlungen.',
        'Genannte Fristen, Termine, Mengen und Beanstandungen ausdrücklich benennen.',
        'Unklare oder mehrdeutige Stellen als unklar kennzeichnen statt sie zu deuten.',
    ];

    public function __construct(
        private readonly AiInvocationService $invocation,
        private readonly AiMemoryService $memory,
        private readonly CustomerNameMasker $masker,
        private readonly PortalQuerySubjects $subjects,
    ) {}

    /** Übersetzte Kurzfassung der Rückfrage in der Sprache des Bearbeiters. */
    public function understand(CustomerQuery $query, ?User $user, ?int $connectionId = null): AiTextSuggestion {
        $organization = $this->organizationOf($query);
        $question = trim((string) $query->question);
        if ($question === '') {
            throw new AiException((string) __('ai.error.portal_query_no_text'));
        }

        $items = [$this->masker->mask($organization, $question)];
        $subject = $query->subject;
        if ($subject !== null) {
            // Vorgangsbezeichnung als Kontextzeile — maskiert, damit kein
            // Kundenname aus dem eigenen Stamm den Provider erreicht.
            $items[] = 'Vorgang: ' . $this->masker->mask($organization, $this->subjects->label($subject));
        }

        $request = new SummarizeRequest(
            items: $items,
            language: app()->getLocale(),
            styleRules: array_merge(self::RULES, $this->memory->styleRulesFor($organization, self::CAPABILITY)),
            glossary: $this->memory->glossaryFor($organization, self::CAPABILITY),
        );

        $result = $this->invocation->invoke($organization, self::CAPABILITY, $request, $connectionId);
        $payload = $result->result;
        if (! $payload instanceof AiTextResult) {
            throw new AiException((string) __('ai.error.unexpected_result_type'));
        }

        return $this->storeProposal((int) $organization->id, $query, self::CAPABILITY, $question, $payload->text, $result, $user);
    }

    private function organizationOf(CustomerQuery $query): Organization {
        return Organization::query()->withoutGlobalScopes()->findOrFail($query->organization_id);
    }
}
