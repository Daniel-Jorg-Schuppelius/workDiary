<?php
/*
 * Created on   : Tue Jul 14 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : BackupRunService.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Services\Backup;

use App\Enums\Backup\{BackupGenerationStatus, BackupRetentionClass, BackupTargetStatus};
use App\Models\Backup\{BackupGeneration, BackupGenerationPart, BackupTargetConnection};
use App\Models\BackupHeartbeat;
use App\Plugins\Contracts\BackupTarget;
use App\Plugins\PluginManager;
use App\Services\Backup\Exceptions\{BackupKeyMissingException, BackupPreflightException};
use Illuminate\Support\{Carbon, Str};
use Illuminate\Support\Facades\{Cache, Log};
use SensitiveParameter;
use Throwable;

/**
 * Orchestrierung der Cloud-Backups (Feature 017 Phase 32, MVP-364):
 * Quota-Preflight → Snapshot → Verschlüsselung → Teil-Uploads (idempotent
 * wiederaufnehmbar über die parts-Tabelle) → signiertes Commit → Retention
 * (7 täglich / 4 wöchentlich / 12 monatlich). Fehlerklassen landen als
 * redigierte Health-Einträge an der Verbindung plus Betriebsalarm
 * (Muster MVP-056); ohne Commit-Manifest zählt nichts als Backup.
 */
class BackupRunService {
    /** Sicherheitsaufschlag der Quota-Prüfung (Krypto-Overhead + Manifest). */
    private const QUOTA_SAFETY_FACTOR = 1.05;

    public function __construct(
        private readonly BackupSnapshotBuilder $builder,
        private readonly BackupCrypter $crypter,
        private readonly BackupKeyring $keyring,
        private readonly BackupNaming $naming,
    ) {}

    /**
     * Führt den Lauf für alle aktiven Verbindungen aus. `$adapter`
     * überschreibt die Plugin-Auflösung (Tests, Muster CloudIntakeRunner).
     *
     * @return array{ok: list<string>, failed: array<string, string>}
     */
    public function run(?Carbon $now = null, ?BackupTarget $adapter = null): array {
        $now ??= now();

        if (!$this->keyring->hasMasterKey()) {
            throw new BackupKeyMissingException('BACKUP_MASTER_KEY fehlt — Cloud-Backup nicht möglich.');
        }

        $lock = Cache::lock('backup-targets:run', 3 * 3600);
        if (!$lock->get()) {
            throw new BackupPreflightException('Ein Backup-Lauf ist bereits aktiv (Lease).');
        }

        $ok = [];
        $failed = [];
        try {
            $connections = BackupTargetConnection::query()
                ->where('status', BackupTargetStatus::Active->value)
                ->orderBy('id')
                ->get();

            foreach ($connections as $connection) {
                if (!$connection->isRunnable()) {
                    continue;
                }
                try {
                    $this->runForConnection($connection, $now, $adapter);
                    $ok[] = $connection->name;
                } catch (Throwable $e) {
                    $connection->recordConnectionFailure(class_basename($e) . ': ' . $e->getMessage());
                    $failed[$connection->name] = class_basename($e);
                }
            }
        } finally {
            $lock->release();
        }

        return ['ok' => $ok, 'failed' => $failed];
    }

    /** Zeitklasse des Laufs: monatlich am 1., wöchentlich montags, sonst täglich. */
    public function retentionClassFor(Carbon $day): BackupRetentionClass {
        if ((int) $day->day === 1) {
            return BackupRetentionClass::Monthly;
        }
        if ($day->isMonday()) {
            return BackupRetentionClass::Weekly;
        }

        return BackupRetentionClass::Daily;
    }

