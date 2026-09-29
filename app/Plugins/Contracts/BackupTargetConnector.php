<?php
/*
 * Created on   : Tue Sep 29 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : BackupTargetConnector.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Plugins\Contracts;

use App\Models\Backup\BackupTargetConnection;
use App\Support\Ui\UiAction;

/** Verbinden und Neu-Verbinden eines Backup-Ziels in der Verwaltung (MVP-1041). */
interface BackupTargetConnector {
    /** Eintrag im Menü „Neu“ der Backup-Ziele. */
    public function backupConnectAction(): UiAction;

    /** „Neu verbinden“ einer bestehenden Verbindung dieses Anbieters. */
    public function backupReconnectAction(BackupTargetConnection $connection): UiAction;
}
