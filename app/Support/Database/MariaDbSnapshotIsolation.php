<?php
/*
 * Created on   : Mon Oct 19 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : MariaDbSnapshotIsolation.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Support\Database;

use Illuminate\Support\Facades\Log;
use PDO;
use PDOException;

/**
 * Schaltet `innodb_snapshot_isolation` je Datenbanksitzung ab.
 *
 * MariaDB kennt die Variable seit 10.6.18 / 10.11.8 / 11.0.6 / 11.1.5 /
 * 11.2.4 / 11.4.2 (MDEV-26643) und schaltet sie seit 11.6.2 standardmäßig
 * ein (MDEV-35124). Eingeschaltet scheitert unter REPEATABLE READ jede
 * sperrende Leseabfrage (`FOR UPDATE`, `UPDATE`) mit Fehler 1020 „Record has
 * changed since last read", sobald eine andere Transaktion die Zeile nach
 * dem ersten Lesen dieser Transaktion geändert und committet hat — statt wie
 * bisher auf die Sperre zu warten und den jüngsten Stand zu liefern.
 *
 * Die App ist auf das Warten gebaut: Kettenkopf der Audit-Kette,
 * Nummernkreise, Lagerbestände lesen erst ungesperrt und sperren dann.
 * Befund 2026-10-19 im Produktivlog (MariaDB 11.8): Speichern einer
 * Abrechnungsvereinbarung brach beim Audit-Insert ab, während ein zweiter
 * Prozess dieselbe Kette fortschrieb.
 */
final class MariaDbSnapshotIsolation {
    /** Erste Version je Zweig, die die Variable kennt; ab {@see INTRODUCED_ALL_BRANCHES} jeder Zweig. */
    private const INTRODUCED = [
        '10.6' => '10.6.18',
        '10.11' => '10.11.8',
        '11.0' => '11.0.6',
        '11.1' => '11.1.5',
        '11.2' => '11.2.4',
    ];

    private const INTRODUCED_ALL_BRANCHES = '11.4.2';

    /**
     * MariaDB-Version aus der Versionskennung des Handshakes
     * (`5.5.5-10.11.14-MariaDB-0ubuntu0.24.04.1`, `11.8.3-MariaDB-log`);
     * `null` für MySQL und andere Server.
     */
    public static function serverVersion(string $handshake): ?string {
        return preg_match('/(\d+\.\d+\.\d+)-MariaDB/i', $handshake, $match) === 1 ? $match[1] : null;
    }

    /** Kennt der Server die Variable? Ältere Zweige ohne sie (10.7–10.10, 11.3) liefern false. */
    public static function isKnownTo(string $handshake): bool {
        $version = self::serverVersion($handshake);
        if ($version === null) {
            return false;
        }
        if (version_compare($version, self::INTRODUCED_ALL_BRANCHES, '>=')) {
            return true;
        }

        $branch = implode('.', array_slice(explode('.', $version), 0, 2));
        $introduced = self::INTRODUCED[$branch] ?? null;

        return $introduced !== null && version_compare($version, $introduced, '>=');
    }

    /**
     * Schaltet die Variable für diese Sitzung ab, wenn der Server sie kennt.
     * Liefert true, wenn die Anweisung ausgeführt wurde.
     */
    public static function disable(PDO $pdo): bool {
        $handshake = (string) $pdo->getAttribute(PDO::ATTR_SERVER_VERSION);
        if (! self::isKnownTo($handshake)) {
            return false;
        }

        try {
            $pdo->exec('SET SESSION innodb_snapshot_isolation = OFF');
        } catch (PDOException $e) {
            // Die Verbindung darf daran nicht scheitern: ohne die Einstellung
            // läuft die App, nur sperrende Lesezugriffe können bei Konkurrenz
            // mit Fehler 1020 abbrechen.
            Log::warning('innodb_snapshot_isolation konnte nicht abgeschaltet werden', [
                'server' => $handshake,
                'error' => $e->getMessage(),
            ]);

            return false;
        }

        return true;
    }
}
