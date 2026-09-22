<?php
/*
 * Created on   : Tue Sep 22 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : LexwarePlan.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Enums\Lexoffice;

use App\Enums\Concerns\HasOptions;
use App\Enums\Contracts\HasLabel;

/**
 * Gebuchter Lexware-Office-Tarif der Organisation (Feature 158, MVP-831).
 * Der Tarif ist eine Orientierung, keine Berechtigung — welche Übergabe
 * möglich ist, entscheiden Verbindung, Rechte und geprüfte Verfügbarkeit.
 */
enum LexwarePlan: string implements HasLabel {
    use HasOptions;

    case Unknown = 'unknown';
    case S = 's';
    case M = 'm';
    case L = 'l';
    case XL = 'xl';

    public function label(): string {
        return (string) __('lexware.plan.' . $this->value);
    }

    public function isKnown(): bool {
        return $this !== self::Unknown;
    }

    /** @return list<self> */
    public static function known(): array {
        return [self::S, self::M, self::L, self::XL];
    }
}
