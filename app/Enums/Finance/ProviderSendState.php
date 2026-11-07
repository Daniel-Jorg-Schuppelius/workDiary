<?php
/*
 * Created on   : Sat Nov 07 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ProviderSendState.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Enums\Finance;

use App\Enums\Concerns\HasOptions;
use App\Enums\Contracts\HasLabel;

/**
 * Hat das führende Fakturasystem die Rechnung selbst zugestellt (Feature 196)?
 * Unbekannt ist der Normalfall, wenn die API es nicht meldet; dann entscheidet
 * die Erklärung der Organisation oder eine bewusste Bestätigung je Rechnung.
 */
enum ProviderSendState: string implements HasLabel {
    use HasOptions;

    case NotSent = 'not_sent';
    case Sent = 'sent';
    case Unknown = 'unknown';

    public function label(): string {
        return (string) __('enums.finance.provider-send-state.' . $this->value);
    }

    public function tone(): string {
        return match ($this) {
            self::NotSent => 'ghost',
            self::Sent => 'warning',
            self::Unknown => 'ghost',
        };
    }
}
