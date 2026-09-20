<?php
/*
 * Created on   : Wed Aug 26 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : DocumentMetadataSuggestionService.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Ai\Suggestions;

use App\Enums\Document\{DocumentTextFailure, DocumentType};
use App\Exceptions\DocumentTextUnavailableException;
use App\Models\Ai\AiTextSuggestion;
use App\Models\{Document, Organization, User};
use App\Services\Ai\AiInvocationService;
use App\Services\Ai\Dto\{AiExtractionResult, ExtractRequest};
use App\Services\Ai\Exceptions\AiException;
use App\Services\Ai\Suggestions\Concerns\DecidesSuggestions;
use App\Services\Ai\Support\CustomerNameMasker;
use App\Services\Document\{DocumentService, DocumentTextExtractor};
use CommonToolkit\Helper\Data\JsonHelper;
use Illuminate\Support\{Carbon, Str};
use Throwable;

/**
 * KI-Welle 3 — DMS: Dokumenttyp erkennen, Metadaten und Fristen extrahieren
 * (Feature 148, MVP-732; Feature 031). Den Text der aktuellen Version liefert
 * {@see DocumentTextExtractor} — seit MVP-819 braucht ihn auch der
 * Tätigkeitsindex, und die Formatweiche samt Fehlergründen ist keine Fassade
 * um einen Einzelaufruf, sondern Logik, die sonst zweimal dastünde. Die
 * Toolkit-Aufrufe selbst stehen dort weiterhin direkt.
 *
 * Bewusst EIN Aufruf statt Classify + Extract: der Dokumenttyp ist ein Feld
 * des abschließenden Zielschemas mit fester Werteliste, das Rückmapping auf
 * {@see DocumentType} verwirft alles Unbekannte (Katalog-Garantie). Ergebnis
 * sind Chips, die EINZELN über den regulären {@see DocumentService}
 * übernommen werden — nie Auto-Apply.
 */
class DocumentMetadataSuggestionService {
    use DecidesSuggestions;

    public const CAPABILITY = 'dms.classify_extract';

    public const FIELD_TYPE = 'document_type';

    public const FIELD_TITLE = 'title';

    public const FIELD_VALID_FROM = 'valid_from';

    public const FIELD_VALID_UNTIL = 'valid_until';

    /** Zeichen-Obergrenze des Dokumenttexts im Prompt (Budget + Kontextfenster). */
    public const MAX_TEXT_CHARACTERS = 12000;

    public function __construct(
        private readonly AiInvocationService $invocation,
        private readonly CustomerNameMasker $masker,
        private readonly DocumentService $documents,
        private readonly DocumentTextExtractor $textExtractor,
    ) {}

    /**
     * Analysiert die aktuelle Version — null, wenn kein Feld mit
     * ausreichender Konfidenz zurückkommt (dann entsteht kein Vorschlag).
     */
    public function analyze(Document $document, ?User $user, ?int $connectionId = null): ?AiTextSuggestion {
        $organization = $this->organizationOf($document);
        $text = $this->documentText($document);

        $request = new ExtractRequest(
            text: $this->masker->mask($organization, Str::limit($text, self::MAX_TEXT_CHARACTERS, '')),
            schema: self::schema(),
            language: app()->getLocale(),
        );

        $result = $this->invocation->invoke($organization, self::CAPABILITY, $request, $connectionId);
        $payload = $result->result;
        if (! $payload instanceof AiExtractionResult) {
            throw new AiException((string) __('ai.error.unexpected_extraction_type'));
        }

        $entries = [];

        $type = self::mapDocumentType($payload->confidentValue(self::FIELD_TYPE));
        if ($type !== null && $type !== $document->document_type) {
            $entries[] = ['field' => self::FIELD_TYPE, 'value' => $type->value, 'label' => $type->label()];
        }

        $title = $payload->confidentValue(self::FIELD_TITLE);
        if ($title !== null && trim($title) !== trim((string) $document->title)) {
            $entries[] = ['field' => self::FIELD_TITLE, 'value' => Str::limit($title, 120, ''), 'label' => Str::limit($title, 120, '')];
        }

        foreach ([self::FIELD_VALID_FROM, self::FIELD_VALID_UNTIL] as $field) {
            $date = self::normalizeDate($payload->confidentValue($field));
            if ($date !== null) {
                $entries[] = ['field' => $field, 'value' => $date, 'label' => Carbon::parse($date)->format('d.m.Y')];
            }
        }

        if ($entries === []) {
            return null;
        }

        return $this->storeProposal(
            (int) $organization->id,
            $document,
            self::CAPABILITY,
            (string) __('ai.dms.source_hint', ['name' => (string) ($document->currentVersion->original_name ?? $document->title)]),
            JsonHelper::encode($entries, JSON_UNESCAPED_UNICODE),
            $result,
            $user,
        );
    }

