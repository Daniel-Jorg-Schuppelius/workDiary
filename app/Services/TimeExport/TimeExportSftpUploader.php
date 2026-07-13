<?php
/*
 * Created on   : Mon Jul 13 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : TimeExportSftpUploader.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Services\TimeExport;

use App\Models\TimeExportDeliveryConfig;
use Illuminate\Support\Facades\Storage;

/**
 * SFTP-Upload der automatischen Export-Lieferung (A21 · MVP-019).
 *
 * Baut aus der {@see TimeExportDeliveryConfig} eine On-Demand-Disk
 * (league/flysystem-sftp-v3, bereits Projekt-Dependency) und legt die
 * unveränderten Export-Bytes unter dem konfigurierten Zielverzeichnis ab.
 * Als eigener Service gekapselt, damit der {@see \App\Jobs\DeliverTimeExportJob}
 * in Tests gegen einen Fake läuft (kein echter SFTP-Verkehr).
 */
class TimeExportSftpUploader {
    /**
     * Lädt die Datei hoch und liefert die Zielbeschreibung für den
     * Liefernachweis (sftp://user@host:port/pfad/datei).
     */
    public function upload(TimeExportDeliveryConfig $config, string $contents, string $filename): string {
        $root = trim((string) ($config->sftp_root ?? ''));
        $port = $config->sftp_port > 0 ? $config->sftp_port : 22;

        $disk = Storage::build([
            'driver' => 'sftp',
            'host' => (string) $config->sftp_host,
            'port' => $port,
            'username' => (string) $config->sftp_username,
            'password' => (string) ($config->sftp_password ?? ''),
            // Leeres Zielverzeichnis = Home-Verzeichnis des SFTP-Benutzers.
            'root' => $root === '' ? './' : $root,
            'timeout' => 15,
        ]);

        $disk->put($filename, $contents);

        $targetPath = $root === '' ? $filename : rtrim($root, '/') . '/' . $filename;

        return sprintf('sftp://%s@%s:%d/%s', $config->sftp_username, $config->sftp_host, $port, ltrim($targetPath, '/'));
    }
}
