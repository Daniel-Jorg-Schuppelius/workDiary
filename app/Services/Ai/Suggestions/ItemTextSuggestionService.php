<?php
/*
 * Created on   : Thu Jul 16 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ItemTextSuggestionService.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Ai\Suggestions;

use App\Models\Ai\AiTextSuggestion;
use App\Models\{AuditLog, Invoice, InvoiceItem, Organization, Quote, QuoteItem, TimeEntry, User};
use App\Models\Finance\{BillingTransfer, BillingTransferPosition};
use App\Services\Ai\{AiInvocationService, AiMemoryService};
use App\Services\Ai\Contracts\AiRequestInterface;
use App\Services\Ai\Dto\{AiInvocationResult, AiTextResult, AiTranslationResult, FormulateRequest, SummarizeRequest, TranslateRequest};
use App\Services\Ai\Exceptions\AiException;
use App\Services\Invoicing\TextCorrectionService;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * KI-Leistungstexte (Feature 084, MVP-402–405): erzeugt Vorschläge für
 * Rechnungs-/Angebotspositionen — Einzeltext (Formulieren), Blocktext
 * aus gebündelten Zeiten (Zusammenfassen) und Übersetzung. Nur im
 * Entwurfsstadium; KI schreibt nie still — erst accept() ändert die
 * Position. Gedächtnis-Kontext (Kundenglossar, Stilprofil,
 * Beispielpaare) fließt in jeden Aufruf ein (MVP-404).
 */
class ItemTextSuggestionService {
    public const CAPABILITY_ITEM = 'invoicing.item_text';

    public const CAPABILITY_BLOCK = 'invoicing.block_text';

    public const CAPABILITY_TRANSLATE = 'invoicing.item_translate';

    public const CAPABILITY_QUOTE_ITEM = 'quotes.item_text';

    public function __construct(
        private readonly AiInvocationService $invocation,
        private readonly AiMemoryService $memory,
        private readonly TextCorrectionService $corrections,
    ) {}

    /** Einzel- oder Blocktext für eine Rechnungsposition im Entwurf. */
    public function suggestForInvoiceItem(Invoice $invoice, InvoiceItem $item, ?User $user, ?int $connectionId = null): AiTextSuggestion {
        $this->assertInvoiceDraft($invoice);

        $organization = $invoice->organization ?? Organization::query()->findOrFail($invoice->organization_id);
        [$capability, $request] = $this->buildForInvoiceItem($organization, $invoice, $item);

        return $this->invokeAndStore($organization, $capability, $request, $item, (string) $item->description, $user, $connectionId);
    }

    /**
     * Sammelaktion (MVP-403): asynchron über den idempotenten Queue-Job —
     * je Position ein Job, Ergebnisse erscheinen als offene Vorschläge.
     * Terminale Fehler (Capability aus, Budget) bleiben ohne Vorschlag,
     * der Beleg-Workflow läuft unverändert weiter.
     */
    public function queueAllForInvoice(Invoice $invoice, ?User $user): int {
        $this->assertInvoiceDraft($invoice);

        $organization = $invoice->organization ?? Organization::query()->findOrFail($invoice->organization_id);
        $count = 0;

        foreach ($invoice->items as $item) {
            [$capability, $request] = $this->buildForInvoiceItem($organization, $invoice, $item);

            \App\Jobs\Ai\AiInvocationJob::dispatch(
                (int) $organization->id,
                $capability,
                $request,
                StoreItemSuggestionHandler::class,
                [
                    'organization_id' => (int) $organization->id,
                    'subject_type' => $item->getMorphClass(),
                    'subject_id' => (int) $item->getKey(),
                    'original' => (string) $item->description,
                    'user_id' => $user?->getKey(),
                ],
            );
            $count++;
        }

        return $count;
    }

