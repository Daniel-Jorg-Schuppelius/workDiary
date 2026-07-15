<?php
/*
 * Created on   : Tue Jul 14 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : BackupKeyring.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Services\Backup;

use App\Services\Backup\Exceptions\{BackupCryptoException, BackupKeyMissingException};
use SensitiveParameter;

/**
 * Schlüsselverwaltung der Cloud-Backups (Feature 017 Phase 32, MVP-362).
 *
 * - Der Installations-Backup-Schlüssel (`BACKUP_MASTER_KEY`, 32 B base64,
 *   bewusst NICHT der APP_KEY) ist der einzige reguläre Entschlüsselungsweg.
 * - Je Snapshot wird ein zufälliger Datenschlüssel erzeugt und doppelt
 *   verpackt: `secretbox` unter dem Master-Key + optional `crypto_box_seal`
 *   an den Recovery-Public-Key (`BACKUP_RECOVERY_PUBLIC_KEY`).
 * - Der Signing-Key für das Commit-Manifest wird deterministisch per KDF
 *   (BLAKE2b) aus dem Master-Key abgeleitet — kein zweites Geheimnis nötig.
 *
 * Schlüsselmaterial verlässt diese Klasse nie in Logs/Exceptions.
 */
class BackupKeyring {
    private const SIGN_KDF_CONTEXT = 'workdiary-backup-sign-v1';

    /** Zufälliger Datenschlüssel für einen neuen Snapshot (secretstream). */
    public function generateDataKey(): string {
        return sodium_crypto_secretstream_xchacha20poly1305_keygen();
    }

    public function hasMasterKey(): bool {
        try {
            $this->masterKey();
        } catch (BackupKeyMissingException) {
            return false;
        }

        return true;
    }

    public function hasRecoveryKey(): bool {
        return $this->recoveryPublicKey() !== null;
    }

    /**
     * Verpackt den Datenschlüssel: secretbox unter dem Master-Key plus
     * optional crypto_box_seal an den Recovery-Public-Key.
     *
     * @return array{key_envelope: string, recovery_envelope: string|null}
     */
    public function wrapDataKey(#[SensitiveParameter] string $dataKey): array {
        $nonce = random_bytes(SODIUM_CRYPTO_SECRETBOX_NONCEBYTES);
        $envelope = base64_encode($nonce . sodium_crypto_secretbox($dataKey, $nonce, $this->masterKey()));

        $recovery = null;
        $recoveryKey = $this->recoveryPublicKey();
        if ($recoveryKey !== null) {
            $recovery = base64_encode(sodium_crypto_box_seal($dataKey, $recoveryKey));
        }

        return ['key_envelope' => $envelope, 'recovery_envelope' => $recovery];
    }

    /** Entpackt den Datenschlüssel mit dem Master-Key (Regelweg). */
    public function unwrapDataKey(string $keyEnvelope): string {
        $raw = base64_decode($keyEnvelope, true);
        if ($raw === false || strlen($raw) <= SODIUM_CRYPTO_SECRETBOX_NONCEBYTES) {
            throw new BackupCryptoException('Schlüssel-Envelope ist beschädigt.');
        }

        $nonce = substr($raw, 0, SODIUM_CRYPTO_SECRETBOX_NONCEBYTES);
        $cipher = substr($raw, SODIUM_CRYPTO_SECRETBOX_NONCEBYTES);
        $dataKey = sodium_crypto_secretbox_open($cipher, $nonce, $this->masterKey());
        if ($dataKey === false) {
            throw new BackupCryptoException('Schlüssel-Envelope lässt sich mit dem Master-Key nicht öffnen.');
        }

        return $dataKey;
    }

    /**
     * Notfallweg: Entpackt den Datenschlüssel über das Recovery-Schlüsselpaar
     * (base64-kodierter crypto_box-Secret-Key des Betreibers).
     */
    public function unwrapWithRecoveryKey(string $recoveryEnvelope, #[SensitiveParameter] string $recoverySecretKeyB64): string {
        $sealed = base64_decode($recoveryEnvelope, true);
        $secret = base64_decode($recoverySecretKeyB64, true);
        if ($sealed === false || $secret === false || strlen($secret) !== SODIUM_CRYPTO_BOX_SECRETKEYBYTES) {
            throw new BackupCryptoException('Recovery-Envelope oder Recovery-Key ist beschädigt.');
        }

        $keypair = sodium_crypto_box_keypair_from_secretkey_and_publickey(
            $secret,
            sodium_crypto_box_publickey_from_secretkey($secret),
        );
        $dataKey = sodium_crypto_box_seal_open($sealed, $keypair);
        if ($dataKey === false) {
            throw new BackupCryptoException('Recovery-Envelope lässt sich mit diesem Recovery-Key nicht öffnen.');
        }

        return $dataKey;
    }

    /** Signiert das Commit-Manifest (detached, Ed25519). */
    public function signCommit(string $bytes): string {
        return base64_encode(sodium_crypto_sign_detached($bytes, $this->signingSecretKey()));
    }

    public function verifyCommitSignature(string $bytes, string $signatureB64): bool {
        $signature = base64_decode($signatureB64, true);
        if ($signature === false || strlen($signature) !== SODIUM_CRYPTO_SIGN_BYTES) {
            return false;
        }

        return sodium_crypto_sign_verify_detached($signature, $bytes, $this->signingPublicKey());
    }

    private function masterKey(): string {
        $configured = config('backup_targets.master_key');
        $raw = is_string($configured) && $configured !== '' ? base64_decode($configured, true) : false;
        if ($raw === false || strlen($raw) !== SODIUM_CRYPTO_SECRETBOX_KEYBYTES) {
            throw new BackupKeyMissingException(
                'BACKUP_MASTER_KEY fehlt oder ist kein gültiger 32-Byte-base64-Schlüssel.',
            );
        }

        return $raw;
    }

    private function recoveryPublicKey(): ?string {
        $configured = config('backup_targets.recovery_public_key');
        if (!is_string($configured) || $configured === '') {
            return null;
        }

        $raw = base64_decode($configured, true);
        if ($raw === false || strlen($raw) !== SODIUM_CRYPTO_BOX_PUBLICKEYBYTES) {
            throw new BackupKeyMissingException(
                'BACKUP_RECOVERY_PUBLIC_KEY ist kein gültiger crypto_box-Public-Key (base64).',
            );
        }

        return $raw;
    }

    /** @return non-empty-string */
    private function signingSecretKey(): string {
        $keypair = sodium_crypto_sign_seed_keypair($this->signingSeed());

        return sodium_crypto_sign_secretkey($keypair);
    }

    /** @return non-empty-string */
    private function signingPublicKey(): string {
        $keypair = sodium_crypto_sign_seed_keypair($this->signingSeed());

        return sodium_crypto_sign_publickey($keypair);
    }

    /** @return non-empty-string */
    private function signingSeed(): string {
        return sodium_crypto_generichash(self::SIGN_KDF_CONTEXT, $this->masterKey(), SODIUM_CRYPTO_SIGN_SEEDBYTES);
    }
}
