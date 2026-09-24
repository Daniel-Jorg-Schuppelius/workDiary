<?php
/*
 * Created on   : Sun May 31 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ProtocolItemType.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Enums\Protocol;

use App\Enums\Concerns\HasOptions;
use App\Enums\Contracts\HasLabel;
use App\Enums\Fields\Contracts\FieldTyped;
use App\Enums\Fields\FieldType;
use App\Services\Protocol\Fields\Extensions\{AttachmentsField, DefectField, MeasurementSeriesField, SignatureField};

/**
 * Erlaubte Typen für `protocol_items.item_type` (MVP-021 §3).
 *
 * Jeder Typ hat ein eigenes `value_json`-Schema, ggf. automatische
 * `result`-Ableitung und eine UI-Komponente. Die Validierung erfolgt
 * zentral im {@see \App\Services\Protocol\ProtocolItemValidator}.
 */
enum ProtocolItemType: string implements FieldTyped, HasLabel {
    use HasOptions;

    case Group = 'group';
    case Text = 'text';
    case Boolean = 'boolean';
    case Choice = 'choice';
    case Multichoice = 'multichoice';
    case Number = 'number';
    case Range = 'range';
    case Date = 'date';
    case DateTime = 'datetime';
    case Signature = 'signature';
    case Photo = 'photo';
    case File = 'file';
    case Defect = 'defect';
    case MeasurementTimestamped = 'measurement.timestamped';
    case ProcedureStep = 'procedure_step';
    case SignoffInternal = 'signoff_internal';

    public function label(): string {
        return (string) __('enums.protocol.item-type.' . $this->value);
    }

    /**
     * Grundtyp im Feldschema-Baustein (MVP-867). Der Enum-Wert bleibt der
     * hash-relevante Speicherwert; `range` ist eine Zahl mit Toleranz, kein
     * Bewertungsraster, und Freitext ist mehrzeilig.
     */
    public function fieldType(): FieldType {
        return match ($this) {
            self::Group, self::ProcedureStep => FieldType::Section,
            self::Text => FieldType::Textarea,
            self::Boolean, self::SignoffInternal => FieldType::Boolean,
            self::Choice => FieldType::Choice,
            self::Multichoice => FieldType::Multichoice,
            self::Number, self::Range => FieldType::Number,
            self::Date => FieldType::Date,
            self::DateTime => FieldType::DateTime,
            self::Signature => FieldType::Signature,
            self::Photo => FieldType::Photo,
            self::File => FieldType::File,
            self::Defect => FieldType::Textarea,
            self::MeasurementTimestamped => FieldType::Measurement,
        };
    }

    public function fieldExtension(): ?string {
        return match ($this) {
            self::Defect => DefectField::KEY,
            self::MeasurementTimestamped => MeasurementSeriesField::KEY,
            self::Photo, self::File => AttachmentsField::KEY,
            self::Signature => SignatureField::KEY,
            default => null,
        };
    }

    /**
     * Ob der Typ einen tatsaechlichen Wert traegt (vs. reine Struktur).
     * `group` z. B. ist ausschliesslich strukturell.
     */
    public function hasValue(): bool {
        return $this !== self::Group;
    }

    /**
     * Ob `result` aus dem Wert automatisch abgeleitet wird (true) oder
     * vom Anwender explizit gesetzt werden muss (false).
     */
    public function derivesResult(): bool {
        return in_array($this, [
            self::Boolean,
            self::Choice,
            self::Number,
            self::Range,
            self::Defect,
        ], true);
    }
}
