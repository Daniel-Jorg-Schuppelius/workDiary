<?php
/*
 * Created on   : Thu Jul 23 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : BackupStatusCommand.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Console\Commands;

use App\Enums\Backup\{BackupTargetStatus, RestoreTestResult};
use App\Models\Backup\BackupTargetConnection;
use App\Models\{BackupHeartbeat, RestoreTest};
use App\Services\Backup\BackupKeyring;
use App\Services\Backup\Exceptions\BackupKeyMissingException;
use Illuminate\Console\Command;

/**
 * Einrichtungs-/Betriebsübersicht beider Backup-Wege (MVP-046 + Phase 32)
 * mit konkreten Handlungshinweisen. Exit-Code 1, sobald etwas fehlt, das den
 * Backup-Betrieb verhindert — damit auch für Cron/CI nutzbar.
 */
class BackupStatusCommand extends Command {
    protected $signature = 'workdiary:backup:status';

    protected $description = 'Zeigt den Einrichtungszustand der Backups (Heartbeat, Schlüssel, Ziele, Restore-Test) mit Handlungshinweisen.';

    private bool $problem = false;

    public function handle(BackupKeyring $keyring): int {
        $this->info('Skript-Backup (extern, scripts/backup.sh):');
        $this->scriptBackupStatus();

        $this->newLine();
        $this->info('Cloud-Backupziele (verschlüsselt):');
        $this->cloudBackupStatus($keyring);

        $this->newLine();
        $this->info('Restore-Test:');
        $this->restoreTestStatus();

        return $this->problem ? self::FAILURE : self::SUCCESS;
    }

    private function scriptBackupStatus(): void {
        if ((string) config('backup.heartbeat_token', '') === '') {
            $this->flag('FEHLT', 'Heartbeat-Token — Backup-Läufe erscheinen nicht auf der Statusseite.',
                'php artisan workdiary:backup:rotate-token (erledigt auch scripts/install-system.sh)');
        } else {
            $this->flag('OK', 'Heartbeat-Token gesetzt.');
        }

        $last = BackupHeartbeat::query()->orderByDesc('occurred_at')->first();
        $freshnessHours = (int) config('backup.heartbeat_freshness_hours', 26);
        if ($last === null) {
            $this->flag('FEHLT', 'Noch kein Backup registriert (kein Heartbeat empfangen).',
                'sudo scripts/install-system.sh richtet Cron + Token ein; Probelauf: sudo scripts/backup.sh');
        } elseif ($last->occurred_at->addHours($freshnessHours)->isPast()) {
            $this->flag('ÜBERFÄLLIG', sprintf('Letzter Heartbeat %s (älter als %d h).',
                $last->occurred_at->format('d.m.Y H:i'), $freshnessHours),
                'Cron-Eintrag und /var/log/workdiary-backup.log prüfen.');
        } else {
            $this->flag('OK', sprintf('Letzter Heartbeat %s (%s).',
                $last->occurred_at->format('d.m.Y H:i'), (string) ($last->source ?? 'ohne Quelle')));
        }
    }

    private function cloudBackupStatus(BackupKeyring $keyring): void {
        $targets = BackupTargetConnection::query()->count();
        $active = BackupTargetConnection::query()->where('status', BackupTargetStatus::Active)->count();

        if ($targets === 0) {
            $this->flag('—', 'Keine Ziele verbunden (optional; Administration → Backupziele).');
        } else {
            $this->flag($active > 0 ? 'OK' : 'HINWEIS', sprintf('%d Ziel(e) verbunden, %d aktiv.', $targets, $active),
                $active === 0 ? 'Kein Ziel ist aktiv — Verbindung unter Administration → Backupziele prüfen.' : null);
        }

        if ($keyring->hasMasterKey()) {
            $this->flag('OK', 'Master-Key gesetzt (BACKUP_MASTER_KEY).');
        } elseif ($targets > 0) {
            $this->flag('FEHLT', 'Master-Key fehlt oder ist ungültig — jeder Cloud-Backup-Lauf schlägt fehl.',
                'php artisan workdiary:backup:generate-master-key (Schlüssel danach offline sichern!)');
        } else {
            $this->flag('—', 'Master-Key nicht gesetzt — erst nötig, wenn Ziele verbunden werden.',
                'Dann: php artisan workdiary:backup:generate-master-key');
        }

        try {
            if ($keyring->hasRecoveryKey()) {
                $this->flag('OK', 'Recovery-Public-Key gesetzt (Notfall-Zweitweg vorhanden).');
            } elseif ($targets > 0 || $keyring->hasMasterKey()) {
                $this->flag('HINWEIS', 'Kein Recovery-Key — geht der Master-Key verloren, gibt es keinen Zweitweg.',
                    'php artisan workdiary:backup:generate-recovery-key (Secret-Key in den Offline-Tresor)');
            } else {
                $this->flag('—', 'Recovery-Key nicht gesetzt (folgt mit der Cloud-Backup-Einrichtung).');
            }
        } catch (BackupKeyMissingException) {
            $this->flag('FEHLER', 'BACKUP_RECOVERY_PUBLIC_KEY ist gesetzt, aber kein gültiger crypto_box-Public-Key (base64).',
                'php artisan workdiary:backup:generate-recovery-key --force (neues Paar erzeugen)');
        }
    }

    private function restoreTestStatus(): void {
        $lastPassed = RestoreTest::query()
            ->where('result', RestoreTestResult::Passed)
            ->orderByDesc('tested_on')
            ->first();
        $overdueDays = (int) config('backup.restore_test_overdue_days', 180);

        if ($lastPassed === null) {
            $this->flag('HINWEIS', 'Noch kein erfolgreicher Restore-Test registriert.',
                'Nach einem Test-Restore erfassen: php artisan workdiary:backup:restore-test');
        } elseif ($lastPassed->tested_on->addDays($overdueDays)->isPast()) {
            $this->flag('ÜBERFÄLLIG', sprintf('Letzter erfolgreicher Restore-Test am %s (älter als %d Tage).',
                $lastPassed->tested_on->format('d.m.Y'), $overdueDays),
                'Restore nach docs/backup-restore.md §4 proben und erfassen: php artisan workdiary:backup:restore-test');
        } else {
            $this->flag('OK', sprintf('Letzter erfolgreicher Restore-Test am %s.', $lastPassed->tested_on->format('d.m.Y')));
        }
    }

    /** Statuszeile + optionaler Handlungshinweis; FEHLT/ÜBERFÄLLIG/FEHLER kippen den Exit-Code. */
    private function flag(string $label, string $message, ?string $hint = null): void {
        $this->problem = $this->problem || in_array($label, ['FEHLT', 'ÜBERFÄLLIG', 'FEHLER'], true);

        $this->line(sprintf('  [%s] %s', $label, $message));
        if ($hint !== null) {
            $this->line('      → ' . $hint);
        }
    }
}
