<?php
/*
 * Created on   : Tue Sep 22 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : LexwareCoverage.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Enums\Lexoffice;

use App\Enums\Concerns\HasOptions;
use App\Enums\Contracts\HasLabel;

/**
 * Abdeckung einer Funktion je Tarif (Feature 158): in Lexware enthalten,
 * lokal ergänzbar, geplanter Ausbau oder — bei unbekanntem Tarif — keine
 * sichere Aussage.
 */
enum LexwareCoverage: string implements HasLabel {
    use HasOptions;

    case Lexware = 'lexware';
    case Supplement = 'supplement';
    case Expansion = 'expansion';
    case Unknown = 'unknown';

    public function label(): string {
        return (string) __('lexware.coverage.' . $this->value);
    }

    public function tone(): string {
        return match ($this) {
            self::Lexware => 'info',
            self::Supplement => 'success',
            self::Expansion => 'ghost',
            self::Unknown => 'warning',
        };
    }
}