    /**
     * Einen Chip übernehmen — über den regulären Dokument-Service (Auditable-
     * Diff, Gültigkeitsprüfung). Verbleibende Chips bleiben offen.
     */
    public function applyValue(AiTextSuggestion $suggestion, User $user, string $field): void {
        $document = $this->openDocumentOf($suggestion);

        $entries = self::extractedValues($suggestion);
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
            $this->documents->update($document, $user, [$field => $match['value']]);
        } catch (\Illuminate\Validation\ValidationException $e) {
            throw new AiException(implode(' • ', \Illuminate\Support\Arr::flatten($e->errors())), 0, $e);
        }

        $remaining = array_values(array_filter($entries, static fn (array $e): bool => $e['field'] !== $field));
        if ($remaining === []) {
            $this->markDecided($suggestion, AiTextSuggestion::STATUS_ACCEPTED, $user);
        } else {
            $suggestion->forceFill(['suggestion' => JsonHelper::encode($remaining, JSON_UNESCAPED_UNICODE)])->save();
        }

        $this->auditDecision($suggestion, 'accepted', $user, ['field' => $field]);
    }

    /**
     * Chips eines Metadaten-Vorschlags.
     *
     * @return list<array{field: string, value: string, label: string}>
     */
    public static function extractedValues(AiTextSuggestion $suggestion): array {
        $decoded = json_decode((string) $suggestion->suggestion, true);
        if (! is_array($decoded)) {
            return [];
        }

        $allowed = [self::FIELD_TYPE, self::FIELD_TITLE, self::FIELD_VALID_FROM, self::FIELD_VALID_UNTIL];
        $entries = [];
        foreach ($decoded as $row) {
            if (is_array($row) && isset($row['field'], $row['value'], $row['label']) && in_array((string) $row['field'], $allowed, true)) {
                $entries[] = ['field' => (string) $row['field'], 'value' => (string) $row['value'], 'label' => (string) $row['label']];
            }
        }

        return $entries;
    }

    /**
     * Zielschema — abschließend. Die Werteliste des Dokumenttyps steht im
     * Feldtext, damit der Adapter direkt Codes liefert (Structured Output).
     *
     * @return array<string, string>
     */
    public static function schema(): array {
        $codes = implode(', ', array_map(static fn (DocumentType $t): string => $t->value . ' (' . $t->label() . ')', DocumentType::cases()));

        return [
            self::FIELD_TYPE => 'Dokumentart als Code, ausschließlich einer von: ' . $codes . '. Null, wenn keiner sicher passt.',
            self::FIELD_TITLE => 'Sprechender Titel des Dokuments, höchstens 120 Zeichen; null, wenn keiner erkennbar ist.',
            self::FIELD_VALID_FROM => 'Beginn der Gültigkeit im Format JJJJ-MM-TT; null, wenn nicht genannt.',
            self::FIELD_VALID_UNTIL => 'Ende der Gültigkeit bzw. Frist/Ablaufdatum im Format JJJJ-MM-TT; null, wenn nicht genannt.',
        ];
    }

    /** Rückmapping auf den Katalog — Code oder Label, sonst verwerfen. */
    public static function mapDocumentType(?string $value): ?DocumentType {
        if ($value === null || trim($value) === '') {
            return null;
        }

        $needle = mb_strtolower(trim($value));
        foreach (DocumentType::cases() as $case) {
            if (mb_strtolower($case->value) === $needle || mb_strtolower($case->label()) === $needle) {
                return $case;
            }
        }

        return null;
    }

    /**
     * Text der aktuellen Version über den gemeinsamen Extractor (MVP-819);
     * dessen Fehlergrund wird hier zur Meldung für die Person.
     */
    private function documentText(Document $document): string {
        $version = $document->currentVersion;
        if ($version === null) {
            throw new AiException((string) __(DocumentTextFailure::VersionMissing->aiMessageKey()));
        }

        try {
            return $this->textExtractor->extract($version);
        } catch (DocumentTextUnavailableException $e) {
            throw new AiException((string) __($e->reason->aiMessageKey()), 0, $e);
        }
    }

    private function openDocumentOf(AiTextSuggestion $suggestion): Document {
        if (! $suggestion->isOpen()) {
            throw new AiException((string) __('ai.error.suggestion_decided'));
        }

        $document = $suggestion->subject;
        if (! $document instanceof Document) {
            throw new AiException((string) __('ai.error.suggestion_subject_missing'));
        }

        return $document;
    }

    private function organizationOf(Document $document): Organization {
        return $document->organization ?? Organization::query()->withoutGlobalScopes()->findOrFail($document->organization_id);
    }

    /** Nur echte ISO-Datumsangaben werden zum Chip — nie geraten. */
    private static function normalizeDate(?string $value): ?string {
        if ($value === null || preg_match('/^\d{4}-\d{2}-\d{2}$/', trim($value)) !== 1) {
            return null;
        }

        try {
            return Carbon::createFromFormat('Y-m-d', trim($value))?->toDateString();
        } catch (Throwable) {
            return null;
        }
    }
}
