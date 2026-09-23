<?php
/*
 * Created on   : Wed Sep 23 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ModuleKind.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Enums\Modules;

use App\Enums\Contracts\HasLabel;

/**
 * Modulart (MVP-861). Bestimmt die Abhängigkeitsregel (MVP-863): Plattform
 * kennt kein Fachmodul, Kern nutzt Plattform und Kern, Feature nutzt beides
 * und andere Features nur über `requires()`.
 */
enum ModuleKind: string implements HasLabel {
    case Platform = 'platform';
    case Core = 'core';
    case Feature = 'feature';

    public function label(): string {
        return match ($this) {
            self::Platform => (string) __('Plattformdienst'),
            self::Core => (string) __('Kernmodul'),
            self::Feature => (string) __('Fachmodul'),
        };
    }
}
