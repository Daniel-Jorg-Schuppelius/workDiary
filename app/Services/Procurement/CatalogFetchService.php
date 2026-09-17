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
use App\Plugins\Support\PluginHttpFactory;
use App\Support\UrlSafety;
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

        // SSRF-Laufzeit-Guard (auch gegen DNS-Rebinding/Altbestand): nie interne/private/reservierte Ziele abrufen.
        // Redirects bleiben deaktiviert, damit ein externer Server nicht auf interne Ziele weiterleitet.
        if (! UrlSafety::isPubliclyRoutableHttpUrl($url)) {
            throw new RuntimeException((string) __('procurement.catalog.error.host_not_allowed'));
        }

        $client = app(PluginHttpFactory::class)->coreClient('catalog-fetch', $url);
        $client->setFollowRedirects(false);
        $client->setTimeout(60.0);
        $client->setDefaultHeaders(['Accept' => '*/*']);
        $options = [];
        $username = trim((string) $source->remote_username);
        if ($username !== '') {
            $options['auth'] = [$username, (string) $source->remote_password];
        }

        $response = $client->getResponse($url, [], $options);
        if (! $response->successful()) {
            throw new RuntimeException(sprintf('%s: HTTP %d', (string) __('procurement.catalog.error.fetch_failed'), $response->status()));
        }

        return $response->body();
    }

    private function ftp(SupplierCatalogSource $source): string {
        $this->requireHostPath($source);

        return $this->read(new FtpAdapter($this->ftpOptions($source)), (string) $source->remote_path);
    }

    /**
     * Die Verbindungsvorgaben getrennt vom Verbindungsaufbau — nur so lässt
     * sich die TLS-Vorgabe prüfen, ohne einen FTP-Server zu betreiben
     * (Regressionsnetz zum Sicherheitsaudit 2026-09-13, `crypto-4`).
     */
    private function ftpOptions(SupplierCatalogSource $source): FtpConnectionOptions {
        return FtpConnectionOptions::fromArray([
            'host' => (string) $source->remote_host,
            'root' => '/',
            'username' => (string) $source->remote_username,
            'password' => (string) $source->remote_password,
            'port' => $source->remote_port ?: 21,
            // Sicherheitsaudit 2026-09-13: Ohne TLS gehen die hinterlegten
            // Lieferanten-Zugangsdaten im Klartext ueber die Leitung. Klartext
            // nur noch, wenn der Betreiber ihn bewusst freischaltet.
            'ssl' => ! (bool) config('procurement.ftp_allow_plaintext', false),
            'timeout' => 30,
            'passive' => true,
            // Sicherheitsaudit 2026-09-17 (ssrf-3): die PASV-Antwort des Servers
            // nennt IP:Port des Datenkanals — ohne diese Option verbindet sich der
            // App-Server dorthin, auch auf 127.0.0.1 oder interne Netze. So gilt
            // die bereits geprüfte Adresse der Steuerverbindung.
            'ignorePassiveAddress' => true,
        ]);
    }

    private function sftp(SupplierCatalogSource $source): string {
        $this->requireHostPath($source);

        $password = (string) $source->remote_password;
        $fingerprint = trim((string) ($source->remote_host_fingerprint ?? ''));

        // Ohne Host-Key keine Verbindung (Sicherheitsscan 2026-08-23, S-22):
        // sonst authentifiziert sich die Anwendung bei jedem Server, der die
        // Ziel-IP beantwortet — mit Benutzername und Passwort im Gepäck.
        if ($fingerprint === '') {
            throw new RuntimeException((string) __('procurement.catalog.error.fingerprint_missing'));
        }

        $provider = new SftpConnectionProvider(
            host: (string) $source->remote_host,
            username: (string) $source->remote_username,
            password: $password !== '' ? $password : null,
            port: $source->remote_port ?: 22,
            hostFingerprint: $fingerprint,
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

        // SSRF-Laufzeit-Guard für FTP/SFTP: kein Abruf gegen interne Ziele.
        if (! UrlSafety::isPubliclyRoutableHost((string) $source->remote_host)) {
            throw new RuntimeException((string) __('procurement.catalog.error.host_not_allowed'));
        }
    }
}