    /** @return array{0: string, 1: AiRequestInterface} */
    private function buildForInvoiceItem(Organization $organization, Invoice $invoice, InvoiceItem $item): array {
        $customerId = (int) $invoice->customer_id;

        $entryTexts = $item->timeEntries()
            ->pluck('description')
            ->filter(static fn (?string $d): bool => filled($d))
            // Wörterbuch-Vorreinigung: bekannte Schreibfehler deterministisch
            // beheben, bevor die KI formuliert (stabilere Cache-Keys).
            ->map(fn (?string $d): string => (string) $this->corrections->apply((string) $d, (int) $organization->id))
            ->values();

        if ($entryTexts->count() > 1) {
            // MVP-403: Blocktext — die Einzelbeschreibungen der gebündelten
            // Zeiten sind die Quelle, nicht der bisherige Sammeltext.
            return [self::CAPABILITY_BLOCK, new SummarizeRequest(
                items: array_values($entryTexts->map(fn(string $t): string => $this->maskCustomerNames($organization, $t))->all()),
                period: $item->service_date?->format('d.m.Y'),
                styleRules: $this->memory->styleRulesFor($organization, self::CAPABILITY_BLOCK, $customerId),
                glossary: $this->memory->glossaryFor($organization, self::CAPABILITY_BLOCK, $customerId),
            )];
        }

        return [self::CAPABILITY_ITEM, new FormulateRequest(
            text: $this->maskCustomerNames($organization, (string) ($entryTexts->first() ?? $item->description)),
            styleRules: $this->memory->styleRulesFor($organization, self::CAPABILITY_ITEM, $customerId),
            glossary: $this->memory->glossaryFor($organization, self::CAPABILITY_ITEM, $customerId),
            examples: $this->memory->examplesFor($organization, self::CAPABILITY_ITEM, $customerId),
            contextHints: array_filter([
                $item->service_date !== null ? 'Leistungsdatum: ' . $item->service_date->format('d.m.Y') : null,
            ]),
        )];
    }

    /** Übersetzung einer Rechnungsposition in die Zielsprache (MVP-409). */
    public function translateInvoiceItem(Invoice $invoice, InvoiceItem $item, string $targetLanguage, ?User $user, ?int $connectionId = null): AiTextSuggestion {
        $this->assertInvoiceDraft($invoice);

        $organization = $invoice->organization ?? Organization::query()->findOrFail($invoice->organization_id);
        $customerId = (int) $invoice->customer_id;

        $request = new TranslateRequest(
            text: $this->maskCustomerNames($organization, (string) $item->description),
            targetLanguage: $targetLanguage,
            sourceLanguage: 'de',
            formality: 'more',
            glossary: $this->memory->glossaryFor($organization, self::CAPABILITY_TRANSLATE, $customerId, $targetLanguage),
        );

        return $this->invokeAndStore($organization, self::CAPABILITY_TRANSLATE, $request, $item, (string) $item->description, $user, $connectionId);
    }

    /**
     * Position einer Faktura-Übergabe (MVP-488) — nur solange die Übergabe
     * bestätigt und noch nicht übertragen ist. Quelle des Vorschlags sind die
     * Beschreibungen der gebündelten Zeiteinträge (Blocktext bei mehreren),
     * nicht der bereits zusammengesetzte Positionstext; Standardtext der
     * Leistung und Leistungsdatum kommen beim Übernehmen wieder davor bzw.
     * dahinter.
     */
    public function suggestForTransferPosition(BillingTransfer $transfer, BillingTransferPosition $position, ?User $user, ?int $connectionId = null): AiTextSuggestion {
        $this->assertTransferOpen($transfer);

        $organization = $transfer->organization ?? Organization::query()->findOrFail($transfer->organization_id);
        $customerId = (int) $transfer->customer_id;

        $entryTexts = TimeEntry::query()
            ->whereIn('id', is_array($position->source_ids) ? $position->source_ids : [])
            ->pluck('description')
            ->filter(static fn (?string $d): bool => filled($d))
            // Wörterbuch-Vorreinigung wie in buildForInvoiceItem().
            ->map(fn (?string $d): string => (string) $this->corrections->apply((string) $d, (int) $organization->id))
            ->values();

        $period = $position->service_from?->format('d.m.Y');

        if ($entryTexts->count() > 1) {
            $request = new SummarizeRequest(
                items: array_values($entryTexts->map(fn(string $t): string => $this->maskCustomerNames($organization, $t))->all()),
                period: $period,
                styleRules: $this->memory->styleRulesFor($organization, self::CAPABILITY_BLOCK, $customerId),
                glossary: $this->memory->glossaryFor($organization, self::CAPABILITY_BLOCK, $customerId),
            );

            return $this->invokeAndStore($organization, self::CAPABILITY_BLOCK, $request, $position, (string) $position->description, $user, $connectionId);
        }

        $request = new FormulateRequest(
            text: $this->maskCustomerNames($organization, (string) ($entryTexts->first() ?? $position->description)),
            styleRules: $this->memory->styleRulesFor($organization, self::CAPABILITY_ITEM, $customerId),
            glossary: $this->memory->glossaryFor($organization, self::CAPABILITY_ITEM, $customerId),
            examples: $this->memory->examplesFor($organization, self::CAPABILITY_ITEM, $customerId),
            contextHints: array_filter([
                $period !== null ? 'Leistungsdatum: ' . $period : null,
            ]),
        );

        return $this->invokeAndStore($organization, self::CAPABILITY_ITEM, $request, $position, (string) $position->description, $user, $connectionId);
    }

