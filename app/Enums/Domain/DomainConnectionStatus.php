<?php
/*
 * Created on   : Thu Jul 16 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : DomainConnectionStatus.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Enums\Domain;

use App\Enums\Concerns\HasOptions;
use App\Enums\Contracts\HasLabel;

/**
 * Lebenszyklus einer DomainReselling-Verbindung (Feature 083, MVP-385).
 * `Draft` bis Zugangsdaten erfolgreich geprüft; `Active` betriebsbereit;
 * `Blocked` nach wiederholtem Auth-/Transportfehler (Auto-Disable via
 * {@see \App\Models\Concerns\HasConnectionHealth}).
 */
enum DomainConnectionStatus: string implements HasLabel {
    use HasOptions;

    case Draft = 'draft';
    case Active = 'active';
    case Blocked = 'blocked';

    public function label(): string {
        return (string) __('enums.domain.connection_status.' . $this->value);
    }

    public function isRunnable(): bool {
        return $this === self::Active;
    }

    public function badge(): string {
        return match ($this) {
            self::Active => 'success',
            self::Draft => 'neutral',
            self::Blocked => 'error',
        };
    }
}
