<?php
/*
 * Created on   : Wed Aug 26 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : CaseNarrativeSuggestionService.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Ai\Suggestions;

use App\Models\Ai\AiTextSuggestion;
use App\Models\{DiaryEntry, Organization, User};
use App\Services\Ai\{AiInvocationService, AiMemoryService};
use App\Services\Ai\Dto\{AiTextResult, SummarizeRequest};
use App\Services\Ai\Exceptions\AiException;
use App\Services\Ai\Suggestions\Concerns\DecidesSuggestions;
use App\Services\Ai\Support\CustomerNameMasker;
use App\Services\Timeline\{DiaryEntryTimelineService, TimelineItem};

/**
 * KI-Welle 2 — Kurznarrativ des Auftragsverlaufs (Feature 148, MVP-732;
 * Ausblick aus Feature 023): die Fallakten-Timeline ist vollständig, aber
 * lang — der Vorschlag verdichtet sie zu wenigen Sätzen „was ist passiert".
 *
 * Quelle sind AUSSCHLIESSLICH die bereits rechtegeprüften Timeline-Einträge
 * ({@see DiaryEntryTimelineService::forDiaryEntry()} mit dem Betrachter):
 * was der Nutzer nicht sehen darf, sieht auch die KI nicht. Kundennamen
 * werden vor dem Versand maskiert, die Capability ist `high` eingestuft
 * (lokal-exklusiv). Übernahme = interner Kommentar am Auftrag; die
 * Timeline selbst bleibt unberührt (sie schreibt nie).
 */
class CaseNarrativeSuggestionService {
    use DecidesSuggestions;

    public const CAPABILITY = 'case.timeline_narrative';

    /** Obergrenze der eingespeisten Ereignisse (Budget + Prompt-Länge). */
    public const MAX_EVENTS = 80;

    /** @var list<string> */
    private const RULES = [
        'Nur die aufgeführten Ereignisse wiedergeben — keine Ursachen, Bewertungen, Schuldzuweisungen oder Empfehlungen ergänzen.',
        'Chronologisch erzählen, gebündelt nach Vorgängen; Zahlen und Termine wörtlich übernehmen.',
        'Höchstens acht Sätze; offene Punkte am Ende als eigenen Satz nennen.',
        'Keine Personennamen nennen — Rollen verwenden (z. B. „die Bearbeitung").',
    ];

    public function __construct(
        private readonly AiInvocationService $invocation,
        private readonly AiMemoryService $memory,
        private readonly CustomerNameMasker $masker,
        private readonly DiaryEntryTimelineService $timeline,
    ) {}

    /** Kurznarrativ des Auftragsverlaufs für den Betrachter. */
    public function narrate(DiaryEntry $entry, User $viewer, ?int $connectionId = null): AiTextSuggestion {
        $organization = $this->organizationOf($entry);

        $timeline = $this->timeline->forDiaryEntry($entry, $viewer, null, self::MAX_EVENTS);
        /** @var list<TimelineItem> $events */
        $events = $timeline['items'];
        if ($events === []) {
            throw new AiException((string) __('ai.error.case_timeline_empty'));
        }

        $items = [];
        foreach ($events as $event) {
            $line = trim(implode(' · ', array_filter([
                $event->occurredAt?->format('d.m.Y'),
                $event->title,
                $event->summary !== null ? \CommonToolkit\Helper\Data\StringHelper::truncate($event->summary, 200) : null,
            ])));
            if ($line !== '') {
                $items[] = $this->masker->mask($organization, $line);
            }
        }

        // Ältestes zuerst: das Narrativ soll vorwärts erzählen.
        $items = array_reverse($items);

        $request = new SummarizeRequest(
            items: $items,
            language: app()->getLocale(),
            period: $entry->start_at?->format('d.m.Y'),
            styleRules: array_merge(self::RULES, $this->memory->styleRulesFor($organization, self::CAPABILITY)),
            glossary: $this->memory->glossaryFor($organization, self::CAPABILITY),
        );

        $result = $this->invocation->invoke($organization, self::CAPABILITY, $request, $connectionId);
        $payload = $result->result;
        if (! $payload instanceof AiTextResult) {
            throw new AiException((string) __('ai.error.unexpected_result_type'));
        }

        return $this->storeProposal(
            (int) $organization->id,
            $entry,
            self::CAPABILITY,
            (string) __('ai.case.source_hint', ['count' => count($items)]),
            $payload->text,
            $result,
            $viewer,
        );
    }

    /**
     * Übernahme: das (ggf. editierte) Narrativ wird als interner Kommentar
     * am Auftrag gesichert — die reguläre Kommentar-Fachlogik, kein
     * Direktschreiben in die Timeline.
     */
    public function accept(AiTextSuggestion $suggestion, User $user, ?string $editedText = null): bool {
        if (! $suggestion->isOpen()) {
            throw new AiException((string) __('ai.error.suggestion_decided'));
        }

        $entry = $suggestion->subject;
        if (! $entry instanceof DiaryEntry) {
            throw new AiException((string) __('ai.error.suggestion_subject_missing'));
        }

        $text = trim($editedText ?? (string) $suggestion->suggestion);
        if ($text === '') {
            throw new AiException((string) __('ai.error.case_narrative_empty'));
        }
        $edited = $text !== trim((string) $suggestion->suggestion);

        $entry->comments()->create([
            'user_id' => $user->getKey(),
            'body' => $text,
        ]);

        $this->markDecided($suggestion, $edited ? AiTextSuggestion::STATUS_EDITED : AiTextSuggestion::STATUS_ACCEPTED, $user);
        $this->auditDecision($suggestion, $edited ? 'edited' : 'accepted', $user);

        return $edited;
    }

    private function organizationOf(DiaryEntry $entry): Organization {
        return $entry->organization ?? Organization::query()->withoutGlobalScopes()->findOrFail($entry->organization_id);
    }
}
