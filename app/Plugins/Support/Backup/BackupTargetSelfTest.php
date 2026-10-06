<?php
/*
 * Created on   : Sun Oct 04 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : BackupTargetSelfTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Plugins\Support\Backup;

use App\Models\Backup\BackupTargetConnection;
use App\Plugins\Contracts\BackupTarget;
use CommonToolkit\Helper\FileSystem\File;
use RuntimeException;

/**
 * Probe vor dem Aktivieren eines Backupziels: schreiben, lesen, löschen —
 * über den Vertrag {@see BackupTarget}, also für jedes Ziel gleich
 * (Konsolidierungs-Audit 2026-10, k2-04). Ein Ziel, das erst im Ernstfall als
 * unbrauchbar auffällt, ist schlimmer als keins: Nextcloud und die drei
 * OAuth-Ziele wurden bisher nach Konto, Kontingent und Ordner aktiv, ohne je
 * eine Datei gelesen oder gelöscht zu haben.
 */
final class BackupTargetSelfTest {
    public const PAYLOAD = 'workdiary-selftest';

    /** @throws RuntimeException wenn das Ziel die Probe nicht besteht */
    public function run(BackupTarget $target, BackupTargetConnection $connection, string $folder): void {
        $name = trim($folder, '/') . '/.wd-selftest-' . bin2hex(random_bytes(6)) . '.bin';
        $ref = File::withTemp(self::PAYLOAD, static fn (string $path): string => $target->backupUploadPart($connection, $path, $name));

        try {
            if ((string) $target->backupDownload($connection, $ref) !== self::PAYLOAD) {
                throw new RuntimeException('Backup-Testdatei kam verändert zurück.');
            }
        } finally {
            // Aufräumen gehört zur Probe: ein Ziel, das nicht löschen kann, taugt nicht für die Retention.
            if (! $target->backupDelete($connection, $ref)) {
                throw new RuntimeException('Backup-Testdatei konnte nicht gelöscht werden.');
            }
        }
    }
}
