<?php
/*
 * Created on   : Thu Jul 23 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : BackupGenerateMasterKeyCommand.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Console\Commands;

use App\Console\Concerns\UpdatesEnvFile;
use App\Models\AuditLog;
use Illuminate\Console\Command;

/**
 * Erzeugt den BACKUP_MASTER_KEY der verschlüsselten Cloud-Backupziele
 * (Feature 017 Phase 32, MVP-362) und schreibt ihn in die .env.
 *
 * Ein vorhandener Schlüssel wird nie stillschweigend ersetzt (--force nötig),
 * denn ein neuer Schlüssel kann bestehende Backups nicht mehr öffnen. Der
 * Schlüssel selbst landet nie im Audit-Log.
 */
class BackupGenerateMasterKeyCommand extends Command {
    use UpdatesEnvFile;

    protected $signature = 'workdiary:backup:generate-master-key
        {--force : Vorhandenen Schlüssel ersetzen — bestehende Cloud-Backups bleiben am ALTEN Schlüssel!}';

    protected $description = 'Erzeugt den BACKUP_MASTER_KEY für die verschlüsselten Cloud-Backupziele und schreibt ihn in die .env.';

    public function handle(): int {
        $envPath = $this->writableEnvPath();
        if ($envPath === null) {
            $this->error('.env-Datei nicht gefunden oder nicht beschreibbar: ' . app()->environmentFilePath());

            return self::FAILURE;
        }

        $configured = (string) config('backup_targets.master_key', '');
        if (($configured !== '' || $this->envHasValue($envPath, 'BACKUP_MASTER_KEY')) && ! $this->option('force')) {
            $this->error('BACKUP_MASTER_KEY ist bereits gesetzt — Abbruch.');
            $this->line('Ein neuer Schlüssel kann bestehende Cloud-Backups NICHT mehr öffnen.');
            $this->line('Wirklich ersetzen: --force (alten Schlüssel vorher im Tresor sichern!).');

            return self::FAILURE;
        }

        $key = base64_encode(random_bytes(SODIUM_CRYPTO_SECRETBOX_KEYBYTES));
        if (! $this->writeEnvValue($envPath, 'BACKUP_MASTER_KEY', $key)) {
            $this->error('Konnte .env nicht schreiben: ' . $envPath);

            return self::FAILURE;
        }

        AuditLog::create([
            'organization_id' => null,
            'user_id' => null,
            'event' => 'backup.masterKeyGenerated',
            'auditable_type' => self::class,
            'auditable_id' => 0,
            'changes' => ['forced' => (bool) $this->option('force')],
            'ip' => null,
            'user_agent' => null,
        ]);

        $this->info('Neuer BACKUP_MASTER_KEY (in .env hinterlegt):');
        $this->line($key);
        $this->newLine();
        $this->warn('JETZT offline sichern (Tresor/Passwortmanager) — ohne diesen Schlüssel sind alle Cloud-Backups unlesbar.');
        $this->line('Er gehört nie ins Backup selbst, nie ins Cloudziel, nie in Logs.');
        if ($this->option('force')) {
            $this->warn('Bestehende Snapshots bleiben mit dem ALTEN Schlüssel verschlüsselt — den alten Schlüssel im Tresor behalten!');
        }
        $this->newLine();
        $this->comment('Empfohlen: php artisan workdiary:backup:generate-recovery-key (Notfall-Zweitweg).');
        $this->comment('Tipp: php artisan config:clear, damit der Schlüssel sofort greift.');

        return self::SUCCESS;
    }
}
