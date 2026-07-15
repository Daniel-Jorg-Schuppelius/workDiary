<?php
/*
 * Created on   : Tue Jul 14 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : BackupRetentionClass.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Enums\Backup;

use App\Enums\Concerns\HasOptions;
use App\Enums\Contracts\HasLabel;

/**
 * Zeitklasse einer Generation (Phase 32): Standard-Retention behält
 * 7 tägliche, 4 wöchentliche und 12 monatliche Generationen.
 */
enum BackupRetentionClass: string implements HasLabel {
    use HasOptions;

    case Daily = 'daily';
    case Weekly = 'weekly';
    case Monthly = 'monthly';

    public function label(): string {
        return (string) __('enums.backup.retention_class.' . $this->value);
    }

    public function keepCount(): int {
        return match ($this) {
            self::Daily => (int) config('backup_targets.retention.daily', 7),
            self::Weekly => (int) config('backup_targets.retention.weekly', 4),
            self::Monthly => (int) config('backup_targets.retention.monthly', 12),
        };
    }
}
