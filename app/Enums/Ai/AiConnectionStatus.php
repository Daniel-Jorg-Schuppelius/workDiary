<?php
/*
 * Created on   : Thu Jul 16 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : AiConnectionStatus.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Enums\Ai;

use App\Enums\Concerns\HasOptions;
use App\Enums\Contracts\HasLabel;

/**
 * Lebenszyklus einer KI-Provider-Verbindung (Feature 025, MVP-399).
 * `Draft` bis zum bestandenen Preflight, `Active` betriebsbereit,
 * `Blocked` bewusst gesperrt (z. B. Datenschutz-Entscheidung); das
 * Auto-Disable bei wiederholten Fehlern läuft zusätzlich über
 * {@see \App\Models\Concerns\HasConnectionHealth}.
 */
enum AiConnectionStatus: string implements HasLabel {
    use HasOptions;

    case Draft = 'draft';
    case Active = 'active';
    case Blocked = 'blocked';

    public function label(): string {
        return (string) __('enums.ai.connection_status.' . $this->value);
    }

    public function isRunnable(): bool {
        return $this === self::Active;
    }

    public function badge(): string {
        return match ($this) {
            self::Active => 'success',
            self::Draft => 'neutral',
            self::Blocked => 'danger',
        };
    }
}
