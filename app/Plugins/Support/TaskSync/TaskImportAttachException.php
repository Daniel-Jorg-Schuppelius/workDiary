<?php
/*
 * Created on   : Fri Oct 23 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : TaskImportAttachException.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Plugins\Support\TaskSync;

use RuntimeException;

/**
 * Ausdrückliches Verknüpfen abgelehnt; `reason` ist zugleich der Textschlüssel
 * `integration.task_import.attach_refused.<reason>`.
 */
final class TaskImportAttachException extends RuntimeException {
    public function __construct(public readonly string $reason) {
        parent::__construct('Verknüpfen abgelehnt: ' . $reason);
    }
}
