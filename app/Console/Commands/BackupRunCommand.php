<?php
/*
 * Created on   : Tue Jul 14 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : BackupRunCommand.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Console\Commands;

use App\Enums\Operations\{OperationsTaskSeverity, OperationsTaskType};
use App\Services\Backup\BackupRunService;
use App\Services\Operations\{OperationsAlertService, OperationsSignal};
use Illuminate\Console\Command;
use Throwable;

/**
 * Cloud-Backup-Lauf (Feature 017 Phase 32, MVP-364): Snapshot →
 * Verschlüsselung → Upload → Commit → Retention für alle aktiven
 * Backupziele ({@see BackupRunService}). Fehler werden als
 * Betriebsaufgabe gemeldet (Muster MVP-056); ein erfolgreicher Lauf
 * löst den Alarm wieder auf.
 */
class BackupRunCommand extends Command {
    protected $signature = 'workdiary:backup:run';

    protected $description = 'Cloud-Backup: verschlüsselten Snapshot erstellen, hochladen, committen und Retention anwenden';

    public function handle(BackupRunService $service): int {
        try {
            $result = $service->run();
        } catch (Throwable $e) {
            $this->error('Backup-Lauf abgebrochen: ' . $e->getMessage());
            $this->reportAlert(class_basename($e) . ': ' . $e->getMessage());

            return self::FAILURE;
        }

        foreach ($result['ok'] as $name) {
            $this->line("OK: {$name}");
        }
        foreach ($result['failed'] as $name => $class) {
            $this->error("FEHLER: {$name} ({$class})");
        }

        if ($result['failed'] !== []) {
            $this->reportAlert(implode(', ', array_map(
                static fn (string $name, string $class): string => "{$name}: {$class}",
                array_keys($result['failed']),
                array_values($result['failed']),
            )));

            return self::FAILURE;
        }

        if ($result['ok'] !== []) {
            $this->resolveAlert();
        }

        return self::SUCCESS;
    }

    private function reportAlert(string $reason): void {
        try {
            app(OperationsAlertService::class)->report(new OperationsSignal(
                type: OperationsTaskType::BackupFailed,
                dedupeKey: 'backup_target_failed',
                severity: OperationsTaskSeverity::Critical,
                titleKey: 'operations.task.backup_target_failed',
                params: ['reason' => mb_substr($reason, 0, 300)],
                linkRoute: 'admin.backup-targets.index',
            ));
        } catch (Throwable $e) {
            $this->warn('Betriebsaufgabe konnte nicht aktualisiert werden: ' . $e->getMessage());
        }
    }

    private function resolveAlert(): void {
        try {
            app(OperationsAlertService::class)->resolve('backup_target_failed');
        } catch (Throwable $e) {
            $this->warn('Betriebsaufgabe konnte nicht aufgelöst werden: ' . $e->getMessage());
        }
    }
}
