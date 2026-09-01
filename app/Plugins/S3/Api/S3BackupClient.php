<?php
/*
 * Created on   : Tue Sep 01 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : S3BackupClient.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Plugins\S3\Api;

use App\Models\Backup\BackupTargetConnection;
use App\Plugins\Support\Backup\{BackupAccount, BackupRemoteObject};
use Aws\S3\Exception\S3Exception;
use Aws\S3\{MultipartUploader, S3Client};
use GuzzleHttp\Psr7\Utils;
use Psr\Http\Message\StreamInterface;
use RuntimeException;

/**
 * S3-kompatibler Objektspeicher als Backupziel (Feature 123, MVP-726).
 *
 * Deckt AWS S3 und alles ab, was dessen API spricht — MinIO, Wasabi, Hetzner,
 * Scaleway. Der Unterschied zwischen ihnen ist die Adressierung: AWS setzt den
 * Bucket in die Subdomain, die meisten Selbstbetriebenen in den Pfad
 * (`path_style`). Beides ist konfigurierbar, geraten wird nichts.
 *
 * **Ordner gibt es hier nicht.** S3 kennt nur Schlüssel; „Ordner" sind eine
 * Anzeigekonvention über `/`. `ensureFolder()` legt deshalb nichts an, sondern
 * normalisiert das Präfix — ein leeres Objekt als Ordnermarke wäre Ballast,
 * den niemand aufräumt.
 *
 * Hochgeladen wird ausschließlich Ciphertext ({@see \App\Services\Backup\BackupCrypter});
 * dieser Client sieht nie Klartext.
 */
class S3BackupClient {
    /** Ab dieser Größe geht der Upload in Teilen (S3-Mindestteil: 5 MiB). */
    private const MULTIPART_THRESHOLD = 16 * 1024 * 1024;

    private readonly S3Client $s3;

    public function __construct(
        private readonly BackupTargetConnection $connection,
        ?S3Client $client = null,
    ) {
        $this->s3 = $client ?? $this->makeClient();
    }

    /** Kontoidentität: Bucket + Endpoint, mehr weist S3 nicht aus. */
    public function account(): BackupAccount {
        $bucket = $this->bucket();

        return new BackupAccount(
            externalId: $bucket,
            label: $bucket . ' @ ' . ($this->connection->server_url ?? 'AWS'),
        );
    }

    /**
     * S3 kennt keine Kontingente — weder Gesamtgröße noch Belegung.
     *
     * Bewusst `null` statt einer aufsummierten Objektgröße: Ein
     * `ListObjects` über einen großen Bucket kostet Zeit und Geld und sagt
     * nichts über das Limit aus, das es nicht gibt.
     *
     * @return array{total: int|null, used: int|null}
     */
    public function quota(): array {
        return ['total' => null, 'used' => null];
    }

    /** Normalisiert das Präfix; legt nichts an (S3 hat keine Ordner). */
    public function ensureFolder(string $path): string {
        $prefix = trim($path, '/');

        // Der Bucket muss existieren — ihn anzulegen ist nicht unsere Aufgabe:
        // Region, Verschlüsselung und Aufbewahrungsregeln gehören dem Betreiber.
        $this->guard(fn () => $this->s3->headBucket(['Bucket' => $this->bucket()]), 'Bucket nicht erreichbar');

        return $prefix;
    }

    /** @return list<BackupRemoteObject> */
    public function listObjects(string $prefix): array {
        $prefix = trim($prefix, '/');
        $prefix = $prefix === '' ? '' : $prefix . '/';

        $objects = [];
        $token = null;

        do {
            $result = $this->guard(fn () => $this->s3->listObjectsV2(array_filter([
                'Bucket' => $this->bucket(),
                'Prefix' => $prefix,
                'ContinuationToken' => $token,
            ], static fn ($v): bool => $v !== null && $v !== '')), 'Auflisten fehlgeschlagen');

            /** @var list<array<string, mixed>> $contents */
            $contents = (array) ($result['Contents'] ?? []);
            foreach ($contents as $item) {
                $key = (string) ($item['Key'] ?? '');
                if ($key === '' || str_ends_with($key, '/')) {
                    continue;
                }
                $objects[] = new BackupRemoteObject(
                    ref: $key,
                    name: basename($key),
                    size: (int) ($item['Size'] ?? 0),
                );
            }

            $token = ($result['IsTruncated'] ?? false) ? (string) ($result['NextContinuationToken'] ?? '') : null;
        } while ($token !== null && $token !== '');

        return $objects;
    }

