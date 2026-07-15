<?php
/*
 * Created on   : Tue Jul 14 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : BackupGenerationStatus.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Enums\Backup;

use App\Enums\Concerns\HasOptions;
use App\Enums\Contracts\HasLabel;

/**
 * Lebenszyklus einer Backup-Generation (Phase 32): ohne gültiges
 * Commit-Manifest (`committed`) gilt eine Generation als unvollständig und
 * nicht restorable; `verified` setzt eine erfolgreiche Stichproben- bzw.
 * Restore-Test-Verifikation voraus.
 */
enum BackupGenerationStatus: string implements HasLabel {
    use HasOptions;

    case Building = 'building';
    case Uploading = 'uploading';
    case Committed = 'committed';
    case Verified = 'verified';
    case VerifyFailed = 'verify_failed';
    case Failed = 'failed';

    public function label(): string {
        return (string) __('enums.backup.generation_status.' . $this->value);
    }

    /** DaisyUI badge tone */
    public function tone(): string {
        return match ($this) {
            self::Verified => 'success',
            self::Committed => 'info',
            self::Building, self::Uploading => 'warning',
            self::VerifyFailed, self::Failed => 'error',
        };
    }

    /** Retention darf nur vollständig verifizierte Generationen löschen. */
    public function isDeletableByRetention(): bool {
        return $this === self::Verified;
    }
}