    private function runForConnection(BackupTargetConnection $connection, Carbon $now, ?BackupTarget $adapter = null): void {
        $adapter ??= $this->adapter($connection);

        // Wiederaufnahme: eine unterbrochene Generation mit noch vorhandenem
        // Arbeitsverzeichnis wird fortgesetzt statt neu aufgebaut.
        $generation = $this->resumableGeneration($connection);
        $dataKey = null;

        if ($generation === null) {
            $uuid = (string) Str::uuid();
            $generation = BackupGeneration::query()->create([
                'connection_id' => $connection->id,
                'snapshot_uuid' => $uuid,
                'retention_class' => $this->retentionClassFor($now),
                'status' => BackupGenerationStatus::Building,
                'remote_prefix' => $this->naming->remotePrefix($uuid),
                'app_version' => (string) config('app.version'),
                'started_at' => $now,
            ]);

            try {
                $dataKey = $this->buildAndEncrypt($generation);
            } catch (Throwable $e) {
                $generation->forceFill([
                    'status' => BackupGenerationStatus::Failed,
                    'last_error' => mb_substr(class_basename($e) . ': ' . $e->getMessage(), 0, 300),
                ])->save();
                $this->builder->cleanup($generation->snapshot_uuid);

                throw $e;
            }
        }

        try {
            $this->quotaPreflight($adapter, $connection, $generation);
            $this->uploadParts($adapter, $connection, $generation);
            $this->commit($adapter, $connection, $generation, $dataKey);
        } catch (Throwable $e) {
            $generation->forceFill([
                'status' => BackupGenerationStatus::Uploading,
                'last_error' => mb_substr(class_basename($e) . ': ' . $e->getMessage(), 0, 300),
            ])->save();

            throw $e;
        }

        $this->builder->cleanup($generation->snapshot_uuid);
        $this->applyRetention($adapter, $connection);
    }

    /**
     * Baut den Snapshot und verschlüsselt alle Teile ins Arbeitsverzeichnis;
     * legt die Teil-Zeilen (Hashes/Größen) an. Liefert den Datenschlüssel —
     * er wird NUR verpackt (Envelope) persistiert.
     */
    private function buildAndEncrypt(BackupGeneration $generation): string {
        $uuid = $generation->snapshot_uuid;
        $result = $this->builder->build($uuid);
        $plainParts = $this->builder->splitParts($result['tar_path']);

        $dataKey = $this->keyring->generateDataKey();
        $envelopes = $this->keyring->wrapDataKey($dataKey);

        $cipherTotal = 0;
        foreach ($plainParts as $index => $plainPath) {
            $partNo = $index + 1;
            $cipherPath = $plainPath . '.enc';
            $this->crypter->encryptPart($plainPath, $cipherPath, $dataKey, $uuid, $partNo);

            $cipherSize = (int) filesize($cipherPath);
            $cipherTotal += $cipherSize;
            BackupGenerationPart::query()->create([
                'generation_id' => $generation->id,
                'part_no' => $partNo,
                'plain_size' => (int) filesize($plainPath),
                'cipher_size' => $cipherSize,
                'plain_sha256' => (string) hash_file('sha256', $plainPath),
                'cipher_sha256' => (string) hash_file('sha256', $cipherPath),
            ]);
            @unlink($plainPath); // Klartext-Teil sofort entsorgen
        }
        @unlink($result['tar_path']);

        $generation->forceFill([
            'status' => BackupGenerationStatus::Uploading,
            'plain_size' => $result['plain_size'],
            'cipher_size' => $cipherTotal,
            'part_count' => count($plainParts),
            'key_envelope' => $envelopes['key_envelope'],
            'recovery_envelope' => $envelopes['recovery_envelope'],
        ])->save();

        return $dataKey;
    }