    /**
     * Lädt eine bereits verschlüsselte Datei hoch und prüft die Remote-Größe.
     *
     * Große Dateien gehen über `MultipartUploader` — der nimmt einen
     * abgebrochenen Upload nicht automatisch wieder auf, wiederholt aber
     * einzelne Teile statt der ganzen Datei. Die Größenprüfung danach ist der
     * eigentliche Nachweis: ein Ziel, das stillschweigend kürzt, fällt sonst
     * erst beim Wiederherstellen auf.
     */
    public function upload(string $localPath, string $remoteName): string {
        if (! is_file($localPath)) {
            throw new RuntimeException('Lokale Backup-Datei fehlt: ' . basename($localPath));
        }

        $key = $this->key($remoteName);
        $size = (int) filesize($localPath);

        if ($size >= self::MULTIPART_THRESHOLD) {
            $this->guard(function () use ($localPath, $key): void {
                (new MultipartUploader($this->s3, $localPath, [
                    'bucket' => $this->bucket(),
                    'key' => $key,
                ]))->upload();
            }, 'Mehrteiliger Upload fehlgeschlagen');
        } else {
            $this->guard(fn () => $this->s3->putObject([
                'Bucket' => $this->bucket(),
                'Key' => $key,
                'SourceFile' => $localPath,
            ]), 'Upload fehlgeschlagen');
        }

        $remoteSize = $this->size($key);
        if ($remoteSize !== $size) {
            throw new RuntimeException(sprintf(
                'Remote-Größe weicht ab (%d statt %d Bytes): %s',
                $remoteSize,
                $size,
                $remoteName,
            ));
        }

        return $key;
    }

    public function download(string $remoteRef): StreamInterface {
        $result = $this->guard(fn () => $this->s3->getObject([
            'Bucket' => $this->bucket(),
            'Key' => $remoteRef,
        ]), 'Download fehlgeschlagen');

        $body = $result['Body'] ?? null;

        return $body instanceof StreamInterface ? $body : Utils::streamFor((string) $body);
    }

    /** Idempotent: S3 meldet auch das Löschen eines fehlenden Schlüssels als Erfolg. */
    public function delete(string $remoteRef): bool {
        $this->guard(fn () => $this->s3->deleteObject([
            'Bucket' => $this->bucket(),
            'Key' => $remoteRef,
        ]), 'Löschen fehlgeschlagen');

        return true;
    }

    /**
     * Schreiben, lesen, löschen — bevor das Ziel als brauchbar gilt.
     *
     * Ein Backupziel, das erst im Ernstfall auffällt, ist schlimmer als
     * keins: Leserechte allein genügen nicht, und ein Bucket mit
     * Objektsperre nimmt zwar Schreibvorgänge an, lässt aber die Aufräumung
     * scheitern.
     */
    public function selfTest(string $prefix): void {
        $key = trim($prefix, '/');
        $key = ($key === '' ? '' : $key . '/') . '.workdiary-selftest-' . bin2hex(random_bytes(6));
        $payload = 'workdiary-selftest';

        $this->guard(fn () => $this->s3->putObject([
            'Bucket' => $this->bucket(),
            'Key' => $key,
            'Body' => $payload,
        ]), 'Schreibtest fehlgeschlagen');

        $result = $this->guard(fn () => $this->s3->getObject([
            'Bucket' => $this->bucket(),
            'Key' => $key,
        ]), 'Lesetest fehlgeschlagen');

        if ((string) ($result['Body'] ?? '') !== $payload) {
            throw new RuntimeException('Lesetest lieferte abweichenden Inhalt.');
        }

        $this->guard(fn () => $this->s3->deleteObject([
            'Bucket' => $this->bucket(),
            'Key' => $key,
        ]), 'Löschtest fehlgeschlagen');
    }

    private function size(string $key): int {
        $result = $this->guard(fn () => $this->s3->headObject([
            'Bucket' => $this->bucket(),
            'Key' => $key,
        ]), 'Größenprüfung fehlgeschlagen');

        return (int) ($result['ContentLength'] ?? -1);
    }

    private function key(string $remoteName): string {
        $prefix = trim((string) ($this->connection->root_folder_ref ?? ''), '/');

        return $prefix === '' ? ltrim($remoteName, '/') : $prefix . '/' . ltrim($remoteName, '/');
    }

    private function bucket(): string {
        $bucket = (string) (($this->connection->options ?? [])['bucket'] ?? '');
        if ($bucket === '') {
            throw new RuntimeException('Kein Bucket konfiguriert.');
        }

        return $bucket;
    }

    private function makeClient(): S3Client {
        $options = (array) ($this->connection->options ?? []);
        $endpoint = trim((string) ($this->connection->server_url ?? ''));

        $config = [
            'version' => '2006-03-01',
            'region' => (string) ($options['region'] ?? 'us-east-1'),
            'credentials' => [
                'key' => (string) ($this->connection->username ?? ''),
                'secret' => (string) ($this->connection->access_token ?? ''),
            ],
            // MinIO & Co. adressieren den Bucket im Pfad, AWS in der Subdomain.
            'use_path_style_endpoint' => (bool) ($options['path_style'] ?? false),
        ];

        if ($endpoint !== '') {
            $config['endpoint'] = $endpoint;
        }

        return new S3Client($config);
    }

    /**
     * Übersetzt SDK-Fehler in RuntimeException — ohne Zugangsdaten.
     *
     * Die Meldung eines S3Exception enthält die vollständige Anfrage samt
     * Signaturkopf; sie gehört nicht ins Protokoll und nicht in eine
     * Oberfläche.
     *
     * @template T
     *
     * @param  callable(): T  $call
     * @return T
     */
    private function guard(callable $call, string $message): mixed {
        try {
            return $call();
        } catch (S3Exception $e) {
            throw new RuntimeException($message . ' (' . $e->getAwsErrorCode() . ')', 0, $e);
        }
    }
}
