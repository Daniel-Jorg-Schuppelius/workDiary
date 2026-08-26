<?php
/*
 * Created on   : Tue Aug 25 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ProtocolTextSuggestionService.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Ai\Suggestions;

use App\Enums\Classification\ClassificationDomain;
use App\Enums\OpenIssue\OpenIssueSeverity;
use App\Enums\Protocol\{ProtocolItemResult, ProtocolItemType};
use App\Exceptions\{InvalidProtocolTransitionException, ProtocolValidationException};
use App\Models\Ai\AiTextSuggestion;
use App\Models\{Asset, Customer, DiaryEntry, Organization, Project, Protocol, ProtocolItem, User};
use App\Services\Ai\{AiInvocationService, AiMemoryService};
use App\Services\Ai\Dto\{AiClassificationResult, AiTextResult, ClassifyRequest, FormulateRequest};
use App\Services\Ai\Exceptions\AiException;
use App\Services\Ai\Suggestions\Concerns\DecidesSuggestions;
use App\Services\Ai\Support\CustomerNameMasker;
use App\Services\Classification\ClassificationResolver;
use App\Services\Protocol\ProtocolService;

/**
 * KI-Welle 1 für Protokolle (Feature 143, MVP-711): veredelt den Mangel-/
 * Zustandsfreitext eines Protokollpunkts (nur umformulieren — keine neuen
 * Fakten) und schlägt Schweregrad/Kategorie/Ergebnis aus dem Katalog vor.
 * Nur in bearbeitbaren Protokollen; die Übernahme läuft IMMER über die
 * reguläre Punkt-Erfassung des {@see ProtocolService} (Freeze-/Signatur-
 * Regeln, Validierung, Ereignisprotokoll) — die KI schreibt nie still.
 */
class ProtocolTextSuggestionService {
    use DecidesSuggestions;

    public const CAPABILITY_TEXT = 'protocols.item_text';

    public const CAPABILITY_CLASSIFY = 'protocols.item_classify';

    public const KIND_SEVERITY = 'severity';

    public const KIND_CATEGORY = 'category';

    public const KIND_RESULT = 'result';

    /**
     * Prompt-Grundsatz (Feature 143): Umformulieren ist erlaubt, Erfinden
     * nicht — der Text ist kundensichtbar und Teil eines Nachweises.
     *
     * @var list<string>
     */
    private const TEXT_RULES = [
        'Nur umformulieren: keine neuen Fakten, Mengen, Ursachen, Bewertungen oder Empfehlungen ergänzen.',
        'Sachlich, neutral und kundensichtbar formulieren; Fachbegriffe beibehalten.',
        'Unklare Angaben unverändert lassen statt zu interpretieren.',
    ];

    public function __construct(
        private readonly AiInvocationService $invocation,
        private readonly AiMemoryService $memory,
        private readonly CustomerNameMasker $masker,
        private readonly ProtocolService $protocols,
        private readonly ClassificationResolver $classifications,
    ) {}

    /** Veredelter Freitext des Punkts (Mangel-Beschreibung bzw. Beschreibung). */
    public function suggestForItem(ProtocolItem $item, ?User $user, ?int $connectionId = null): AiTextSuggestion {
        $protocol = $this->editableProtocolOf($item);
        $organization = $this->organizationOf($protocol);
        $source = $this->requireSourceText($item);
        $customerId = $this->customerIdOf($protocol);

        $request = new FormulateRequest(
            text: $this->masker->mask($organization, $source),
            styleRules: array_merge(self::TEXT_RULES, $this->memory->styleRulesFor($organization, self::CAPABILITY_TEXT, $customerId)),
            glossary: $this->memory->glossaryFor($organization, self::CAPABILITY_TEXT, $customerId),
            examples: $this->memory->examplesFor($organization, self::CAPABILITY_TEXT, $customerId),
            contextHints: [
                'Protokoll: ' . $this->masker->mask($organization, (string) $protocol->title),
                'Punkt: ' . $this->masker->mask($organization, (string) $item->label),
            ],
        );

        $result = $this->invocation->invoke($organization, self::CAPABILITY_TEXT, $request, $connectionId);
        $payload = $result->result;
        if (! $payload instanceof AiTextResult) {
            throw new AiException((string) __('ai.error.unexpected_result_type'));
        }

        return $this->storeProposal((int) $organization->id, $item, self::CAPABILITY_TEXT, $source, $payload->text, $result, $user);
    }

