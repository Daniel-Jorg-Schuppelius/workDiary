<?php
/*
 * Created on   : Tue Sep 29 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : MirrorConflictActions.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Plugins\Support\Mirror;

use App\Models\Integration\IntegrationInboxItem;
use App\Support\Ui\UiAction;

/**
 * Ablage-Spiegelkonflikt (Feature 058 Rang 18 / MVP-330): Datei-Divergenz,
 * kein Feld-Diff. Routen `admin.<id>.conflict.*`, Texte `<id>.conflict.*`.
 */
trait MirrorConflictActions {
    public function inboxConflictActions(IntegrationInboxItem $item): array {
        $id = static::ID;

        return [
            new UiAction('upload', (string) __($id . '.conflict.action.overwrite'), route('admin.' . $id . '.conflict.overwrite', $item), post: true, tone: 'primary', confirm: (string) __($id . '.conflict.confirm.overwrite')),
            new UiAction('download', (string) __($id . '.conflict.action.import'), route('admin.' . $id . '.conflict.import', $item), post: true, tone: 'outline', confirm: (string) __($id . '.conflict.confirm.import')),
            new UiAction('link_off', (string) __($id . '.conflict.action.detach'), route('admin.' . $id . '.conflict.detach', $item), post: true, confirm: (string) __($id . '.conflict.confirm.detach')),
        ];
    }

    public function replacesDefaultConflictActions(IntegrationInboxItem $item): bool {
        return true;
    }
}
