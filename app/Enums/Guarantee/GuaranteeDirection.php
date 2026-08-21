<?php
/*
 * Created on   : Thu Aug 20 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : GuaranteeDirection.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Enums\Guarantee;

use App\Enums\Concerns\HasOptions;
use App\Enums\Contracts\HasLabel;

/**
 * Richtung einer Bürgschaft (Feature 114, MVP-603).
 *
 * `Issued` = wir haben sie gestellt (Avalprovision läuft zu unseren Lasten,
 * bis sie zurückkommt). `Received` = wir haben sie erhalten (unsere
 * Sicherheit ist weg, wenn sie unbemerkt abläuft). Zwei gegenläufige
 * Risiken — deshalb zwei getrennte Alarme.
 */
enum GuaranteeDirection: string implements HasLabel {
    use HasOptions;

    case Issued = 'issued';
    case Received = 'received';

    public function label(): string {
        return (string) __('enums.guarantee_direction.' . $this->value);
    }
}