    /** Angebotsposition (MVP-405) — nur im Entwurf. */
    public function suggestForQuoteItem(Quote $quote, QuoteItem $item, ?User $user, ?int $connectionId = null): AiTextSuggestion {
        if ($quote->status !== 'draft') {
            throw new AiException((string) __('ai.error.only_quote_draft'));
        }

        $organization = $quote->organization ?? Organization::query()->findOrFail($quote->organization_id);
        $customerId = (int) $quote->customer_id;

        $request = new FormulateRequest(
            text: $this->maskCustomerNames($organization, (string) $item->description),
            styleRules: $this->memory->styleRulesFor($organization, self::CAPABILITY_QUOTE_ITEM, $customerId),
            glossary: $this->memory->glossaryFor($organization, self::CAPABILITY_QUOTE_ITEM, $customerId),
            examples: $this->memory->examplesFor($organization, self::CAPABILITY_QUOTE_ITEM, $customerId),
        );

        return $this->invokeAndStore($organization, self::CAPABILITY_QUOTE_ITEM, $request, $item, (string) $item->description, $user, $connectionId);
    }

    /**
     * Übernahme (MVP-402): schreibt den (ggf. editierten) Text in die
     * Position, kennzeichnet sie als KI-unterstützt und auditiert ohne
     * Klartext. Liefert true, wenn der Nutzer den Vorschlag vor der
     * Übernahme verändert hat (→ „Merken?"-Dialog, MVP-404).
     */
    public function accept(AiTextSuggestion $suggestion, ?User $user, ?string $editedText = null): bool {
        if (! $suggestion->isOpen()) {
            throw new AiException((string) __('ai.error.suggestion_decided'));
        }

        $subject = $suggestion->subject;
        if ($subject instanceof InvoiceItem) {
            $this->assertInvoiceDraft($subject->invoice);
        } elseif ($subject instanceof QuoteItem && $subject->quote?->status !== 'draft') {
            throw new AiException((string) __('ai.error.position_not_draft'));
        } elseif ($subject instanceof BillingTransferPosition) {
            $this->assertTransferOpen($subject->transfer);
        }

        $text = trim($editedText ?? $suggestion->suggestion);
        $edited = $text !== trim($suggestion->suggestion);

        if (! $subject instanceof Model) {
            throw new AiException((string) __('ai.error.suggestion_subject_missing'));
        }

        $subject->forceFill([
            'description' => $text,
            'ai_assisted_at' => Carbon::now(),
        ])->save();

        $suggestion->forceFill([
            'status' => $edited ? AiTextSuggestion::STATUS_EDITED : AiTextSuggestion::STATUS_ACCEPTED,
            'decided_by_user_id' => $user?->getKey(),
            'decided_at' => Carbon::now(),
        ])->save();

        $this->auditDecision($suggestion, $edited ? 'edited' : 'accepted', $user);

        return $edited;
    }

    public function reject(AiTextSuggestion $suggestion, ?User $user): void {
        if (! $suggestion->isOpen()) {
            return; // idempotent
        }

        $suggestion->forceFill([
            'status' => AiTextSuggestion::STATUS_REJECTED,
            'decided_by_user_id' => $user?->getKey(),
            'decided_at' => Carbon::now(),
        ])->save();

        $this->auditDecision($suggestion, 'rejected', $user);
    }

    /**
     * Offene Vorschläge eines Belegs verfallen bei Ausstellung/Versand.
     *
     * @param list<int> $subjectIds
     */
    public function expireForSubjects(string $subjectType, array $subjectIds): void {
        if ($subjectIds === []) {
            return;
        }

        AiTextSuggestion::query()
            ->withoutGlobalScopes()
            ->where('subject_type', $subjectType)
            ->whereIn('subject_id', $subjectIds)
            ->where('status', AiTextSuggestion::STATUS_PROPOSED)
            ->update(['status' => AiTextSuggestion::STATUS_EXPIRED]);
    }