    private function quotaPreflight(BackupTarget $adapter, BackupTargetConnection $connection, BackupGeneration $generation): void {
        $quota = $adapter->backupQuota($connection);
        $connection->forceFill([
            'quota_total' => $quota['total'],
            'quota_used' => $quota['used'],
            'quota_checked_at' => now(),
        ])->save();

        $needed = (int) ceil((int) $generation->cipher_size * self::QUOTA_SAFETY_FACTOR);
        if ($quota['total'] !== null && $quota['used'] !== null && $needed > $quota['total'] - $quota['used']) {
            throw new BackupPreflightException(sprintf(
                'Quota reicht nicht: benötigt ~%d B, frei %d B.',
                $needed,
                $quota['total'] - $quota['used'],
            ));
        }
    }

    /** Lädt alle noch offenen Teile hoch (idempotent über uploaded_at). */
    private function uploadParts(BackupTarget $adapter, BackupTargetConnection $connection, BackupGeneration $generation): void {
        $prefix = (string) $generation->remote_prefix;
        $adapter->backupEnsureFolder($connection, $prefix);

        $parts = $generation->parts()->orderBy('part_no')->get();
        foreach ($parts as $part) {
            if ($part->isUploaded()) {
                continue;
            }
            $cipherPath = $this->cipherPath($generation, $part->part_no);
            if (!is_file($cipherPath)) {
                throw new BackupPreflightException(
                    "Verschlüsselter Teil {$part->part_no} fehlt lokal — Lauf kann nicht fortgesetzt werden.",
                );
            }
            $ref = $adapter->backupUploadPart($connection, $cipherPath, $this->naming->partName($prefix, $part->part_no));
            $part->forceFill(['remote_ref' => $ref, 'uploaded_at' => now()])->save();
            @unlink($cipherPath);
        }
    }

    /** Baut, signiert und lädt das Commit-Manifest; erst danach zählt das Backup. */
    private function commit(BackupTarget $adapter, BackupTargetConnection $connection, BackupGeneration $generation, #[SensitiveParameter] ?string $dataKey): void {
        if ($dataKey === null) {
            // Wiederaufnahme: Datenschlüssel aus dem Envelope zurückholen.
            $dataKey = $this->keyring->unwrapDataKey((string) $generation->key_envelope);
        }

        $parts = $generation->parts()->orderBy('part_no')->get();
        $manifest = [
            'snapshot_uuid' => $generation->snapshot_uuid,
            'created_at' => now()->toIso8601String(),
            'app_version' => $generation->app_version,
            'retention_class' => $generation->retention_class->value,
            'plain_size' => $generation->plain_size,
            'cipher_size' => $generation->cipher_size,
            'parts' => $parts->map(static fn (BackupGenerationPart $part): array => [
                'no' => $part->part_no,
                'plain_size' => $part->plain_size,
                'cipher_size' => $part->cipher_size,
                'plain_sha256' => $part->plain_sha256,
                'cipher_sha256' => $part->cipher_sha256,
                'remote_ref' => $part->remote_ref,
            ])->values()->all(),
        ];

        $commit = $this->crypter->buildCommitDocument($manifest, $dataKey, $generation->snapshot_uuid);

        $commitPath = tempnam(sys_get_temp_dir(), 'wd-commit-');
        if ($commitPath === false) {
            throw new BackupPreflightException('Commit-Datei konnte nicht angelegt werden.');
        }
        try {
            file_put_contents($commitPath, $commit['document']);
            $remoteRef = $adapter->backupUploadPart(
                $connection,
                $commitPath,
                $this->naming->commitName((string) $generation->remote_prefix),
            );
        } finally {
            @unlink($commitPath);
        }

        $generation->forceFill([
            'status' => BackupGenerationStatus::Committed,
            'manifest_sha256' => $commit['manifest_sha256'],
            'commit_remote_ref' => $remoteRef,
            'key_envelope' => $commit['key_envelope'],
            'recovery_envelope' => $commit['recovery_envelope'],
            'committed_at' => now(),
            'last_error' => null,
        ])->save();
        $connection->recordConnectionSuccess();
        $this->recordHeartbeat($connection, $generation, $commit['manifest_sha256']);
    }

