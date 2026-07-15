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

use App\Models\Backup\BackupGeneration;
use App\Services\Backup\BackupRestoreTestService;
use Illuminate\Console\Command;
use Throwable;

/**
 * Restore-Test einer Backup-Generation (Feature 017 Phase 32, MVP-365):
 * voller Download + Entschlüsselung + Entpacken in ein ISOLIERTES
 * Verzeichnis ({@see BackupRestoreTestService}) — überschreibt nie die
 * laufende Produktion. Der produktive Restore bleibt ein bewusster
 * Wartungslauf mit dokumentierter Vier-Augen-Freigabe.
 */
class BackupRestoreTestCommand extends Command {
    protected $signature = 'workdiary:backup:restore-test
        {--generation= : Snapshot-UUID der Generation (Default: jüngste committete)}
        {--target-dir= : Isoliertes, leeres Zielverzeichnis (Pflicht)}';

    protected $description = 'Cloud-Backup: Generation isoliert wiederherstellen und Integrität protokollieren (RPO/RTO)';

    public function handle(BackupRestoreTestService $service): int {
        $targetDir = (string) $this->option('target-dir');
        if ($targetDir === '') {
            $this->error('--target-dir ist Pflicht (isoliertes, leeres Verzeichnis).');

            return self::INVALID;
        }

        $uuid = (string) $this->option('generation');
        $generation = $uuid !== ''
            ? BackupGeneration::query()->where('snapshot_uuid', $uuid)->first()
            : BackupGeneration::query()->whereNotNull('committed_at')->orderByDesc('committed_at')->first();
        if ($generation === null) {
            $this->error('Keine passende Generation gefunden.');

            return self::INVALID;
        }

        try {
            $result = $service->run($generation, $targetDir);
        } catch (Throwable $e) {
            $this->error('Restore-Test fehlgeschlagen: ' . $e->getMessage());

            return self::FAILURE;
        }

        $this->info(sprintf(
            'Restore-Test bestanden: %s — RPO %d s, RTO %d s, %d B nach %s',
            $generation->snapshot_uuid,
            $result['rpo_seconds'],
            $result['rto_seconds'],
            $result['restored_size'],
            $result['target_dir'],
        ));

        return self::SUCCESS;
    }
}
