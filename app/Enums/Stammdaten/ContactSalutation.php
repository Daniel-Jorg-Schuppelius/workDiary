<?php
/*
 * Created on   : Mon Oct 12 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ContactSalutation.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Enums\Stammdaten;

use App\Enums\Concerns\HasOptions;
use App\Enums\Contracts\HasLabel;
use CommonToolkit\Helper\Data\PersonNameHelper;

/**
 * Anrede eines Ansprechpartners (MVP-1136). Die Werte `mr`/`ms` entsprechen
 * der kanonischen Anrede des Namenszerlegers im common-toolkit.
 */
enum ContactSalutation: string implements HasLabel {
    use HasOptions;

    case Mr = 'mr';
    case Ms = 'ms';
    case Diverse = 'diverse';

    /** Anrede aus Text („Herr“, „Mrs“, „Frau Dr.“, „divers“ oder Enum-Wert), sonst null. */
    public static function fromText(?string $text): ?self {
        $text = trim((string) $text);
        $direct = self::tryFrom(mb_strtolower($text));
        if ($direct !== null) {
            return $direct;
        }
        if (in_array(mb_strtolower($text), ['divers', 'diverso'], true)) {
            return self::Diverse;
        }

        return self::tryFrom((string) PersonNameHelper::splitSalutation($text)['salutation']);
    }

    public function label(): string {
        return (string) __('enums.stammdaten.contact_salutation.' . $this->value);
    }
}