    /**
     * Schweregrad/Kategorie/Ergebnis als Chips — null, wenn die KI keinen
     * bekannten Katalogwert liefert (unbekannte Werte werden verworfen).
     */
    public function classifyItem(ProtocolItem $item, ?User $user, ?int $connectionId = null): ?AiTextSuggestion {
        $protocol = $this->editableProtocolOf($item);
        $organization = $this->organizationOf($protocol);
        $source = $this->requireSourceText($item);

        $catalog = $this->catalogFor($organization, $item);
        if ($catalog === []) {
            return null;
        }
        $labels = array_values(array_unique(array_column($catalog, 'label')));

        $request = new ClassifyRequest(
            text: $this->masker->mask($organization, $source),
            catalog: $labels,
            multiple: true,
            language: app()->getLocale(),
        );

        $result = $this->invocation->invoke($organization, self::CAPABILITY_CLASSIFY, $request, $connectionId);
        $payload = $result->result;
        if (! $payload instanceof AiClassificationResult) {
            throw new AiException((string) __('ai.error.unexpected_classification_type'));
        }

        // Rückmapping case-insensitiv — Katalog-Garantie: nur bekannte Labels.
        $entries = [];
        foreach ($payload->values as $label) {
            $needle = mb_strtolower(trim($label));
            foreach ($catalog as $entry) {
                if (mb_strtolower($entry['label']) === $needle) {
                    $entries[$entry['kind'] . ':' . $entry['value']] = $entry;
                }
            }
        }
        if ($entries === []) {
            return null;
        }

        return $this->storeProposal(
            (int) $organization->id,
            $item,
            self::CAPABILITY_CLASSIFY,
            $source,
            (string) json_encode(array_values($entries), JSON_UNESCAPED_UNICODE),
            $result,
            $user,
        );
    }

    /**
     * Chips eines Klassifikationsvorschlags.
     *
     * @return list<array{kind: string, value: string, label: string}>
     */
    public static function classificationValues(AiTextSuggestion $suggestion): array {
        $decoded = json_decode((string) $suggestion->suggestion, true);
        if (! is_array($decoded)) {
            return [];
        }

        $entries = [];
        foreach ($decoded as $row) {
            if (is_array($row) && isset($row['kind'], $row['value'], $row['label'])) {
                $entries[] = ['kind' => (string) $row['kind'], 'value' => (string) $row['value'], 'label' => (string) $row['label']];
            }
        }

        return $entries;
    }

    /**
     * Textvorschlag übernehmen — Mangelpunkte über fillItem() (value_json.
     * description), sonst über updateItemText(). Liefert true, wenn der
     * Nutzer den Vorschlag vor der Übernahme verändert hat.
     */
    public function accept(AiTextSuggestion $suggestion, User $user, ?string $editedText = null): bool {
        $item = $this->openItemOf($suggestion);
        $this->editableProtocolOf($item);

        $text = trim($editedText ?? (string) $suggestion->suggestion);
        $edited = $text !== trim((string) $suggestion->suggestion);

        $this->guardedProtocolWrite(function () use ($item, $user, $text): void {
            if ($item->item_type === ProtocolItemType::Defect) {
                $value = is_array($item->value_json) ? $item->value_json : [];
                $value['description'] = $text;
                $this->protocols->fillItem($item, $user, ['value_json' => $value]);

                return;
            }

            $this->protocols->updateItemText($item, $user, $text, true);
        });

        $this->markDecided($suggestion, $edited ? AiTextSuggestion::STATUS_EDITED : AiTextSuggestion::STATUS_ACCEPTED, $user);
        $this->auditDecision($suggestion, $edited ? 'edited' : 'accepted', $user);

        return $edited;
    }

    /**
     * Einen Chip übernehmen (nie Auto-Apply): Schweregrad/Kategorie landen in
     * value_json, das Ergebnis im Punkt-Ergebnis — beides über fillItem().
     * Verbleibende Chips bleiben offen; der letzte schließt den Vorschlag.
     */
    public function applyValue(AiTextSuggestion $suggestion, User $user, string $kind, string $value): void {
        $item = $this->openItemOf($suggestion);
        $this->editableProtocolOf($item);

        $entries = self::classificationValues($suggestion);
        $match = null;
        foreach ($entries as $entry) {
            if ($entry['kind'] === $kind && $entry['value'] === $value) {
                $match = $entry;
                break;
            }
        }
        if ($match === null) {
            throw new AiException((string) __('ai.error.classification_value_unknown'));
        }

        $this->guardedProtocolWrite(function () use ($item, $user, $kind, $value): void {
            if ($kind === self::KIND_RESULT) {
                $this->protocols->fillItem($item, $user, ['result' => $value]);

                return;
            }

            $json = is_array($item->value_json) ? $item->value_json : [];
            $json[$kind] = $value;
            $this->protocols->fillItem($item, $user, ['value_json' => $json]);
        });

        $remaining = array_values(array_filter(
            $entries,
            static fn (array $e): bool => ! ($e['kind'] === $kind && $e['value'] === $value),
        ));

        if ($remaining === []) {
            $this->markDecided($suggestion, AiTextSuggestion::STATUS_ACCEPTED, $user);
        } else {
            $suggestion->forceFill(['suggestion' => (string) json_encode($remaining, JSON_UNESCAPED_UNICODE)])->save();
        }

        $this->auditDecision($suggestion, 'accepted', $user, ['kind' => $kind, 'value' => $value]);
    }

