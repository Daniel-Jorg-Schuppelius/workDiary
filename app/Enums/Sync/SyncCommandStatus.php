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
 *
 * `conflict` ist seit dem Offline-Nachtrag von Stempelzeiten real
 * (Audit 2026-08, W4.1): `attendance.correct` vergleicht `base_version`
 * gegen {@see \App\Models\Attendance::correctionVersion()}. Der Unterschied
 * zu `rejected` ist keine Nuance — eine Ablehnung ist endgültig und darf
 * erneut gesendet werden, ein Konflikt verlangt vorher eine Entscheidung des
 * Nutzers (fremden Stand übernehmen oder die eigene Fassung durchsetzen).
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
