<?php
/*
 * Created on   : Sun Sep 27 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : DamageKind.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Enums\Damage;

use App\Enums\Concerns\HasOptions;
use App\Enums\Contracts\HasLabel;

/** Art eines Schadensfalls (MVP-919). */
enum DamageKind: string implements HasLabel {
    use HasOptions;

    case Property = 'property';
    case Liability = 'liability';
    case Theft = 'theft';
    case Vehicle = 'vehicle';
    case Transport = 'transport';
    case Other = 'other';

    public function label(): string {
        return (string) __('damage.kind.' . $this->value);
    }
}