    /**
     * Datenschutz-Vorfilter (Feature 084, Vollaudit 2026-07 M35): maskiert
     * Namen aus dem eigenen Kundenstamm im Prompttext, BEVOR der Text einen
     * (ggf. Cloud-)Provider erreicht — die DoD verlangt, dass Vorschläge nie
     * den Empfängernamen enthalten. Namen unter 4 Zeichen bleiben unberührt
     * (False-Positive-Schutz); Obergrenze 5000 Namen je Organisation.
     */
    private function maskCustomerNames(Organization $organization, string $text): string {
        if (trim($text) === '') {
            return $text;
        }

        $names = \App\Models\Customer::query()
            ->withoutGlobalScopes()
            ->where('organization_id', $organization->id)
            ->orderBy('id')
            ->limit(5000)
            ->pluck('name')
            ->map(static fn($n): string => trim((string) $n))
            ->filter(static fn(string $n): bool => mb_strlen($n) >= 4)
            ->sortByDesc(static fn(string $n): int => mb_strlen($n))
            ->values();

        foreach ($names->chunk(200) as $chunk) {
            $pattern = '/(?:' . $chunk->map(static fn(string $n): string => preg_quote($n, '/'))->implode('|') . ')/iu';
            $text = preg_replace($pattern, '[Kunde]', $text) ?? $text;
        }

        return $text;
    }

    private function invokeAndStore(
        Organization $organization,
        string $capability,
        AiRequestInterface $request,
        Model $subject,
        string $original,
        ?User $user,
        ?int $connectionId,
    ): AiTextSuggestion {
        $result = $this->invocation->invoke($organization, $capability, $request, $connectionId);

        return $this->storeSuggestion(
            (int) $organization->id,
            $subject->getMorphClass(),
            (int) $subject->getKey(),
            $original,
            $result,
            $user?->getKey() !== null ? (int) $user->getKey() : null,
        );
    }

    /**
     * Ergebnis als offenen Vorschlag persistieren (idempotent: der neue
     * ersetzt einen bestehenden offenen Vorschlag derselben Position) —
     * auch vom asynchronen {@see StoreItemSuggestionHandler} genutzt.
     */
    public function storeSuggestion(
        int $organizationId,
        string $subjectType,
        int $subjectId,
        string $original,
        AiInvocationResult $result,
        ?int $userId,
    ): AiTextSuggestion {
        $text = $this->textFrom($result);

        AiTextSuggestion::query()
            ->withoutGlobalScopes()
            ->where('organization_id', $organizationId)
            ->where('subject_type', $subjectType)
            ->where('subject_id', $subjectId)
            ->where('status', AiTextSuggestion::STATUS_PROPOSED)
            ->delete();

        return AiTextSuggestion::query()->create([
            'organization_id' => $organizationId,
            'subject_type' => $subjectType,
            'subject_id' => $subjectId,
            'capability' => $result->capability,
            'original' => $original,
            'suggestion' => $text,
            'status' => AiTextSuggestion::STATUS_PROPOSED,
            'connection_id' => $result->connectionId,
            'provider' => $result->provider->value,
            'fallback_used' => $result->fallbackUsed,
            'from_cache' => $result->fromCache,
            'created_by_user_id' => $userId,
        ]);
    }

    private function textFrom(AiInvocationResult $result): string {
        $payload = $result->result;

        return match (true) {
            $payload instanceof AiTextResult => $payload->text,
            $payload instanceof AiTranslationResult => $payload->text,
            default => throw new AiException((string) __('ai.error.unexpected_result_type')),
        };
    }

    private function assertInvoiceDraft(?Invoice $invoice): void {
        if ($invoice === null || $invoice->status !== Invoice::STATUS_DRAFT) {
            throw new AiException((string) __('ai.error.only_invoice_draft'));
        }
    }

    /** Übergabe-Positionen sind nur zwischen Bestätigen und Übertragen offen. */
    private function assertTransferOpen(?BillingTransfer $transfer): void {
        if ($transfer === null || $transfer->status !== \App\Enums\Finance\TransferStatus::Confirmed) {
            throw new AiException((string) __('ai.error.only_transfer_confirmed'));
        }
    }

    private function auditDecision(AiTextSuggestion $suggestion, string $decision, ?User $user): void {
        AuditLog::create([
            'organization_id' => $suggestion->organization_id,
            'user_id' => $user?->getKey(),
            'event' => 'ai.suggestion_decided',
            'auditable_type' => $suggestion->getMorphClass(),
            'auditable_id' => $suggestion->getKey(),
            'changes' => [
                'decision' => $decision,
                'capability' => $suggestion->capability,
                'provider' => $suggestion->provider,
                'subject_type' => $suggestion->subject_type,
                'subject_id' => $suggestion->subject_id,
            ],
        ]);
    }
}
