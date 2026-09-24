<?php
/*
 * Created on   : Sun May 31 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ProtocolItemValidator.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Services\Protocol;

use App\Enums\Protocol\{ProtocolItemResult, ProtocolItemType};
use App\Models\Protocol\{Protocol, ProtocolItem};
use App\Services\Fields\{FieldSchema, FieldValidator};
use App\Services\Protocol\Fields\ProtocolItemFields;
use Illuminate\Support\Facades\Validator;

/**
 * Validiert ein {@see ProtocolItem} gemäß seinem `item_type` (MVP-021 §4).
 *
 * Aufgaben:
 * 1. Typ-spezifische Schema-Prüfung auf `value_json`.
 * 2. Auswertung von `required`.
 * 3. Wert-/Toleranzbereich.
 * 4. `result`-Ableitung sofern automatisch.
 *
 * Liefert eine Liste von Fehlermeldungen; leer = gültig.
 */
class ProtocolItemValidator {
    public function __construct(
        private readonly ProtocolItemFields $fields,
        private readonly FieldValidator $fieldValidator,
    ) {}

    /**
     * @return list<string>
     */
    public function validate(ProtocolItem $item): array {
        if (! $item->item_type->hasValue()) {
            return []; // group: keine Validierung
        }
        if ($this->isEmpty($item)) {
            return $item->required
                ? [(string) __('protocol.validation.required', ['label' => $item->label])]
                : []; // nicht ausgefüllt, nicht Pflicht
        }
        // Regeln je Grundtyp aus dem Feldschema-Baustein, Fachtypen (Mangel,
        // Messreihe, Anhänge, Signatur) aus den Protokoll-Erweiterungen —
        // geprüft wird der aus `value_json` gelesene Wert, nie eine neue Form.
        $schema = new FieldSchema([$this->fields->definition($item)]);
        $errors = Validator::make(
            ['values' => [ProtocolItemFields::key($item) => $this->fields->value($item)]],
            $this->fieldValidator->rules($schema),
            [],
            $schema->attributeNames(),
        )->errors()->all();
        if ($item->item_type === ProtocolItemType::Photo) {
            $errors = array_merge($errors, $this->missingPhotoPhases($item));
        }

        return array_values($errors);
    }

    /**
     * Prueft die in `value_json.min_per_phase` geforderten Mindestmengen
     * je Phase fuer Foto-Items (MVP-023 §5). Fehlt der Schluessel ganz,
     * wird (rueckwaertskompatibel mit MVP-021) nicht zusaetzlich gefordert.
     *
     * @return list<string>
     */
    public function missingPhotoPhases(ProtocolItem $item): array {
        $value = (array) ($item->value_json ?? []);
        $min = (array) ($value['min_per_phase'] ?? []);

        // Vollaudit 2026-07 (H7): defect-Punkte erzwingen automatisch
        // mindestens ein Mängel-Foto (protokoll-fotos.md §5).
        if ($item->item_type === ProtocolItemType::Defect) {
            $min['defect'] = max(1, (int) ($min['defect'] ?? 0));
        } elseif ($item->item_type !== ProtocolItemType::Photo) {
            return [];
        }

        if ($min === []) {
            return [];
        }
        $counts = \App\Models\Protocol\ProtocolItemPhoto::query()
            ->where('protocol_item_id', $item->id)
            ->selectRaw('phase, COUNT(*) as c')
            ->groupBy('phase')
            ->pluck('c', 'phase')
            ->all();

        $errors = [];
        foreach ($min as $phase => $required) {
            $have = (int) ($counts[$phase] ?? 0);
            if ($have < (int) $required) {
                $errors[] = (string) __('protocol.validation.photo.missingPhase', [
                    'label' => $item->label,
                    'phase' => $phase,
                    'have' => $have,
                    'need' => (int) $required,
                ]);
            }
        }
        return $errors;
    }

