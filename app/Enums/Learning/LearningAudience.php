<?php
/*
 * Created on   : Fri Aug 28 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : LearningAudience.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Enums\Learning;

use App\Enums\Concerns\HasOptions;
use App\Enums\Contracts\HasLabel;

/**
 * Zielgruppe eines Kurses (Feature 149). Steuert ausschließlich die
 * Sichtbarkeit im Katalog — die Beweiskraft des Nachweises ist für alle
 * Zielgruppen dieselbe. Default-Deny: ohne passende Zielgruppe ist ein Kurs
 * für Portal- und Extern-Lernende unsichtbar.
 */
enum LearningAudience: string implements HasLabel {
    use HasOptions;

    case Internal = 'internal';
    case External = 'external';
    case Customer = 'customer';
    case Public = 'public';

    public function label(): string {
        return (string) __('enums.learning.audience.' . $this->value);
    }

    public function tone(): string {
        return match ($this) {
            self::Internal => 'info',
            self::External => 'warning',
            self::Customer => 'success',
            self::Public => 'ghost',
        };
    }
}
