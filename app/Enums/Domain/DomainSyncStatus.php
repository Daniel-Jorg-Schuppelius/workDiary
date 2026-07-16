<?php
/*
 * Created on   : Thu Jul 16 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : DomainSyncStatus.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Enums\Domain;

use App\Enums\Concerns\HasOptions;
use App\Enums\Contracts\HasLabel;

/**
 * Aktualitäts-/Abgleichzustand einer Domain-Projektion (Feature 083, MVP-387).
 * `Current` = frisch abgeglichen; `Stale` = Datenalter überschritten;
 * `Pending` = laufende WorkDiary-Mutation noch nicht bestätigt; `Conflict` =
 * Providerzustand weicht nach dem Schreiben ab; `Unknown` = Ausgang unklar
 * (fehlendes `EOF`/Timeout), niemals blind als Erfolg gewertet.
 */
enum DomainSyncStatus: string implements HasLabel {
    use HasOptions;

    case Current = 'current';
    case Stale = 'stale';
    case Pending = 'pending';
    case Conflict = 'conflict';
    case Unknown = 'unknown';

    public function label(): string {
        return (string) __('enums.domain.sync_status.' . $this->value);
    }

    /** Ampelfarbe für die UI-Badge. */
    public function badge(): string {
        return match ($this) {
            self::Current => 'success',
            self::Stale => 'warning',
            self::Pending => 'info',
            self::Conflict, self::Unknown => 'error',
        };
    }

    public function isCurrent(): bool {
        return $this === self::Current;
    }
}
