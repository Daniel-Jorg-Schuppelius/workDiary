<?php
/*
 * Created on   : Sat Jun 27 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : CatalogFetchService.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Procurement;

use App\Models\SupplierCatalogSource;
use Illuminate\Support\Facades\Http;
use League\Flysystem\{Filesystem, FilesystemAdapter};
use League\Flysystem\Ftp\{FtpAdapter, FtpConnectionOptions};
use League\Flysystem\PhpseclibV3\{SftpAdapter, SftpConnectionProvider};
use RuntimeException;
use Throwable;

/**
 * Ruft die Katalogdatei einer Remote-Quelle ab (Feature 050, MVP-091, „Später").
 * HTTP(S) läuft über den Laravel-HTTP-Client, FTP und SFTP über die einheitliche
 * Flysystem-Abstraktion (league/flysystem-ftp bzw. -sftp-v3). Zugangsdaten werden
 * verschlüsselt am Modell gehalten und niemals protokolliert.
 */
class CatalogFetchService {
    /**
     * @throws RuntimeException Bei fehlender Konfiguration oder Abruf-/Verbindungsfehler.
     */
    public function fetch(SupplierCatalogSource $source): string {
        return match ($source->source_type) {
            'http' => $this->http($source),
            'ftp' => $this->ftp($source),
            'sftp' => $this->sftp($source),
            default => throw new RuntimeException((string) __('procurement.catalog.error.no_remote')),
        };
    }

    private function http(SupplierCatalogSource $source): string {
        $url = trim((string) $source->remote_url);
        if ($url === '') {
            throw new RuntimeException((string) __('procurement.catalog.error.no_remote'));
        }

        $request = Http::timeout(60)->withHeaders(['Accept' => '*/*']);
        $username = trim((string) $source->remote_username);
        if ($username !== '') {
            $request = $request->withBasicAuth($username, (string) $source->remote_password);
        }

        $response = $request->get($url);
        if (! $response->successful()) {
            throw new RuntimeException(sprintf('%s: HTTP %d', (string) __('procurement.catalog.error.fetch_failed'), $response->status()));
        }

        return $response->body();
    }

    private function ftp(SupplierCatalogSource $source): string {
        $this->requireHostPath($source);

        $adapter = new FtpAdapter(FtpConnectionOptions::fromArray([
            'host' => (string) $source->remote_host,
            'root' => '/',
            'username' => (string) $source->remote_username,
            'password' => (string) $source->remote_password,
            'port' => $source->remote_port ?: 21,
            'ssl' => false,
            'timeout' => 30,
            'passive' => true,
        ]));

        return $this->read($adapter, (string) $source->remote_path);
    }

    private function sftp(SupplierCatalogSource $source): string {
        $this->requireHostPath($source);

        $password = (string) $source->remote_password;
        $provider = new SftpConnectionProvider(
            host: (string) $source->remote_host,
            username: (string) $source->remote_username,
            password: $password !== '' ? $password : null,
            port: $source->remote_port ?: 22,
            timeout: 30,
        );

        return $this->read(new SftpAdapter($provider, '/'), (string) $source->remote_path);
    }

    /** Liest eine Datei über die Flysystem-Abstraktion. */
    private function read(FilesystemAdapter $adapter, string $path): string {
        try {
            return (new Filesystem($adapter))->read(ltrim($path, '/'));
        } catch (Throwable) {
            throw new RuntimeException((string) __('procurement.catalog.error.fetch_failed'));
        }
    }

    private function requireHostPath(SupplierCatalogSource $source): void {
        if (trim((string) $source->remote_host) === '' || trim((string) $source->remote_path) === '') {
            throw new RuntimeException((string) __('procurement.catalog.error.no_remote'));
        }
    }
}
