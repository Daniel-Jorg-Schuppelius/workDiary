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

/**
 * Erlaubte Typen für `protocol_items.item_type` (MVP-021 §3).
 *
 * Jeder Typ hat ein eigenes `value_json`-Schema, ggf. automatische
 * `result`-Ableitung und eine UI-Komponente. Die Validierung erfolgt
 * zentral im {@see \App\Services\Protocol\ProtocolItemValidator}.
 */
enum ProtocolItemType: string implements HasLabel {
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
