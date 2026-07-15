<?php
/*
 * Created on   : Tue Jul 14 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : SecretStreamFile.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Services\Backup\Support;

use App\Services\Backup\Exceptions\BackupCryptoException;
use SensitiveParameter;

/**
 * Streaming-AEAD-Dateiformat der Backup-Teile (Feature 017 Phase 32,
 * MVP-362): `sodium_crypto_secretstream_xchacha20poly1305` in
 * 1-MiB-Chunks. Dateiaufbau: Magic `WDB1` + secretstream-Header, danach je
 * Chunk eine 4-Byte-Länge (big-endian) + Ciphertext; der letzte Chunk trägt
 * TAG_FINAL — Trunkierung fällt dadurch immer auf. Die Additional Data
 * (z. B. `<uuid>/part-<n>`) bindet jeden Chunk an seinen Kontext, sodass
 * Teil-Vertauschung die Entschlüsselung bricht.
 */
class SecretStreamFile {
    private const MAGIC = 'WDB1';

    private const CHUNK_SIZE = 1_048_576; // 1 MiB Klartext je Chunk

    public function encrypt(string $plainPath, string $cipherPath, #[SensitiveParameter] string $key, string $additionalData): void {
        $in = @fopen($plainPath, 'rb');
        if ($in === false) {
            throw new BackupCryptoException("Quelldatei nicht lesbar: {$plainPath}");
        }

        $out = @fopen($cipherPath, 'wb');
        if ($out === false) {
            fclose($in);

            throw new BackupCryptoException("Zieldatei nicht schreibbar: {$cipherPath}");
        }

        try {
            [$state, $header] = sodium_crypto_secretstream_xchacha20poly1305_init_push($key);
            fwrite($out, self::MAGIC . $header);

            while (!feof($in)) {
                $plain = fread($in, self::CHUNK_SIZE);
                if ($plain === false) {
                    throw new BackupCryptoException("Lesefehler in {$plainPath}.");
                }
                if ($plain === '' && !feof($in)) {
                    continue;
                }

                $tag = feof($in)
                    ? SODIUM_CRYPTO_SECRETSTREAM_XCHACHA20POLY1305_TAG_FINAL
                    : SODIUM_CRYPTO_SECRETSTREAM_XCHACHA20POLY1305_TAG_MESSAGE;
                $cipher = sodium_crypto_secretstream_xchacha20poly1305_push($state, $plain, $additionalData, $tag);
                fwrite($out, pack('N', strlen($cipher)) . $cipher);
            }
        } finally {
            fclose($in);
            fclose($out);
        }
    }

    public function decrypt(string $cipherPath, string $plainPath, #[SensitiveParameter] string $key, string $additionalData): void {
        $in = @fopen($cipherPath, 'rb');
        if ($in === false) {
            throw new BackupCryptoException("Verschlüsselte Datei nicht lesbar: {$cipherPath}");
        }

        $out = @fopen($plainPath, 'wb');
        if ($out === false) {
            fclose($in);

            throw new BackupCryptoException("Zieldatei nicht schreibbar: {$plainPath}");
        }

        try {
            $prefix = fread($in, strlen(self::MAGIC) + SODIUM_CRYPTO_SECRETSTREAM_XCHACHA20POLY1305_HEADERBYTES);
            if ($prefix === false
                || strlen($prefix) !== strlen(self::MAGIC) + SODIUM_CRYPTO_SECRETSTREAM_XCHACHA20POLY1305_HEADERBYTES
                || !str_starts_with($prefix, self::MAGIC)) {
                throw new BackupCryptoException('Kein gültiges Backup-Teilformat (Header beschädigt).');
            }

            $state = sodium_crypto_secretstream_xchacha20poly1305_init_pull(substr($prefix, strlen(self::MAGIC)), $key);

            $sawFinal = false;
            while (!feof($in)) {
                $lengthBytes = fread($in, 4);
                if ($lengthBytes === false || $lengthBytes === '') {
                    break; // sauberes Dateiende nach dem letzten Chunk
                }
                if (strlen($lengthBytes) !== 4 || $sawFinal) {
                    throw new BackupCryptoException('Backup-Teil ist beschädigt (unerwartete Daten nach dem Ende).');
                }

                /** @var array{1: int} $unpacked */
                $unpacked = unpack('N', $lengthBytes);
                $length = $unpacked[1];
                if ($length < SODIUM_CRYPTO_SECRETSTREAM_XCHACHA20POLY1305_ABYTES) {
                    throw new BackupCryptoException('Backup-Teil ist beschädigt (ungültige Chunk-Länge).');
                }

                $cipher = fread($in, $length);
                if ($cipher === false || strlen($cipher) !== $length) {
                    throw new BackupCryptoException('Backup-Teil ist trunkiert.');
                }

                $result = sodium_crypto_secretstream_xchacha20poly1305_pull($state, $cipher, $additionalData);
                if ($result === false) {
                    throw new BackupCryptoException('Entschlüsselung fehlgeschlagen — Teil manipuliert, vertauscht oder falscher Schlüssel.');
                }

                [$plain, $tag] = $result;
                fwrite($out, $plain);
                $sawFinal = $tag === SODIUM_CRYPTO_SECRETSTREAM_XCHACHA20POLY1305_TAG_FINAL;
            }

            if (!$sawFinal) {
                throw new BackupCryptoException('Backup-Teil ist unvollständig (Endmarkierung fehlt).');
            }
        } finally {
            fclose($in);
            fclose($out);
        }
    }
}