    /** Quelle des Vorschlags: Mangeltext (Defect) bzw. Beschreibung/Notiz. */
    public static function sourceTextOf(ProtocolItem $item): string {
        if ($item->item_type === ProtocolItemType::Defect) {
            $raw = is_array($item->value_json) ? ($item->value_json['description'] ?? null) : null;

            return is_scalar($raw) ? trim((string) $raw) : '';
        }

        $description = trim((string) $item->description);

        return $description !== '' ? $description : trim((string) $item->note);
    }

    /**
     * Katalog je Punkt: Schweregrade (nur Mangel), Fehlertypen der
     * Organisation (nur Mangel) und Punkt-Ergebnisse — alles als Labels,
     * damit der Provider semantisch statt auf Codes zuordnet.
     *
     * @return list<array{kind: string, value: string, label: string}>
     */
    private function catalogFor(Organization $organization, ProtocolItem $item): array {
        $catalog = [];

        if ($item->item_type === ProtocolItemType::Defect) {
            foreach (OpenIssueSeverity::cases() as $severity) {
                $catalog[] = ['kind' => self::KIND_SEVERITY, 'value' => $severity->value, 'label' => $severity->label()];
            }
            foreach ($this->classifications->list((int) $organization->id, ClassificationDomain::DefectType) as $classification) {
                $catalog[] = ['kind' => self::KIND_CATEGORY, 'value' => (string) $classification->code, 'label' => (string) $classification->label];
            }
        }

        if ($item->item_type !== ProtocolItemType::Group) {
            foreach (ProtocolItemResult::cases() as $result) {
                $catalog[] = ['kind' => self::KIND_RESULT, 'value' => $result->value, 'label' => $result->label()];
            }
        }

        return $catalog;
    }

    private function requireSourceText(ProtocolItem $item): string {
        $source = self::sourceTextOf($item);
        if ($source === '') {
            throw new AiException((string) __('ai.error.protocol_item_no_text'));
        }

        return $source;
    }

    private function openItemOf(AiTextSuggestion $suggestion): ProtocolItem {
        if (! $suggestion->isOpen()) {
            throw new AiException((string) __('ai.error.suggestion_decided'));
        }

        $item = $suggestion->subject;
        if (! $item instanceof ProtocolItem) {
            throw new AiException((string) __('ai.error.suggestion_subject_missing'));
        }

        return $item;
    }

    /** Signierte/archivierte/abgelöste Protokolle: keine Vorschläge, keine Übernahme. */
    private function editableProtocolOf(ProtocolItem $item): Protocol {
        $protocol = $item->protocol;
        if ($protocol === null || ! $protocol->status->isEditable()) {
            throw new AiException((string) __('ai.error.only_protocol_editable'));
        }

        return $protocol;
    }

    private function organizationOf(Protocol $protocol): Organization {
        return $protocol->organization ?? Organization::query()->findOrFail($protocol->organization_id);
    }

    /** Kundenbezug für Gedächtnis-Scope customer (über das Protokoll-Subjekt). */
    private function customerIdOf(Protocol $protocol): ?int {
        $subject = $protocol->subject;

        $customerId = match (true) {
            $subject instanceof Customer => $subject->getKey(),
            $subject instanceof DiaryEntry, $subject instanceof Project, $subject instanceof Asset => $subject->customer_id,
            default => null,
        };

        return $customerId !== null ? (int) $customerId : null;
    }

    /** Protokoll-Fachfehler werden zur KI-Fehlermeldung (Flash), nie 500. */
    private function guardedProtocolWrite(callable $write): void {
        try {
            $write();
        } catch (InvalidProtocolTransitionException $e) {
            throw new AiException((string) __('ai.error.only_protocol_editable'), 0, $e);
        } catch (ProtocolValidationException $e) {
            throw new AiException(implode(' • ', $e->errors()), 0, $e);
        }
    }
}
