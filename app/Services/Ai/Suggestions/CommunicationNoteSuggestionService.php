<?php
/*
 * Created on   : Wed Aug 26 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : CommunicationNoteSuggestionService.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Ai\Suggestions;

use App\Models\Ai\AiTextSuggestion;
use App\Models\{CommunicationNote, Organization, User};
use App\Services\Ai\AiInvocationService;
use App\Services\Ai\Dto\{AiExtractionResult, ExtractRequest};
use App\Services\Ai\Exceptions\AiException;
use App\Services\Ai\Suggestions\Concerns\DecidesSuggestions;
use App\Services\Ai\Support\CustomerNameMasker;
use App\Services\Communication\CommunicationNoteService;
use Illuminate\Support\Carbon;
use Illuminate\Validation\ValidationException;

/**
 * KI-Welle 2 — Kommunikationsnotiz strukturieren (Feature 148, MVP-732):
 * aus dem mitgeschriebenen Telefonat-/Gesprächsverlauf werden Betreff,
 * Ergebnis, Folgeaktion und deren Fälligkeit als EINZELN übernehmbare Chips
 * vorgeschlagen (Feature 030, dortiger Ausblick).
 *
 * Gesprächsinhalte sind heikel: die Capability ist `high` eingestuft
 * (lokal-exklusiv, kein Cloud-Routing) und als vertraulich markierte Notizen
 * sind zusätzlich komplett gesperrt. Jede Übernahme läuft über den regulären
 * {@see CommunicationNoteService} — nie Auto-Apply.
 */
class CommunicationNoteSuggestionService {
    use DecidesSuggestions;

    public const CAPABILITY = 'communication.note_structure';

    public const FIELD_SUBJECT = 'subject';

    public const FIELD_RESULT = 'result';

    public const FIELD_NEXT_ACTION = 'next_action';

    public const FIELD_NEXT_ACTION_DUE_AT = 'next_action_due_at';

    /** Zielschema — abschließend; der Adapter liefert nur diese Felder. */
    private const SCHEMA = [
        self::FIELD_SUBJECT => 'Kurzer sachlicher Betreff des Gesprächs, höchstens 120 Zeichen.',
        self::FIELD_RESULT => 'Ergebnis bzw. Fazit des Gesprächs in einem Satz; null, wenn der Verlauf keines nennt.',
        self::FIELD_NEXT_ACTION => 'Vereinbarte Folgeaktion in einem Satz; null, wenn keine vereinbart wurde.',
        self::FIELD_NEXT_ACTION_DUE_AT => 'Fälligkeitsdatum der Folgeaktion im Format JJJJ-MM-TT; null, wenn kein Termin genannt wurde.',
    ];

    public function __construct(
        private readonly AiInvocationService $invocation,
        private readonly CustomerNameMasker $masker,
        private readonly CommunicationNoteService $notes,
    ) {}

    /**
     * Strukturvorschlag aus dem Verlauf — null, wenn die KI kein Feld mit
     * ausreichender Konfidenz füllt (dann entsteht kein Vorschlag).
     */
    public function structure(CommunicationNote $note, ?User $user, ?int $connectionId = null): ?AiTextSuggestion {
        $this->assertUsable($note);

        $organization = $this->organizationOf($note);
        $body = trim((string) $note->body);
        if ($body === '') {
            throw new AiException((string) __('ai.error.communication_note_no_text'));
        }

        $request = new ExtractRequest(
            text: $this->masker->mask($organization, $body),
            schema: self::SCHEMA,
            language: app()->getLocale(),
        );

        $result = $this->invocation->invoke($organization, self::CAPABILITY, $request, $connectionId);
        $payload = $result->result;
        if (! $payload instanceof AiExtractionResult) {
            throw new AiException((string) __('ai.error.unexpected_extraction_type'));
        }

        $entries = [];
        foreach (array_keys(self::SCHEMA) as $field) {
            $value = $payload->confidentValue($field);
            if ($value === null) {
                continue;
            }
            if ($field === self::FIELD_NEXT_ACTION_DUE_AT) {
                $value = self::normalizeDate($value);
                if ($value === null) {
                    continue;
                }
            }
            $entries[] = ['field' => $field, 'value' => $value];
        }

        if ($entries === []) {
            return null;
        }

        return $this->storeProposal(
            (int) $organization->id,
            $note,
            self::CAPABILITY,
            $body,
            (string) json_encode($entries, JSON_UNESCAPED_UNICODE),
            $result,
            $user,
        );
    }

