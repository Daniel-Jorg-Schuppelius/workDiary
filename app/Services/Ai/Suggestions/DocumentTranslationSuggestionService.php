<?php
/*
 * Created on   : Wed Aug 26 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : DocumentTranslationSuggestionService.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Ai\Suggestions;

use App\Models\Ai\AiTextSuggestion;
use App\Models\{Organization, Quote, QuoteItem, User};
use App\Services\Ai\{AiInvocationService, AiMemoryService};
use App\Services\Ai\Dto\{AiTranslationResult, TranslateRequest};
use App\Services\Ai\Exceptions\AiException;
use App\Services\Ai\Suggestions\Concerns\DecidesSuggestions;
use App\Services\Ai\Support\CustomerNameMasker;
use App\Support\{DocumentLocale, Locales};
use Illuminate\Support\Carbon;

/**
 * KI-Welle 2 — Belegtexte in die Belegsprache (Feature 148, MVP-732):
 * Angebots-/AB-/Lieferschein-Positionen und Begleittexte werden NICHT in
 * eine frei gewählte Sprache übersetzt, sondern in die für den Kunden
 * hinterlegte Belegsprache ({@see DocumentLocale}, Feature 034/MVP-721) —
 * dieselbe Sprache, in der der Beleg gerendert und versendet wird.
 *
 * Abgrenzung zu `invoicing.item_translate`: dort wählt der Nutzer die
 * Zielsprache im Dialog (Ad-hoc-Übersetzung einer Rechnungsposition), hier
 * ist sie durch den Kundenstamm bestimmt. Übersetzungsprotokolle liegen im
 * php-translation-toolkit; app-seitig bleiben nur die Geschäftsregeln
 * (Maskierung, Gedächtnis-Glossar, Entwurfs-Sperre, nie Auto-Apply).
 */
class DocumentTranslationSuggestionService {
    use DecidesSuggestions;

    public const CAPABILITY = 'documents.item_translate';

    public function __construct(
        private readonly AiInvocationService $invocation,
        private readonly AiMemoryService $memory,
        private readonly CustomerNameMasker $masker,
    ) {}

    /** Positionstext eines Angebots-/AB-Entwurfs in die Belegsprache. */
    public function translateQuoteItem(Quote $quote, QuoteItem $item, ?User $user, ?int $connectionId = null): AiTextSuggestion {
        $organization = $this->draftOrganizationOf($quote);
        $source = trim((string) $item->description);
        if ($source === '') {
            throw new AiException((string) __('ai.error.document_text_missing'));
        }

        return $this->translate($organization, $quote, $item, $source, $user, $connectionId);
    }

    /** Begleittext (Bedingungen/Schlusstext) eines Angebots in die Belegsprache. */
    public function translateQuoteTerms(Quote $quote, ?User $user, ?int $connectionId = null): AiTextSuggestion {
        $organization = $this->draftOrganizationOf($quote);
        $source = trim((string) $quote->terms);
        if ($source === '') {
            throw new AiException((string) __('ai.error.document_text_missing'));
        }

        return $this->translate($organization, $quote, $quote, $source, $user, $connectionId);
    }

    /**
     * Übernahme: schreibt den (ggf. editierten) Text in Position bzw.
     * Begleittext — nur solange der Beleg im Entwurf ist. Liefert true,
     * wenn der Nutzer den Vorschlag vorher verändert hat („Merken?").
     */
    public function accept(AiTextSuggestion $suggestion, ?User $user, ?string $editedText = null): bool {
        if (! $suggestion->isOpen()) {
            throw new AiException((string) __('ai.error.suggestion_decided'));
        }

        $subject = $suggestion->subject;
        $text = trim($editedText ?? (string) $suggestion->suggestion);
        $edited = $text !== trim((string) $suggestion->suggestion);

        if ($subject instanceof QuoteItem) {
            $quote = $subject->quote;
            if (! $quote instanceof Quote || $quote->status !== 'draft') {
                throw new AiException((string) __('ai.error.only_quote_draft'));
            }
            $subject->forceFill(['description' => $text, 'ai_assisted_at' => Carbon::now()])->save();
        } elseif ($subject instanceof Quote) {
            if ($subject->status !== 'draft') {
                throw new AiException((string) __('ai.error.only_quote_draft'));
            }
            $subject->forceFill(['terms' => $text])->save();
        } else {
            throw new AiException((string) __('ai.error.suggestion_subject_missing'));
        }

        $this->markDecided($suggestion, $edited ? AiTextSuggestion::STATUS_EDITED : AiTextSuggestion::STATUS_ACCEPTED, $user);
        $this->auditDecision($suggestion, $edited ? 'edited' : 'accepted', $user);

        return $edited;
    }

    /**
     * Belegsprache des Angebots (Kunde → Organisation → Anzeigesprache).
     * Steht sie auf der Quellsprache, gibt es nichts zu übersetzen.
     */
    public static function targetLanguageFor(Quote $quote): string {
        return DocumentLocale::for($quote->customer, $quote->organization);
    }

    /** Quellsprache = Sprache der Organisation (Belege entstehen in ihr). */
    public static function sourceLanguageFor(Organization $organization): string {
        $locale = (string) $organization->locale;

        return Locales::isSupported($locale) ? $locale : config('app.locale');
    }

    /** Lohnt die Übersetzung? (Belegsprache ≠ Sprache der Organisation) */
    public static function isTranslatable(Quote $quote): bool {
        $organization = $quote->organization;

        return $organization === null
            || self::targetLanguageFor($quote) !== self::sourceLanguageFor($organization);
    }

    private function translate(
        Organization $organization,
        Quote $quote,
        \Illuminate\Database\Eloquent\Model $subject,
        string $source,
        ?User $user,
        ?int $connectionId,
    ): AiTextSuggestion {
        $target = self::targetLanguageFor($quote);
        $sourceLanguage = self::sourceLanguageFor($organization);
        if ($target === $sourceLanguage) {
            throw new AiException((string) __('ai.error.document_locale_same', [
                'language' => Locales::native($target),
            ]));
        }

        $customerId = (int) $quote->customer_id;

        $request = new TranslateRequest(
            text: $this->masker->mask($organization, $source),
            targetLanguage: $target,
            sourceLanguage: $sourceLanguage,
            formality: 'more',
            glossary: $this->memory->glossaryFor($organization, self::CAPABILITY, $customerId, $target),
        );

        $result = $this->invocation->invoke($organization, self::CAPABILITY, $request, $connectionId);
        $payload = $result->result;
        if (! $payload instanceof AiTranslationResult) {
            throw new AiException((string) __('ai.error.unexpected_result_type'));
        }

        return $this->storeProposal((int) $organization->id, $subject, self::CAPABILITY, $source, $payload->text, $result, $user);
    }

    private function draftOrganizationOf(Quote $quote): Organization {
        if ($quote->status !== 'draft') {
            throw new AiException((string) __('ai.error.only_quote_draft'));
        }

        return $quote->organization ?? Organization::query()->findOrFail($quote->organization_id);
    }
}
