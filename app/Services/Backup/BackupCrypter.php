<?php
/*
 * Created on   : Tue Jul 14 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : BackupCrypter.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Services\Backup;

use App\Services\Backup\Support\SecretStreamFile;
use SensitiveParameter;

/**
 * Verschlüsselungsseite der Cloud-Backups (Feature 017 Phase 32, MVP-362).
 *
 * Je Snapshot: zufälliger Datenschlüssel → jeder Teil wird per
 * secretstream (XChaCha20-Poly1305, AD = `<uuid>/part-<n>`) verschlüsselt;
 * das Manifest wie ein Teil (AD = `<uuid>/manifest`). Das Commit-Dokument
 * bündelt Envelopes + verschlüsseltes Manifest + Ed25519-Signatur — ohne
 * gültiges Commit gilt die Generation als nicht restorable.
 * Gegenstück: {@see BackupDecrypter}.
 */
class BackupCrypter {
    public const COMMIT_VERSION = 1;

    public function __construct(
        private readonly BackupKeyring $keyring,
        private readonly SecretStreamFile $stream,
    ) {}

    public function encryptPart(string $plainPath, string $cipherPath, #[SensitiveParameter] string $dataKey, string $snapshotUuid, int $partNo): void {
        $this->stream->encrypt($plainPath, $cipherPath, $dataKey, self::partAd($snapshotUuid, $partNo));
    }

    /**
     * Baut das signierte Commit-Dokument (Inhalt der Remote-Datei
     * `commit.manifest`) und liefert es zusammen mit den Envelopes.
     *
     * @param array<string, mixed> $manifest
     * @return array{document: string, key_envelope: string, recovery_envelope: string|null, manifest_sha256: string}
     */
    public function buildCommitDocument(array $manifest, #[SensitiveParameter] string $dataKey, string $snapshotUuid): array {
        $plain = json_encode($manifest, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

        $plainPath = tempnam(sys_get_temp_dir(), 'wd-manifest-');
        $cipherPath = tempnam(sys_get_temp_dir(), 'wd-manifest-');
        if ($plainPath === false || $cipherPath === false) {
            throw new \RuntimeException('Temporäre Manifest-Datei konnte nicht angelegt werden.');
        }

        try {
            file_put_contents($plainPath, $plain);
            $this->stream->encrypt($plainPath, $cipherPath, $dataKey, self::manifestAd($snapshotUuid));
            $cipher = (string) file_get_contents($cipherPath);
        } finally {
            @unlink($plainPath);
            @unlink($cipherPath);
        }

        $envelopes = $this->keyring->wrapDataKey($dataKey);
        $document = json_encode([
            'version' => self::COMMIT_VERSION,
            'snapshot_uuid' => $snapshotUuid,
            'key_envelope' => $envelopes['key_envelope'],
            'recovery_envelope' => $envelopes['recovery_envelope'],
            'manifest' => base64_encode($cipher),
            'signature' => $this->keyring->signCommit($cipher),
        ], JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES);

        return [
            'document' => $document,
            'key_envelope' => $envelopes['key_envelope'],
            'recovery_envelope' => $envelopes['recovery_envelope'],
            'manifest_sha256' => hash('sha256', $cipher),
        ];
    }

    public static function partAd(string $snapshotUuid, int $partNo): string {
        return $snapshotUuid . '/part-' . $partNo;
    }

    public static function manifestAd(string $snapshotUuid): string {
        return $snapshotUuid . '/manifest';
    }
}
