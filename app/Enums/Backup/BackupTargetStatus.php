<?php
/*
 * Created on   : Tue Jul 14 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : BackupTargetStatus.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Enums\Backup;

use App\Enums\Concerns\HasOptions;
use App\Enums\Contracts\HasLabel;

/**
 * Lebenszyklus einer Backupziel-Verbindung (Phase 32): fehlende Scopes ⇒
 * `blocked` statt teilaktiv; Token-Probleme ⇒ `reauth_required`.
 */
enum BackupTargetStatus: string implements HasLabel {
    use HasOptions;

    case Draft = 'draft';
    case Active = 'active';
    case ReauthRequired = 'reauth_required';
    case Blocked = 'blocked';
    case Disabled = 'disabled';

    public function label(): string {
        return (string) __('enums.backup.target_status.' . $this->value);
    }

    /** DaisyUI badge tone */
    public function tone(): string {
        return match ($this) {
            self::Active => 'success',
            self::Draft => 'ghost',
            self::ReauthRequired => 'warning',
            self::Blocked => 'error',
            self::Disabled => 'neutral',
        };
    }

    public function isRunnable(): bool {
        return $this === self::Active;
    }
}
