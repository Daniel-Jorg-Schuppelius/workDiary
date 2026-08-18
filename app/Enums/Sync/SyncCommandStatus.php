<?php
/*
 * Created on   : Tue Jul 14 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : SyncCommandStatus.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Enums\Sync;

use App\Enums\Concerns\HasOptions;
use App\Enums\Contracts\HasLabel;

/**
 * Ergebnis eines Offline-Sync-Befehls (offline-sync-architektur.md §3.2).
 * `conflict` ist für Phase 3 (base_version-Vergleich) reserviert — der
 * MVP-Scope (append-only Befehle) erzeugt nur applied/duplicate/rejected.
 */
enum SyncCommandStatus: string implements HasLabel {
    use HasOptions;

    case Applied = 'applied';
    case Duplicate = 'duplicate';
    case Conflict = 'conflict';
    case Rejected = 'rejected';

    public function label(): string {
        return (string) __('enums.sync_command.status.' . $this->value);
    }

    /** DaisyUI badge tone */
    public function tone(): string {
        return match ($this) {
            self::Applied => 'success',
            self::Duplicate => 'neutral',
            self::Conflict => 'warning',
            self::Rejected => 'error',
        };
    }
}
