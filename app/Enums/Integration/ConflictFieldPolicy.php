<?php
/*
 * Created on   : Mon Jun 29 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ConflictFieldPolicy.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Enums\Integration;

use App\Enums\Concerns\HasOptions;
use App\Enums\Contracts\HasLabel;

/**
 * Verhalten bei Feld-Abweichungen, wenn ein Remote-Datensatz BEREITS einem
 * lokalen Datensatz zugeordnet ist (Update-Fall). Verallgemeinert die
 * Lexoffice-Konflikt-Semantik plugin-übergreifend.
 */
enum ConflictFieldPolicy: string implements HasLabel {
    use HasOptions;

    /** Remote überschreibt abweichende lokale Felder. */
    case RemoteWins = 'remote_wins';

    /** Lokale Werte bleiben unangetastet (kein Update). */
    case LocalWins = 'local_wins';

    /** Abweichung wird als conflict-Item in die Inbox geschrieben. */
    case ManualReview = 'manual';

    public function label(): string {
        return match ($this) {
            self::RemoteWins => (string) __('Remote gewinnt'),
            self::LocalWins => (string) __('Lokal gewinnt'),
            self::ManualReview => (string) __('Manuelle Prüfung (Inbox)'),
        };
    }

    public static function fromSetting(?string $value): self {
        return self::tryFrom((string) $value) ?? self::ManualReview;
    }
}
