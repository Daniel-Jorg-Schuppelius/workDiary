<?php
/*
 * Created on   : Wed Nov 11 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : SyncConflictResolution.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Enums\Sync;

use App\Enums\Concerns\HasOptions;
use App\Enums\Contracts\HasLabel;

/**
 * Entscheidung über einen Offline-Konflikt (MVP-1595): den Stand auf dem
 * Server stehen lassen, die eigene Erfassung verwerfen oder sie — unverändert
 * oder bearbeitet — als neuen Befehl erneut senden.
 */
enum SyncConflictResolution: string implements HasLabel {
    use HasOptions;

    case Server = 'server';
    case Discarded = 'discarded';
    case Resent = 'resent';

    public function label(): string {
        return (string) __('enums.sync_command.resolution.' . $this->value);
    }
}
