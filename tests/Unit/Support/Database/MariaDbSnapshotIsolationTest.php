<?php
/*
 * Created on   : Mon Oct 19 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : MariaDbSnapshotIsolationTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace Tests\Unit\Support\Database;

use App\Support\Database\MariaDbSnapshotIsolation;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

final class MariaDbSnapshotIsolationTest extends TestCase {
    /** @return array<string, array{string, ?string}> */
    public static function handshakes(): array {
        return [
            'ubuntu 10.11 mit 5.5.5-Präfix' => ['5.5.5-10.11.14-MariaDB-0ubuntu0.24.04.1', '10.11.14'],
            '11.8 ohne Präfix'              => ['11.8.3-MariaDB-log', '11.8.3'],
            '11.8 mit Präfix'               => ['5.5.5-11.8.3-MariaDB', '11.8.3'],
            'mysql 8.0'                     => ['8.0.36', null],
            'mysql 8.4 log'                 => ['8.4.2-log', null],
            'leer'                          => ['', null],
        ];
    }

    #[DataProvider('handshakes')]
    public function test_server_version_is_read_from_the_handshake(string $handshake, ?string $expected): void {
        $this->assertSame($expected, MariaDbSnapshotIsolation::serverVersion($handshake));
    }

    /** @return array<string, array{string, bool}> */
    public static function knownVersions(): array {
        return [
            '10.6.17 davor'        => ['5.5.5-10.6.17-MariaDB', false],
            '10.6.18 eingeführt'   => ['5.5.5-10.6.18-MariaDB', true],
            '10.6.22'              => ['5.5.5-10.6.22-MariaDB-ubu2204', true],
            '10.10.7 nie'          => ['5.5.5-10.10.7-MariaDB', false],
            '10.11.7 davor'        => ['5.5.5-10.11.7-MariaDB', false],
            '10.11.8 eingeführt'   => ['5.5.5-10.11.8-MariaDB', true],
            '10.11.14 lokal'       => ['5.5.5-10.11.14-MariaDB-0ubuntu0.24.04.1', true],
            '11.0.5 davor'         => ['11.0.5-MariaDB', false],
            '11.0.6 eingeführt'    => ['11.0.6-MariaDB', true],
            '11.1.4 davor'         => ['11.1.4-MariaDB', false],
            '11.1.5 eingeführt'    => ['11.1.5-MariaDB', true],
            '11.2.3 davor'         => ['11.2.3-MariaDB', false],
            '11.2.4 eingeführt'    => ['11.2.4-MariaDB', true],
            '11.3.2 nie'           => ['11.3.2-MariaDB', false],
            '11.4.1 davor'         => ['11.4.1-MariaDB', false],
            '11.4.2 eingeführt'    => ['11.4.2-MariaDB', true],
            '11.6.2 standard an'   => ['11.6.2-MariaDB', true],
            '11.8.3 produktiv'     => ['11.8.3-MariaDB-log', true],
            '12.0.1'               => ['12.0.1-MariaDB', true],
            'mysql 8.0'            => ['8.0.36', false],
            'mysql 9.1'            => ['9.1.0', false],
        ];
    }

    #[DataProvider('knownVersions')]
    public function test_knows_which_servers_have_the_variable(string $handshake, bool $expected): void {
        $this->assertSame($expected, MariaDbSnapshotIsolation::isKnownTo($handshake));
    }
}
