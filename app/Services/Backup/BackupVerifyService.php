<?php
/*
 * Created on   : Tue Jul 14 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : BackupVerifyService.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Services\Backup;

use App\Enums\Backup\BackupGenerationStatus;
use App\Models\Backup\{BackupGeneration, BackupGenerationPart, BackupTargetConnection};
use App\Plugins\Contracts\BackupTarget;
use App\Plugins\PluginManager;
use App\Services\Backup\Exceptions\BackupPreflightException;
use Throwable;

/**
 * Wöchentliche Verifikation der Cloud-Backups (Feature 017 Phase 32,
 * MVP-365): Commit-Manifest laden, Ed25519-Signatur prüfen,
 * Stichproben-Teile herunterladen + Hash-Abgleich + Probe-Entschlüsselung
 * ⇒ Status `verified` bzw. `verify_failed`. Ohne gültiges Commit ist eine
 * Generation NICHT restorable.
 */
class BackupVerifyService {
    public function __construct(
        private readonly BackupDecrypter $decrypter,
    ) {}

    /**
     * Verifiziert die jüngste committete Generation jeder aktiven Verbindung.
     *
     * @return array{verified: list<string>, failed: array<string, string>}
     */
    public function run(?BackupTarget $adapter = null): array {
        $verified = [];
        $failed = [];

        $generations = BackupGeneration::query()
            ->whereNotNull('connection_id')
            ->whereIn('status', [BackupGenerationStatus::Committed->value, BackupGenerationStatus::Verified->value])
            ->orderByDesc('committed_at')
            ->get()
            ->unique('connection_id');

        foreach ($generations as $generation) {
            try {
                $this->verifyGeneration($generation, $adapter);
                $verified[] = $generation->snapshot_uuid;
            } catch (Throwable $e) {
                $generation->forceFill([
                    'status' => BackupGenerationStatus::VerifyFailed,
                    'last_error' => mb_substr(class_basename($e) . ': ' . $e->getMessage(), 0, 300),
                ])->save();
                $generation->connection?->recordConnectionFailure('Verify: ' . class_basename($e));
                $failed[$generation->snapshot_uuid] = class_basename($e);
            }
        }

        return ['verified' => $verified, 'failed' => $failed];
    }

    /** Volle Prüfung einer Generation (Signatur + Stichproben). */
    public function verifyGeneration(BackupGeneration $generation, ?BackupTarget $adapter = null): void {
        $connection = $generation->connection;
        if ($connection === null) {
            throw new BackupPreflightException('Generation hat keine Verbindung mehr — Verifikation unmöglich.');
        }
        $adapter ??= $this->adapter($connection);

        // 1) Commit-Manifest laden, Signatur prüfen, Manifest entschlüsseln.
        $document = (string) $adapter->backupDownload($connection, (string) $generation->commit_remote_ref);
        $opened = $this->decrypter->openCommitDocument($document);
        $manifest = $opened['manifest'];
        if (($manifest['snapshot_uuid'] ?? null) !== $generation->snapshot_uuid) {
            throw new BackupPreflightException('Commit-Manifest gehört zu einem anderen Snapshot.');
        }

        // 2) Stichproben: n zufällige Teile herunterladen, Hash + Entschlüsselung prüfen.
        $parts = $generation->parts()->whereNotNull('remote_ref')->get();
        if ($parts->isEmpty()) {
            throw new BackupPreflightException('Generation hat keine hochgeladenen Teile.');
        }

        $sampleCount = max(1, min((int) config('backup_targets.verify_sample_parts', 2), $parts->count()));
        foreach ($parts->shuffle()->take($sampleCount) as $part) {
            $this->verifyPart($adapter, $connection, $generation, $part, $opened['data_key']);
        }

        $generation->forceFill([
            'status' => BackupGenerationStatus::Verified,
            'last_verified_at' => now(),
            'last_error' => null,
        ])->save();
        $generation->audit('backup.verified', ['snapshot_uuid' => $generation->snapshot_uuid, 'sampled_parts' => $sampleCount]);
    }

    private function verifyPart(BackupTarget $adapter, BackupTargetConnection $connection, BackupGeneration $generation, BackupGenerationPart $part, string $dataKey): void {
        $cipherPath = tempnam(sys_get_temp_dir(), 'wd-verify-');
        $plainPath = tempnam(sys_get_temp_dir(), 'wd-verify-');
        if ($cipherPath === false || $plainPath === false) {
            throw new BackupPreflightException('Temporäre Verifikationsdatei konnte nicht angelegt werden.');
        }

        try {
            $stream = $adapter->backupDownload($connection, (string) $part->remote_ref);
            $out = fopen($cipherPath, 'wb');
            if ($out === false) {
                throw new BackupPreflightException('Verifikationsdatei nicht schreibbar.');
            }
            while (!$stream->eof()) {
                fwrite($out, $stream->read(1_048_576));
            }
            fclose($out);

            if (hash_file('sha256', $cipherPath) !== $part->cipher_sha256) {
                throw new BackupPreflightException("Teil {$part->part_no}: Ciphertext-Hash weicht ab.");
            }

            $this->decrypter->decryptPart($cipherPath, $plainPath, $dataKey, $generation->snapshot_uuid, $part->part_no);
            if (hash_file('sha256', $plainPath) !== $part->plain_sha256) {
                throw new BackupPreflightException("Teil {$part->part_no}: Klartext-Hash weicht ab.");
            }
        } finally {
            @unlink($cipherPath);
            @unlink($plainPath);
        }
    }

    private function adapter(BackupTargetConnection $connection): BackupTarget {
        $plugin = app(PluginManager::class)->find($connection->provider->pluginId());
        if (!$plugin instanceof BackupTarget) {
            throw new BackupPreflightException(
                "Kein Backup-Adapter für Provider '{$connection->provider->value}' verfügbar.",
            );
        }

        return $plugin;
    }
}
