<?php
/*
 * Created on   : Sun Aug 02 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : DinCategory.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Enums\Disposal;

use App\Enums\Concerns\HasOptions;
use App\Enums\Contracts\HasLabel;

/**
 * Materialkategorie nach DIN 66399 (Feature 100, MVP-470). Zusammen mit der
 * Sicherheitsstufe 1–7 ergibt sie die Norm-Angabe der Vernichtung (z. B. H-5).
 */
enum DinCategory: string implements HasLabel {
    use HasOptions;

    case P = 'P';
    case F = 'F';
    case O = 'O';
    case T = 'T';
    case H = 'H';
    case E = 'E';

    public function label(): string {
        return match ($this) {
            self::P => (string) __('P — Papier'),
            self::F => (string) __('F — Film/Folie'),
            self::O => (string) __('O — Optische Datenträger'),
            self::T => (string) __('T — Magnetische Datenträger'),
            self::H => (string) __('H — Festplatten'),
            self::E => (string) __('E — Elektronische Datenträger'),
        };
    }
}