    /**
     * Aggregierte Pruefung auf Protokoll-Ebene: alle `required`-Items
     * gefuellt, keine Validierungsfehler, kein `defect`/critical ohne
     * verknuepften Open-Issue. Wird vor `requestReview` / `sign` aufgerufen.
     *
     * @return list<string>
     */
    public function validateProtocol(Protocol $protocol): array {
        $errors = [];

        foreach ($protocol->items as $item) {
            foreach ($this->validate($item) as $msg) {
                $errors[] = sprintf('„%s": %s', $item->label, $msg);
            }

            if ($item->item_type === ProtocolItemType::Defect) {
                $severity = (string) ($item->value_json['severity'] ?? '');
                $openIssueId = $item->value_json['open_issue_id'] ?? null;
                if ($severity === 'critical' && $openIssueId === null) {
                    $errors[] = (string) __('protocol.validation.criticalDefectMissingOpenIssue', [
                        'label' => $item->label,
                    ]);
                }
            }
        }

        return $errors;
    }

    /**
     * Leitet aus dem Wert das `result` ab, sofern der Typ es vorsieht.
     * Liefert `null`, wenn keine automatische Ableitung greift (der
     * Anwender hat dann selbst `result` zu setzen).
     */
    public function deriveResult(ProtocolItem $item): ?ProtocolItemResult {
        if (! $item->item_type->derivesResult()) {
            return null;
        }
        $value = $item->value_json ?? [];

        return match ($item->item_type) {
            ProtocolItemType::Boolean => isset($value['value'])
                ? ((bool) $value['value'] ? ProtocolItemResult::Ok : ProtocolItemResult::NotOk)
                : null,
            ProtocolItemType::Choice => $this->deriveFromChoice($value),
            ProtocolItemType::Number, ProtocolItemType::Range => $this->deriveFromNumber($value),
            ProtocolItemType::Defect => ProtocolItemResult::NotOk,
            default => null,
        };
    }

    private function isEmpty(ProtocolItem $item): bool {
        $value = $item->value_json;
        if ($value === null || $value === []) {
            return true;
        }

        return match ($item->item_type) {
            ProtocolItemType::Text => trim((string) ($value['text'] ?? '')) === '',
            ProtocolItemType::Boolean => ! array_key_exists('value', $value),
            ProtocolItemType::Choice => ($value['selected'] ?? null) === null,
            ProtocolItemType::Multichoice => empty($value['selected'] ?? []),
            ProtocolItemType::Number, ProtocolItemType::Range => ! array_key_exists('value', $value),
            ProtocolItemType::Date, ProtocolItemType::DateTime => empty($value['value'] ?? null),
            ProtocolItemType::Photo, ProtocolItemType::File => empty($value['attachment_ids'] ?? []),
            ProtocolItemType::Defect => empty($value['description'] ?? null),
            ProtocolItemType::MeasurementTimestamped => empty($value['samples'] ?? []),
            ProtocolItemType::Signature => empty($value['signature_id'] ?? null),
            default => false,
        };
    }

    /**
     * @param array<string, mixed> $value
     */
    private function deriveFromChoice(array $value): ?ProtocolItemResult {
        $selected = $value['selected'] ?? null;
        $mapping = $value['result_map'] ?? null;
        if (is_array($mapping) && $selected !== null && isset($mapping[$selected])) {
            return ProtocolItemResult::tryFrom((string) $mapping[$selected]);
        }
        return null;
    }

    /**
     * @param array<string, mixed> $value
     */
    private function deriveFromNumber(array $value): ?ProtocolItemResult {
        $val = $value['value'] ?? null;
        if (! is_int($val) && ! is_float($val)) {
            return null;
        }
        $min = $value['tolerance_min'] ?? null;
        $max = $value['tolerance_max'] ?? null;
        if ($min === null && $max === null) {
            return null;
        }
        if (($min !== null && $val < $min) || ($max !== null && $val > $max)) {
            return ProtocolItemResult::NotOk;
        }
        return ProtocolItemResult::Ok;
    }
}
