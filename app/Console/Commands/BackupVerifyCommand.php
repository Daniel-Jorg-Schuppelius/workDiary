<?php
/*
 * Created on   : Tue Jul 14 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : BackupVerifyCommand.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Console\Commands;

use App\Enums\Operations\{OperationsTaskSeverity, OperationsTaskType};
use App\Services\Backup\BackupVerifyService;
use App\Services\Operations\{OperationsAlertService, OperationsSignal};
use Illuminate\Console\Command;
use Throwable;

/**
 * Wöchentliche Backup-Verifikation (Feature 017 Phase 32, MVP-365):
 * Commit-Signatur + Stichproben-Hashes je aktiver Verbindung
 * ({@see BackupVerifyService}); Fehlschläge werden als Betriebsaufgabe
 * gemeldet.
 */
class BackupVerifyCommand extends Command {
    protected $signature = 'workdiary:backup:verify';

    protected $description = 'Cloud-Backup: Commit-Manifest und Stichproben-Teile der jüngsten Generationen verifizieren';

    public function handle(BackupVerifyService $service): int {
        $result = $service->run();

        foreach ($result['verified'] as $uuid) {
            $this->line("VERIFIZIERT: {$uuid}");
        }
        foreach ($result['failed'] as $uuid => $class) {
            $this->error("FEHLER: {$uuid} ({$class})");
        }

        try {
            $alerts = app(OperationsAlertService::class);
            if ($result['failed'] !== []) {
                $alerts->report(new OperationsSignal(
                    type: OperationsTaskType::BackupFailed,
                    dedupeKey: 'backup_target_verify_failed',
                    severity: OperationsTaskSeverity::Critical,
                    titleKey: 'operations.task.backup_target_verify_failed',
                    params: ['reason' => implode(', ', array_keys($result['failed']))],
                    linkRoute: 'admin.backup-targets.index',
                ));
            } else {
                $alerts->resolve('backup_target_verify_failed');
            }
        } catch (Throwable $e) {
            $this->warn('Betriebsaufgabe konnte nicht aktualisiert werden: ' . $e->getMessage());
        }

        return $result['failed'] === [] ? self::SUCCESS : self::FAILURE;
    }
}
