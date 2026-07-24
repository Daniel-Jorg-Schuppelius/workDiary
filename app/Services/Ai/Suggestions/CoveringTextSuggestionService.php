<?php
/*
 * Created on   : Sun Jul 20 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : CoveringTextSuggestionService.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Ai\Suggestions;

use App\Models\{Invoice, Organization};
use App\Services\Ai\{AiInvocationService, AiMemoryService};
use App\Services\Ai\Dto\{AiTextResult, AiTranslationResult, FormulateRequest, TranslateRequest};
use CommonToolkit\Helper\Data\NumberHelper;

/**
 * Begleittext-Entwürfe für die Versand-/Mahn-Dialoge und die
 * Portal-Antwort-Übersetzung (Feature 084, MVP-405-/Phase-36-Rest):
 * E-Mail-Begleittext beim Rechnungsversand, Formulierung der
 * Zahlungserinnerung/Mahnung, Übersetzung einer Rückfragen-Antwort. Synchron und flüchtig —
 * der Text landet ausschließlich als Entwurf im Dialogfeld (nie
 * Auto-Versand, keine Suggestion-Persistenz: der Nutzer editiert/verwirft
 * direkt im Feld). In den Prompt gehen nur Belegmetadaten — nie der
 * Empfängername (DoD Feature 084); Stil/Glossar kommen aus dem
 * KI-Gedächtnis (MVP-404) mit Kundenkontext.
 */
class CoveringTextSuggestionService {
    public const CAPABILITY_MAIL_TEXT = 'invoicing.mail_text';

    public const CAPABILITY_DUNNING_TEXT = 'invoicing.dunning_text';

    public const CAPABILITY_ANSWER_TRANSLATE = 'portal.answer_translate';

    public function __construct(
        private readonly AiInvocationService $invocation,
        private readonly AiMemoryService $memory,
    ) {}

    /**
     * Entwurf des E-Mail-Begleittexts zum Rechnungsversand.
     *
     * @throws \App\Services\Ai\Exceptions\AiException
     */
    public function suggestMailText(Invoice $invoice): string {
        $organization = $this->organizationOf($invoice);

        $facts = [
            'Rechnung ' . $invoice->number,
            'Betrag ' . NumberHelper::toGermanFormat((float) $invoice->total, 2, withThousandsSeparator: true) . ' ' . $invoice->currency->value,
        ];
        if ($invoice->due_on !== null) {
            $facts[] = 'zahlbar bis ' . $invoice->due_on->format('d.m.Y');
        }

        return $this->formulate(
            $organization,
            self::CAPABILITY_MAIL_TEXT,
            (int) $invoice->customer_id,
            'Kurzer, freundlicher E-Mail-Begleittext zum Versand einer Rechnung: ' . implode(', ', $facts) . '. Die Rechnung liegt als PDF bei.',
            ['E-Mail-Begleittext', 'Rechnungsversand'],
        );
    }

    /**
     * Entwurf der Zahlungserinnerung (Stufe 1) bzw. Mahnung (Stufe 2/3).
     *
     * @throws \App\Services\Ai\Exceptions\AiException
     */
    public function suggestDunningText(Invoice $invoice, int $level): string {
        $organization = $this->organizationOf($invoice);

        $kind = $level <= 1 ? 'freundliche Zahlungserinnerung' : $level . '. Mahnung, bestimmter Ton';
        $facts = [
            'Rechnung ' . $invoice->number,
            'offener Betrag ' . NumberHelper::toGermanFormat((float) $invoice->total, 2, withThousandsSeparator: true) . ' ' . $invoice->currency->value,
        ];
        if ($invoice->due_on !== null) {
            $facts[] = 'fällig seit ' . $invoice->due_on->format('d.m.Y');
        }

        return $this->formulate(
            $organization,
            self::CAPABILITY_DUNNING_TEXT,
            (int) $invoice->customer_id,
            ucfirst($kind) . ' zu: ' . implode(', ', $facts) . '. Um zeitnahe Zahlung bitten; sachlich bleiben.',
            ['Mahntext', 'Mahnstufe ' . $level],
        );
    }

    /** @param list<string> $contextHints */
    private function formulate(Organization $organization, string $capability, int $customerId, string $text, array $contextHints): string {
        $request = new FormulateRequest(
            text: $text,
            styleRules: $this->memory->styleRulesFor($organization, $capability, $customerId),
            glossary: $this->memory->glossaryFor($organization, $capability, $customerId),
            examples: $this->memory->examplesFor($organization, $capability, $customerId),
            contextHints: $contextHints,
        );

        $result = $this->invocation->invoke($organization, $capability, $request)->result;

        return $result instanceof AiTextResult ? trim($result->text) : '';
    }

    /**
     * Übersetzt eine Portal-Antwort (Kundenrückfrage, Feature 012) in die
     * Zielsprache — Vorschau-Entwurf, der Nutzer prüft und sendet erneut.
     *
     * @throws \App\Services\Ai\Exceptions\AiException
     */
    public function translatePortalAnswer(Organization $organization, ?int $customerId, string $text, string $targetLanguage): string {
        $request = new TranslateRequest(
            text: $text,
            targetLanguage: $targetLanguage,
            sourceLanguage: 'de',
            formality: 'more',
            glossary: $this->memory->glossaryFor($organization, self::CAPABILITY_ANSWER_TRANSLATE, $customerId, $targetLanguage),
        );

        $result = $this->invocation->invoke($organization, self::CAPABILITY_ANSWER_TRANSLATE, $request)->result;

        return $result instanceof AiTranslationResult ? trim($result->text) : '';
    }

    private function organizationOf(Invoice $invoice): Organization {
        return $invoice->organization ?? Organization::query()->withoutGlobalScopes()->findOrFail($invoice->organization_id);
    }
}
