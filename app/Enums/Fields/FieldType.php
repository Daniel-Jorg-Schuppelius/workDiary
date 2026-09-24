<?php
/*
 * Created on   : Thu Sep 24 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : FieldType.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Enums\Fields;

use App\Enums\Contracts\HasLabel;

/**
 * Typkatalog des Feldschema-Bausteins (MVP-866): eine Liste für Formulare,
 * Checklisten und ab MVP-867 Protokolle, Prozeduren und Umfragen. Fachtypen
 * (Mangel, Messreihe, Prozedurschritt) hängen als `extension` an einer
 * Definition, nicht als weiterer Fall.
 */
enum FieldType: string implements HasLabel {
    case Text = 'text';
    case Textarea = 'textarea';
    case Number = 'number';
    case Boolean = 'boolean';
    case Choice = 'choice';
    case Multichoice = 'multichoice';
    case Date = 'date';
    case DateTime = 'datetime';
    case Scale = 'scale';
    case Photo = 'photo';
    case File = 'file';
    case Signature = 'signature';
    case Section = 'section';
    case Measurement = 'measurement';

    /** Frühere Formular-Typnamen (`select`, `checkbox`) lesen weiter. */
    public static function fromStored(string $value): ?self {
        return self::tryFrom($value) ?? match ($value) {
            'select' => self::Choice,
            'checkbox' => self::Boolean,
            default => null,
        };
    }

    public function label(): string {
        return (string) __('enums.fields.type.' . $this->value);
    }

    public function needsOptions(): bool {
        return $this === self::Choice || $this === self::Multichoice;
    }

    public function supportsUnit(): bool {
        return $this === self::Number || $this === self::Measurement;
    }

    public function supportsRange(): bool {
        return $this === self::Number || $this === self::Scale || $this === self::Measurement;
    }

    /** Datei-/Fotoupload — Inhalt kommt als hochgeladene Datei. */
    public function isUpload(): bool {
        return $this === self::Photo || $this === self::File;
    }

    /** Unterschrift — Inhalt kommt als Base64-PNG vom Signatur-Pad. */
    public function isSignature(): bool {
        return $this === self::Signature;
    }

    /** Legt der Typ seinen Inhalt als Attachment ab (statt als Skalar)? */
    public function storesAttachment(): bool {
        return $this->isUpload() || $this->isSignature();
    }

    /** Trägt der Typ einen Wert? Abschnitte gliedern nur. */
    public function hasValue(): bool {
        return $this !== self::Section;
    }

    public function isMultiValue(): bool {
        return $this === self::Multichoice;
    }
}
