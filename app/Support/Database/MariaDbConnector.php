<?php
/*
 * Created on   : Mon Oct 19 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : MariaDbConnector.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Support\Database;

use Illuminate\Database\Connectors\MariaDbConnector as BaseMariaDbConnector;
use PDO;

/**
 * Connector des Treibers `mariadb` (Bindung `db.connector.mariadb`), siehe
 * {@see MySqlConnector}.
 */
final class MariaDbConnector extends BaseMariaDbConnector {
    /** @param array<string, mixed> $config */
    public function connect(array $config): PDO {
        $pdo = parent::connect($config);
        MariaDbSnapshotIsolation::disable($pdo);

        return $pdo;
    }
}
