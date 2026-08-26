<?php
/*
 * Created on   : Wed Aug 26 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : TrainingProviderKind.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Enums\Training;

use App\Enums\Concerns\HasOptions;
use App\Enums\Contracts\HasLabel;

/**
 * Anbieter einer Schulung (Feature 145): intern (eigene Unterweisung) oder
 * extern (Bildungsträger, Berufsgenossenschaft, Hersteller).
 */
enum TrainingProviderKind: string implements HasLabel {
    use HasOptions;

    case Internal = 'internal';
    case External = 'external';

    public function label(): string {
        return (string) __('enums.training.provider-kind.' . $this->value);
    }

    public function tone(): string {
        return match ($this) {
            self::Internal => 'info',
            self::External => 'ghost',
        };
    }
}
