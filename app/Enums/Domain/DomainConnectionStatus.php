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

use App\Enums\Concerns\{HasOptions, HasTransitions};
use App\Enums\Contracts\HasStatusTransitions;

/**
 * Lebenszyklus einer DomainReselling-Verbindung (Feature 083, MVP-385).
 * `Draft` bis Zugangsdaten erfolgreich geprüft; `Active` betriebsbereit;
 * `Blocked` nach wiederholtem Auth-/Transportfehler (Auto-Disable via
 * {@see \App\Models\Concerns\HasConnectionHealth}); `Disconnected` nach
 * „Trennen“ (MVP-1491): Passwort geleert, Projektionen und Journal bleiben,
 * neue Zugangsdaten führen über `Draft` zurück.
 */
enum DomainConnectionStatus: string implements HasStatusTransitions {
    use HasOptions;
    use HasTransitions;

    case Draft = 'draft';
    case Active = 'active';
    case Blocked = 'blocked';
    case Disconnected = 'disconnected';

    public function label(): string {
        return (string) __('enums.domain.connection_status.' . $this->value);
    }

    /** @return list<self> */
    public function allowedTransitions(): array {
        return match ($this) {
            self::Draft => [self::Active, self::Blocked, self::Disconnected],
            self::Active => [self::Blocked, self::Disconnected],
            self::Blocked => [self::Active, self::Disconnected],
            self::Disconnected => [self::Draft],
        };
    }

    public function isRunnable(): bool {
        return $this === self::Active;
    }

    public function badge(): string {
        return match ($this) {
            self::Active => 'success',
            self::Draft => 'neutral',
            self::Blocked => 'error',
            self::Disconnected => 'neutral',
        };
    }
}
