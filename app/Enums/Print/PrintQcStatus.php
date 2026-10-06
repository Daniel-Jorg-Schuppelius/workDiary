<?php
/*
 * Created on   : Tue Oct 06 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : PrintQcStatus.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Enums\Print;

use App\Enums\Contracts\HasLabel;

/** Ergebnis der Qualitätskontrolle eines Druckauftrags; leer, solange nicht geprüft wurde. */
enum PrintQcStatus: string implements HasLabel {
    case Passed = 'passed';
    case Rework = 'rework';
    case Blocked = 'blocked';

    public function label(): string {
        return (string) __('print.qc.' . $this->value);
    }
}
