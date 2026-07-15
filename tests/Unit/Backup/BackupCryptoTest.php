<?php
/*
 * Created on   : Tue Jul 14 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : BackupCryptoTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace Tests\Unit\Backup;

use App\Services\Backup\{BackupCrypter, BackupDecrypter, BackupKeyring};
use App\Services\Backup\Exceptions\{BackupCommitInvalidException, BackupCryptoException, BackupKeyMissingException};
use App\Services\Backup\Support\SecretStreamFile;
use Tests\TestCase;

/**
 * Kryptodesign Phase 32 (MVP-362) offline: Roundtrip, Manipulation
 * (Bitflip/Trunkierung/Teil-Vertauschung), Envelope-Wege (Master-Key +
 * Recovery-Key) und Commit-Signatur.
 */
class BackupCryptoTest extends TestCase {
    private const UUID = '01890a5d-ac96-774b-bcce-b302099a8057';

    private string $dir;

    protected function setUp(): void {
        parent::setUp();
        config(['backup_targets.master_key' => base64_encode(str_repeat("\x42", 32))]);
        $this->dir = sys_get_temp_dir() . '/wd-backup-crypto-' . uniqid();
        mkdir($this->dir, 0770, true);
    }

    protected function tearDown(): void {
        foreach ((array) glob($this->dir . '/*') as $file) {
            @unlink((string) $file);
        }
        @rmdir($this->dir);
        parent::tearDown();
    }

    private function crypter(): BackupCrypter {
        return new BackupCrypter(new BackupKeyring(), new SecretStreamFile());
    }

    private function decrypter(): BackupDecrypter {
        return new BackupDecrypter(new BackupKeyring(), new SecretStreamFile());
    }

    private function writePlain(string $name, string $content): string {
        $path = $this->dir . '/' . $name;
        file_put_contents($path, $content);

        return $path;
    }

    public function test_part_roundtrip_over_multiple_chunks(): void {
        $keyring = new BackupKeyring();
        $dataKey = $keyring->generateDataKey();
        $plain = random_bytes(2_500_000); // > 2 Chunks à 1 MiB
        $plainPath = $this->writePlain('part.plain', $plain);
        $cipherPath = $this->dir . '/part.enc';
        $restoredPath = $this->dir . '/part.out';

        $this->crypter()->encryptPart($plainPath, $cipherPath, $dataKey, self::UUID, 1);
        $this->decrypter()->decryptPart($cipherPath, $restoredPath, $dataKey, self::UUID, 1);

        $this->assertSame(hash('sha256', $plain), hash_file('sha256', $restoredPath));
        $this->assertNotSame($plain, (string) file_get_contents($cipherPath));
    }

    public function test_bitflip_breaks_decryption(): void {
        $keyring = new BackupKeyring();
        $dataKey = $keyring->generateDataKey();
        $plainPath = $this->writePlain('part.plain', random_bytes(4096));
        $cipherPath = $this->dir . '/part.enc';
        $this->crypter()->encryptPart($plainPath, $cipherPath, $dataKey, self::UUID, 1);

        $bytes = (string) file_get_contents($cipherPath);
        $offset = intdiv(strlen($bytes), 2);
        $bytes[$offset] = chr(ord($bytes[$offset]) ^ 0x01);
        file_put_contents($cipherPath, $bytes);

        $this->expectException(BackupCryptoException::class);
        $this->decrypter()->decryptPart($cipherPath, $this->dir . '/part.out', $dataKey, self::UUID, 1);
    }

    public function test_truncation_breaks_decryption(): void {
        $keyring = new BackupKeyring();
        $dataKey = $keyring->generateDataKey();
        $plainPath = $this->writePlain('part.plain', random_bytes(2_200_000));
        $cipherPath = $this->dir . '/part.enc';
        $this->crypter()->encryptPart($plainPath, $cipherPath, $dataKey, self::UUID, 1);

        // Letzten (FINAL-)Chunk abschneiden — Endmarkierung fehlt.
        $bytes = (string) file_get_contents($cipherPath);
        file_put_contents($cipherPath, substr($bytes, 0, 1_048_700));

        $this->expectException(BackupCryptoException::class);
        $this->decrypter()->decryptPart($cipherPath, $this->dir . '/part.out', $dataKey, self::UUID, 1);
    }

