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
use App\Models\{Protocol, ProtocolItem};

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
    /**
     * @return list<string>
     */
    public function validate(ProtocolItem $item): array {
        $errors = [];
        $value = $item->value_json ?? [];

        if (! $item->item_type->hasValue()) {
            return $errors; // group: keine Validierung
        }

        if ($item->required && $this->isEmpty($item)) {
            $errors[] = (string) __('protocol.validation.required', ['label' => $item->label]);
            return $errors;
        }

        if ($this->isEmpty($item)) {
            return $errors; // nicht ausgefuellt, nicht pflicht
        }

        $errors = array_merge($errors, match ($item->item_type) {
            ProtocolItemType::Text => $this->validateText($item, $value),
            ProtocolItemType::Boolean => $this->validateBoolean($value),
            ProtocolItemType::Choice => $this->validateChoice($value),
            ProtocolItemType::Multichoice => $this->validateMultichoice($value),
            ProtocolItemType::Number, ProtocolItemType::Range => $this->validateNumber($value),
            ProtocolItemType::Date, ProtocolItemType::DateTime => $this->validateDate($value),
            ProtocolItemType::Photo, ProtocolItemType::File => $this->validateAttachments($value),
            ProtocolItemType::Defect => $this->validateDefect($value),
            ProtocolItemType::MeasurementTimestamped => $this->validateMeasurement($value),
            ProtocolItemType::Signature => $this->validateSignature($value),
            ProtocolItemType::ProcedureStep, ProtocolItemType::SignoffInternal,
            ProtocolItemType::Group => [],
        });

        if ($item->item_type === ProtocolItemType::Photo) {
            $errors = array_merge($errors, $this->missingPhotoPhases($item));
        }

        return $errors;
    }

    /**
     * Prueft die in `value_json.min_per_phase` geforderten Mindestmengen
     * je Phase fuer Foto-Items (MVP-023 §5). Fehlt der Schluessel ganz,
     * wird (rueckwaertskompatibel mit MVP-021) nicht zusaetzlich gefordert.
     *
     * @return list<string>
     */
    public function missingPhotoPhases(ProtocolItem $item): array {
        if ($item->item_type !== ProtocolItemType::Photo) {
            return [];
        }
        $value = (array) ($item->value_json ?? []);
        $min = (array) ($value['min_per_phase'] ?? []);
        if ($min === []) {
            return [];
        }
        $counts = \App\Models\ProtocolItemPhoto::query()
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

    // --------- Typ-spezifische Schema-Pruefungen ---------

    /**
     * @param array<string, mixed> $value
     * @return list<string>
     */
    private function validateText(ProtocolItem $item, array $value): array {
        $errors = [];
        $text = (string) ($value['text'] ?? '');
        $min = $item->value_json['min_length'] ?? null;
        $max = $item->value_json['max_length'] ?? null;
        if ($min !== null && mb_strlen($text) < (int) $min) {
            $errors[] = (string) __('protocol.validation.text.minLength', ['min' => $min]);
        }
        if ($max !== null && mb_strlen($text) > (int) $max) {
            $errors[] = (string) __('protocol.validation.text.maxLength', ['max' => $max]);
        }
        return $errors;
    }

    /**
     * @param array<string, mixed> $value
     * @return list<string>
     */
    private function validateBoolean(array $value): array {
        return is_bool($value['value'] ?? null) ? [] : [(string) __('protocol.validation.boolean.invalid')];
    }

    /**
     * @param array<string, mixed> $value
     * @return list<string>
     */
    private function validateChoice(array $value): array {
        $selected = $value['selected'] ?? null;
        $options = $value['options'] ?? [];
        if (! is_string($selected) && ! is_int($selected)) {
            return [(string) __('protocol.validation.choice.invalid')];
        }
        if (! empty($options)) {
            $keys = array_column((array) $options, 'key');
            if (! in_array($selected, $keys, true)) {
                return [(string) __('protocol.validation.choice.notInOptions')];
            }
        }
        return [];
    }

    /**
     * @param array<string, mixed> $value
     * @return list<string>
     */
    private function validateMultichoice(array $value): array {
        $selected = $value['selected'] ?? null;
        if (! is_array($selected) || $selected === []) {
            return [(string) __('protocol.validation.multichoice.invalid')];
        }
        $options = $value['options'] ?? [];
        if (! empty($options)) {
            $keys = array_column((array) $options, 'key');
            foreach ($selected as $sel) {
                if (! in_array($sel, $keys, true)) {
                    return [(string) __('protocol.validation.multichoice.notInOptions')];
                }
            }
        }
        return [];
    }

    /**
     * @param array<string, mixed> $value
     * @return list<string>
     */
    private function validateNumber(array $value): array {
        $errors = [];
        $val = $value['value'] ?? null;
        if (! is_int($val) && ! is_float($val)) {
            return [(string) __('protocol.validation.number.invalid')];
        }
        foreach (['min', 'max'] as $bound) {
            if (isset($value[$bound]) && (
                ($bound === 'min' && $val < $value[$bound])
                || ($bound === 'max' && $val > $value[$bound])
            )) {
                $errors[] = (string) __('protocol.validation.number.' . $bound, [
                    'bound' => $value[$bound],
                ]);
            }
        }
        return $errors;
    }

    /**
     * @param array<string, mixed> $value
     * @return list<string>
     */
    private function validateDate(array $value): array {
        $raw = (string) ($value['value'] ?? '');
        $ts = strtotime($raw);
        return $ts === false ? [(string) __('protocol.validation.date.invalid')] : [];
    }

    /**
     * @param array<string, mixed> $value
     * @return list<string>
     */
    private function validateAttachments(array $value): array {
        $ids = $value['attachment_ids'] ?? [];
        if (! is_array($ids) || $ids === []) {
            return [(string) __('protocol.validation.attachments.required')];
        }
        $min = (int) ($value['min_count'] ?? 0);
        $max = $value['max_count'] ?? null;
        $errors = [];
        if (count($ids) < $min) {
            $errors[] = (string) __('protocol.validation.attachments.min', ['min' => $min]);
        }
        if ($max !== null && count($ids) > (int) $max) {
            $errors[] = (string) __('protocol.validation.attachments.max', ['max' => $max]);
        }
        return $errors;
    }

    /**
     * @param array<string, mixed> $value
     * @return list<string>
     */
    private function validateDefect(array $value): array {
        $errors = [];
        $allowed = ['low', 'medium', 'high', 'critical'];
        if (! in_array($value['severity'] ?? null, $allowed, true)) {
            $errors[] = (string) __('protocol.validation.defect.severity');
        }
        if (trim((string) ($value['description'] ?? '')) === '') {
            $errors[] = (string) __('protocol.validation.defect.description');
        }
        return $errors;
    }

    /**
     * @param array<string, mixed> $value
     * @return list<string>
     */
    private function validateMeasurement(array $value): array {
        $samples = $value['samples'] ?? null;
        if (! is_array($samples) || $samples === []) {
            return [(string) __('protocol.validation.measurement.empty')];
        }
        foreach ($samples as $s) {
            if (! is_array($s) || ! array_key_exists('value', $s) || ! array_key_exists('at', $s)) {
                return [(string) __('protocol.validation.measurement.invalidSample')];
            }
        }
        return [];
    }

    /**
     * @param array<string, mixed> $value
     * @return list<string>
     */
    private function validateSignature(array $value): array {
        return isset($value['signature_id']) && is_int($value['signature_id'])
            ? []
            : [(string) __('protocol.validation.signature.missing')];
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