    /**
     * Meldet den erfolgreichen Lauf an die Backup-Health (Diagnose liest
     * `backup_heartbeats`). Best effort: darf den Lauf nie scheitern lassen.
     */
    private function recordHeartbeat(BackupTargetConnection $connection, BackupGeneration $generation, string $manifestSha256): void {
        try {
            BackupHeartbeat::query()->create([
                'occurred_at' => now()->toImmutable(),
                'size_bytes' => (int) $generation->cipher_size,
                'manifest_hash' => strtolower($manifestSha256),
                'source' => 'cloud:' . $connection->name,
            ]);
        } catch (Throwable $e) {
            Log::warning('Backup-Heartbeat konnte nicht gespeichert werden.', ['exception' => $e->getMessage()]);
        }
    }

    /**
     * Retention 7/4/12: löscht je Zeitklasse die ältesten Generationen über
     * dem Behalte-Limit — aber NUR vollständig verifizierte, nie Legal-Hold
     * und nie die letzte als restorable bestätigte (jüngste verifizierte).
     */
    public function applyRetention(BackupTarget $adapter, BackupTargetConnection $connection): void {
        foreach (BackupRetentionClass::cases() as $class) {
            $generations = BackupGeneration::query()
                ->where('connection_id', $connection->id)
                ->where('retention_class', $class->value)
                ->whereIn('status', [
                    BackupGenerationStatus::Committed->value,
                    BackupGenerationStatus::Verified->value,
                ])
                ->orderByDesc('started_at')
                ->get();

            $lastRestorable = $generations->first(
                static fn (BackupGeneration $generation): bool => $generation->status === BackupGenerationStatus::Verified,
            );

            foreach ($generations->slice($class->keepCount())->values() as $candidate) {
                if (!$candidate->isDeletableByRetention() || $candidate->is($lastRestorable)) {
                    continue;
                }
                $this->deleteRemoteGeneration($adapter, $connection, $candidate);
                $candidate->audit('backup.retentionDeleted', ['snapshot_uuid' => $candidate->snapshot_uuid]);
                $candidate->delete();
            }
        }
    }

    /** Löscht den Remote-Ordner einer Generation (rekursiv, idempotent). */
    private function deleteRemoteGeneration(BackupTarget $adapter, BackupTargetConnection $connection, BackupGeneration $generation): void {
        $prefix = (string) $generation->remote_prefix;
        if ($prefix === '') {
            return;
        }
        $folderName = basename($prefix);
        foreach ($adapter->backupList($connection, dirname($prefix)) as $object) {
            if ($object->name === $folderName) {
                $adapter->backupDelete($connection, $object->ref);

                return;
            }
        }
    }

    private function resumableGeneration(BackupTargetConnection $connection): ?BackupGeneration {
        $candidate = BackupGeneration::query()
            ->where('connection_id', $connection->id)
            ->where('status', BackupGenerationStatus::Uploading->value)
            ->orderByDesc('started_at')
            ->first();
        if ($candidate === null) {
            return null;
        }

        // Nur fortsetzbar, wenn alle offenen Teile lokal noch vorliegen.
        $pending = $candidate->parts()->whereNull('uploaded_at')->orderBy('part_no')->get();
        foreach ($pending as $part) {
            if (!is_file($this->cipherPath($candidate, $part->part_no))) {
                $candidate->forceFill([
                    'status' => BackupGenerationStatus::Failed,
                    'last_error' => 'Wiederaufnahme unmöglich: lokale Teile fehlen.',
                ])->save();
                $this->builder->cleanup($candidate->snapshot_uuid);

                return null;
            }
        }

        return $candidate;
    }

    private function cipherPath(BackupGeneration $generation, int $partNo): string {
        $workDir = rtrim((string) config('backup_targets.work_dir'), '/');

        return $workDir . '/' . $generation->snapshot_uuid . '/snapshot.tar.part-' . $partNo . '.enc';
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
