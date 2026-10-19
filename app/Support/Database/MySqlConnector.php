<?php
/*
 * Created on   : Mon Oct 19 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : MySqlConnector.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Support\Database;

use Illuminate\Database\Connectors\MySqlConnector as BaseMySqlConnector;
use PDO;

/**
 * Connector des Treibers `mysql` (Bindung `db.connector.mysql`): jede neue
 * PDO-Verbindung — auch nach einem Reconnect — läuft ohne
 * {@see MariaDbSnapshotIsolation}. Der Connector ist der einzige Ort, durch
 * den alle Verbindungen gehen; `ConnectionEstablished` feuert bei
 * `DB::reconnect()` nicht.
 */
final class MySqlConnector extends BaseMySqlConnector {
    /** @param array<string, mixed> $config */
    public function connect(array $config): PDO {
        $pdo = parent::connect($config);
        MariaDbSnapshotIsolation::disable($pdo);

        return $pdo;
    }
}