    public function test_part_swap_breaks_decryption_via_additional_data(): void {
        $keyring = new BackupKeyring();
        $dataKey = $keyring->generateDataKey();
        $plainPath = $this->writePlain('part.plain', random_bytes(4096));
        $cipherPath = $this->dir . '/part.enc';
        $this->crypter()->encryptPart($plainPath, $cipherPath, $dataKey, self::UUID, 1);

        // Als Teil 2 ausgeben (Vertauschung) ⇒ AD-Bindung bricht.
        $this->expectException(BackupCryptoException::class);
        $this->decrypter()->decryptPart($cipherPath, $this->dir . '/part.out', $dataKey, self::UUID, 2);
    }

    public function test_commit_roundtrip_and_signature_break(): void {
        $keyring = new BackupKeyring();
        $dataKey = $keyring->generateDataKey();
        $manifest = ['snapshot_uuid' => self::UUID, 'parts' => [['no' => 1, 'size' => 4096]]];

        $commit = $this->crypter()->buildCommitDocument($manifest, $dataKey, self::UUID);
        $opened = $this->decrypter()->openCommitDocument($commit['document']);
        $this->assertSame($manifest, $opened['manifest']);
        $this->assertSame($dataKey, $opened['data_key']);

        // Signatur brechen: ein Bit im verschlüsselten Manifest kippen.
        $decoded = json_decode($commit['document'], true);
        $cipher = base64_decode((string) $decoded['manifest'], true);
        $this->assertIsString($cipher);
        $cipher[10] = chr(ord($cipher[10]) ^ 0x01);
        $decoded['manifest'] = base64_encode($cipher);
        $tampered = json_encode($decoded, JSON_THROW_ON_ERROR);

        $this->expectException(BackupCommitInvalidException::class);
        $this->decrypter()->openCommitDocument($tampered);
    }

    public function test_recovery_key_path_opens_commit_without_master_key(): void {
        $recoveryKeypair = sodium_crypto_box_keypair();
        config(['backup_targets.recovery_public_key' => base64_encode(sodium_crypto_box_publickey($recoveryKeypair))]);

        $keyring = new BackupKeyring();
        $this->assertTrue($keyring->hasRecoveryKey());
        $dataKey = $keyring->generateDataKey();
        $commit = $this->crypter()->buildCommitDocument(['snapshot_uuid' => self::UUID], $dataKey, self::UUID);
        $this->assertNotNull($commit['recovery_envelope']);

        $opened = $this->decrypter()->openCommitDocument(
            $commit['document'],
            base64_encode(sodium_crypto_box_secretkey($recoveryKeypair)),
        );
        $this->assertSame($dataKey, $opened['data_key']);

        // Falscher Recovery-Key ⇒ Abbruch.
        $this->expectException(BackupCryptoException::class);
        $this->decrypter()->openCommitDocument(
            $commit['document'],
            base64_encode(sodium_crypto_box_secretkey(sodium_crypto_box_keypair())),
        );
    }

    public function test_wrong_master_key_cannot_unwrap_envelope(): void {
        $keyring = new BackupKeyring();
        $dataKey = $keyring->generateDataKey();
        $envelopes = $keyring->wrapDataKey($dataKey);

        config(['backup_targets.master_key' => base64_encode(str_repeat("\x24", 32))]);

        $this->expectException(BackupCryptoException::class);
        (new BackupKeyring())->unwrapDataKey($envelopes['key_envelope']);
    }

    public function test_missing_master_key_is_reported_clearly(): void {
        config(['backup_targets.master_key' => null]);
        $keyring = new BackupKeyring();

        $this->assertFalse($keyring->hasMasterKey());
        $this->expectException(BackupKeyMissingException::class);
        $keyring->wrapDataKey(str_repeat("\x00", 32));
    }
}
