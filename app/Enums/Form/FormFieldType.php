<?php
/*
 * Created on   : Wed Jun 10 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : FormFieldType.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Enums\Form;

use App\Enums\Concerns\HasOptions;
use App\Enums\Contracts\HasLabel;

/**
 * Feldtypen des Formularsystems (Feature 032). Foto/Datei/Unterschrift (Rang 32)
 * legen ihren Inhalt als Attachment am Submission ab (meta_type `field:<key>`),
 * nicht als Skalar im `values`-JSON.
 */
enum FormFieldType: string implements HasLabel {
    use HasOptions;

    case Text = 'text';
    case Textarea = 'textarea';
    case Number = 'number';
    case Date = 'date';
    case Select = 'select';
    case Checkbox = 'checkbox';
    case Photo = 'photo';
    case File = 'file';
    case Signature = 'signature';

    public function label(): string {
        return (string) __('enums.form.field_type.' . $this->value);
    }

    /** Braucht dieser Typ eine Optionsliste? */
    public function needsOptions(): bool {
        return $this === self::Select;
    }

    /** Erlaubt dieser Typ eine Einheit (z. B. kWh, °C)? */
    public function supportsUnit(): bool {
        return $this === self::Number;
    }

    /** Datei-/Fotoupload (Rang 32) — Inhalt kommt als hochgeladene Datei. */
    public function isUpload(): bool {
        return $this === self::Photo || $this === self::File;
    }

    /** Unterschrift (Rang 32) — Inhalt kommt als Base64-PNG vom Signatur-Pad. */
    public function isSignature(): bool {
        return $this === self::Signature;
    }

    /** Legt dieser Typ seinen Inhalt als Attachment ab (statt als Skalar)? */
    public function storesAttachment(): bool {
        return $this->isUpload() || $this->isSignature();
    }
}
