<?php
/*
 * Created on   : Fri Oct 23 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : TaskImportBusyException.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Plugins\Support\TaskSync;

use RuntimeException;

/** Ein anderer Lauf derselben Organisation und desselben Anbieters ist noch aktiv. */
final class TaskImportBusyException extends RuntimeException {
    public static function for(string $pluginId, int $organizationId): self {
        return new self(sprintf('Aufgabenimport %s für Organisation #%d läuft bereits.', $pluginId, $organizationId));
    }
}
