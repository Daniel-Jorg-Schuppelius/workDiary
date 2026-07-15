<?php
/*
 * Created on   : Tue Jul 14 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : BackupDecrypter.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Services\Backup;

use App\Services\Backup\Exceptions\{BackupCommitInvalidException, BackupCryptoException};
use App\Services\Backup\Support\SecretStreamFile;
use SensitiveParameter;

/**
 * Entschlüsselungsseite der Cloud-Backups (Feature 017 Phase 32, MVP-362):
 * liest das signierte Commit-Dokument (Signatur ZUERST prüfen), entpackt den
 * Datenschlüssel (Master-Key-Regelweg oder Recovery-Key-Notfallweg) und
 * entschlüsselt Manifest/Teile. Gegenstück: {@see BackupCrypter}.
 */
class BackupDecrypter {
    public function __construct(
        private readonly BackupKeyring $keyring,
        private readonly SecretStreamFile $stream,
    ) {}

    public function decryptPart(string $cipherPath, string $plainPath, #[SensitiveParameter] string $dataKey, string $snapshotUuid, int $partNo): void {
        $this->stream->decrypt($cipherPath, $plainPath, $dataKey, BackupCrypter::partAd($snapshotUuid, $partNo));
    }

    /**
     * Verifiziert das Commit-Dokument und liefert Manifest + Datenschlüssel.
     * `$recoverySecretKeyB64` schaltet den Notfallweg über den
     * Recovery-Key frei (statt des Master-Keys).
     *
     * @return array{manifest: array<string, mixed>, data_key: string}
     */
    public function openCommitDocument(string $document, #[SensitiveParameter] ?string $recoverySecretKeyB64 = null): array {
        $decoded = json_decode($document, true);
        if (!is_array($decoded)
            || ($decoded['version'] ?? null) !== BackupCrypter::COMMIT_VERSION
            || !is_string($decoded['snapshot_uuid'] ?? null)
            || !is_string($decoded['key_envelope'] ?? null)
            || !is_string($decoded['manifest'] ?? null)
            || !is_string($decoded['signature'] ?? null)) {
            throw new BackupCommitInvalidException('Commit-Manifest ist unlesbar oder hat ein unbekanntes Format.');
        }

        $cipher = base64_decode($decoded['manifest'], true);
        if ($cipher === false) {
            throw new BackupCommitInvalidException('Commit-Manifest ist beschädigt (Base64).');
        }

        if (!$this->keyring->verifyCommitSignature($cipher, $decoded['signature'])) {
            throw new BackupCommitInvalidException(
                'Commit-Signatur ist ungültig — die Generation gilt als nicht restorable.',
            );
        }

        if ($recoverySecretKeyB64 !== null) {
            $recoveryEnvelope = $decoded['recovery_envelope'] ?? null;
            if (!is_string($recoveryEnvelope) || $recoveryEnvelope === '') {
                throw new BackupCryptoException('Diese Generation trägt keinen Recovery-Envelope.');
            }
            $dataKey = $this->keyring->unwrapWithRecoveryKey($recoveryEnvelope, $recoverySecretKeyB64);
        } else {
            $dataKey = $this->keyring->unwrapDataKey($decoded['key_envelope']);
        }

        $cipherPath = tempnam(sys_get_temp_dir(), 'wd-manifest-');
        $plainPath = tempnam(sys_get_temp_dir(), 'wd-manifest-');
        if ($cipherPath === false || $plainPath === false) {
            throw new \RuntimeException('Temporäre Manifest-Datei konnte nicht angelegt werden.');
        }

        try {
            file_put_contents($cipherPath, $cipher);
            $this->stream->decrypt($cipherPath, $plainPath, $dataKey, BackupCrypter::manifestAd($decoded['snapshot_uuid']));
            $manifest = json_decode((string) file_get_contents($plainPath), true);
        } finally {
            @unlink($cipherPath);
            @unlink($plainPath);
        }

        if (!is_array($manifest)) {
            throw new BackupCommitInvalidException('Manifest-Inhalt ist nach der Entschlüsselung unlesbar.');
        }

        /** @var array<string, mixed> $manifest */
        return ['manifest' => $manifest, 'data_key' => $dataKey];
    }
}