    /**
     * Einen Chip übernehmen — die Übernahme läuft über den regulären
     * Notiz-Service (Konsistenzprüfung, Auditable-Diff). Verbleibende Chips
     * bleiben offen; der letzte schließt den Vorschlag.
     */
    public function applyValue(AiTextSuggestion $suggestion, User $user, string $field): void {
        $note = $this->openNoteOf($suggestion);
        $this->assertUsable($note);

        $entries = self::structuredValues($suggestion);
        $match = null;
        foreach ($entries as $entry) {
            if ($entry['field'] === $field) {
                $match = $entry;
                break;
            }
        }
        if ($match === null) {
            throw new AiException((string) __('ai.error.classification_value_unknown'));
        }

        try {
            $this->notes->update($note, $user, [$field => $match['value']]);
        } catch (ValidationException $e) {
            throw new AiException(implode(' • ', \Illuminate\Support\Arr::flatten($e->errors())), 0, $e);
        }

        $remaining = array_values(array_filter($entries, static fn (array $e): bool => $e['field'] !== $field));
        if ($remaining === []) {
            $this->markDecided($suggestion, AiTextSuggestion::STATUS_ACCEPTED, $user);
        } else {
            $suggestion->forceFill(['suggestion' => (string) json_encode($remaining, JSON_UNESCAPED_UNICODE)])->save();
        }

        $this->auditDecision($suggestion, 'accepted', $user, ['field' => $field]);
    }

    /**
     * Chips eines Strukturvorschlags.
     *
     * @return list<array{field: string, value: string}>
     */
    public static function structuredValues(AiTextSuggestion $suggestion): array {
        $decoded = json_decode((string) $suggestion->suggestion, true);
        if (! is_array($decoded)) {
            return [];
        }

        $entries = [];
        foreach ($decoded as $row) {
            if (is_array($row) && isset($row['field'], $row['value']) && isset(self::SCHEMA[(string) $row['field']])) {
                $entries[] = ['field' => (string) $row['field'], 'value' => (string) $row['value']];
            }
        }

        return $entries;
    }

    /** Vertrauliche Notizen und gelöschte Notizen sind gesperrt. */
    private function assertUsable(CommunicationNote $note): void {
        if ($note->confidential) {
            throw new AiException((string) __('ai.error.communication_note_confidential'));
        }
        if ($note->trashed()) {
            throw new AiException((string) __('ai.error.suggestion_subject_missing'));
        }
    }

    private function openNoteOf(AiTextSuggestion $suggestion): CommunicationNote {
        if (! $suggestion->isOpen()) {
            throw new AiException((string) __('ai.error.suggestion_decided'));
        }

        $note = $suggestion->subject;
        if (! $note instanceof CommunicationNote) {
            throw new AiException((string) __('ai.error.suggestion_subject_missing'));
        }

        return $note;
    }

    private function organizationOf(CommunicationNote $note): Organization {
        return $note->organization ?? Organization::query()->withoutGlobalScopes()->findOrFail($note->organization_id);
    }

    /** Nur echte ISO-Datumsangaben werden zum Chip — nie geraten. */
    private static function normalizeDate(string $value): ?string {
        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', trim($value)) !== 1) {
            return null;
        }

        try {
            return Carbon::createFromFormat('Y-m-d', trim($value))?->toDateString();
        } catch (\Throwable) {
            return null;
        }
    }
}
