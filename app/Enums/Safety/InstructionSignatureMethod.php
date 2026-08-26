<?php
/*
 * Created on   : Tue Aug 25 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : InstructionSignatureMethod.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Enums\Safety;

use App\Enums\Concerns\HasOptions;
use App\Enums\Contracts\HasLabel;

/**
 * Nachweisform der Unterweisungs-Teilnahme (DGUV V1 § 4, Feature 132):
 * Bestätigungs-Klick der angemeldeten Person (Name + Zeitpunkt + IP) oder
 * gezeichnete Unterschrift (Bilddatei, nur Datenmodell — kein Erfassungs-UI im MVP).
 */
enum InstructionSignatureMethod: string implements HasLabel {
    use HasOptions;

    case Confirmed = 'confirmed';
    case Drawn = 'drawn';

    public function label(): string {
        return (string) __('enums.safety.signature-method.' . $this->value);
    }
}
