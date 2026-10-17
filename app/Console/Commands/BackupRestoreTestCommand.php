<?php
/*
 * Created on   : Tue Jul 14 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : BackupRestoreTestCommand.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Console\Commands;

use App\Enums\Operations\{OperationsTaskSeverity, OperationsTaskType};
use App\Models\Backup\BackupGeneration;
use App\Services\Backup\BackupRestoreTestService;
use App\Services\Operations\{OperationsAlertService, OperationsSignal};
use Illuminate\Console\Command;
use Throwable;

/**
 * Restore-Test von Backup-Generationen (Feature 017 Phase 32, MVP-365;
 * geplant seit MVP-1204): voller Download + Entschlüsselung + Entpacken in
 * ein ISOLIERTES Verzeichnis ({@see BackupRestoreTestService}) — überschreibt
 * nie die laufende Produktion. Ohne --generation läuft die jüngste Generation
 * jeder Verbindung (Scheduler-Job backup.cloud-restore-test); Fehlschläge
 * stehen im Restore-Test-Register und als Betriebsaufgabe. Der produktive
 * Restore bleibt ein bewusster Wartungslauf mit Vier-Augen-Freigabe.
 */
class BackupRestoreTestCommand extends Command {
    protected $signature = 'workdiary:backup:restore-test
        {--generation= : Snapshot-UUID einer Generation (Default: jüngste Generation jeder Verbindung)}
        {--target-dir= : Isoliertes, leeres Zielverzeichnis (nur mit --generation; Default: unterhalb von BACKUP_WORK_DIR)}
        {--keep : Wiederhergestellte Dateien nach dem Test stehen lassen}';

    protected $description = 'Cloud-Backup: Generationen isoliert wiederherstellen, Integrität protokollieren (RPO/RTO) und ins Restore-Test-Register eintragen';

    public function handle(BackupRestoreTestService $service): int {
        $uuid = (string) $this->option('generation');
        $targetDir = (string) $this->option('target-dir');
        $keep = (bool) $this->option('keep');

        if ($uuid === '') {
            if ($targetDir !== '') {
                $this->error('--target-dir gilt nur zusammen mit --generation.');

                return self::INVALID;
            }

            $result = $service->runForLatestGenerations(keep: $keep);
            if ($result['passed'] === [] && $result['failed'] === []) {
                $this->info('Keine committete Cloud-Generation vorhanden — nichts zu testen.');

                return self::SUCCESS;
            }
        } else {
            $generation = BackupGeneration::query()->where('snapshot_uuid', $uuid)->first();
            if ($generation === null) {
                $this->error('Keine passende Generation gefunden.');

                return self::INVALID;
            }

            $result = $service->runRecorded($generation, $targetDir !== '' ? $targetDir : null, $keep);
        }

        foreach ($result['passed'] as $snapshotUuid) {
            $this->info("BESTANDEN: {$snapshotUuid}" . ($keep ? ' → ' . $result['target_dirs'][$snapshotUuid] : ''));
        }
        foreach ($result['failed'] as $snapshotUuid => $class) {
            $this->error("FEHLER: {$snapshotUuid} ({$class})" . ($keep ? ' → ' . $result['target_dirs'][$snapshotUuid] : ''));
        }

        $this->syncAlert($result['failed']);

        return $result['failed'] === [] ? self::SUCCESS : self::FAILURE;
    }

    /** @param array<string, string> $failed */
    private function syncAlert(array $failed): void {
        try {
            $alerts = app(OperationsAlertService::class);
            if ($failed !== []) {
                $alerts->report(new OperationsSignal(
                    type: OperationsTaskType::BackupFailed,
                    dedupeKey: 'backup_restore_test_failed',
                    severity: OperationsTaskSeverity::Critical,
                    titleKey: 'operations.task.backup_restore_test_failed',
                    params: ['reason' => mb_substr(implode(', ', array_map(
                        static fn (string $uuid, string $class): string => "{$uuid}: {$class}",
                        array_keys($failed),
                        array_values($failed),
                    )), 0, 300)],
                    linkRoute: 'admin.backup.status',
                ));
            } else {
                $alerts->resolve('backup_restore_test_failed');
            }
        } catch (Throwable $e) {
            $this->warn('Betriebsaufgabe konnte nicht aktualisiert werden: ' . $e->getMessage());
        }
    }
}
