<?php
/*
 * Created on   : Thu Jul 23 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : BackupGenerateRecoveryKeyCommand.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Console\Commands;

use App\Console\Concerns\UpdatesEnvFile;
use App\Models\AuditLog;
use Illuminate\Console\Command;

/**
 * Erzeugt das Recovery-Schlüsselpaar der Cloud-Backups (crypto_box, MVP-362):
 * Public-Key → .env (BACKUP_RECOVERY_PUBLIC_KEY), Secret-Key → einmalige
 * Ausgabe für den Offline-Tresor — er wird nirgends gespeichert.
 */
class BackupGenerateRecoveryKeyCommand extends Command {
    use UpdatesEnvFile;

    protected $signature = 'workdiary:backup:generate-recovery-key
        {--force : Vorhandenen Public-Key ersetzen — alte Recovery-Umschläge bleiben am ALTEN Schlüsselpaar!}';

    protected $description = 'Erzeugt das Recovery-Schlüsselpaar der Cloud-Backups: Public-Key in die .env, Secret-Key einmalig für den Offline-Tresor.';

    public function handle(): int {
        $envPath = $this->writableEnvPath();
        if ($envPath === null) {
            $this->error('.env-Datei nicht gefunden oder nicht beschreibbar: ' . app()->environmentFilePath());

            return self::FAILURE;
        }

        $configured = (string) config('backup_targets.recovery_public_key', '');
        if (($configured !== '' || $this->envHasValue($envPath, 'BACKUP_RECOVERY_PUBLIC_KEY')) && ! $this->option('force')) {
            $this->error('BACKUP_RECOVERY_PUBLIC_KEY ist bereits gesetzt — Abbruch.');
            $this->line('Recovery-Umschläge bestehender Snapshots sind an das alte Paar versiegelt; ein neues Paar öffnet sie nicht.');
            $this->line('Wirklich ersetzen: --force (alten Secret-Key im Tresor behalten!).');

            return self::FAILURE;
        }

        $pair = sodium_crypto_box_keypair();
        $publicKey = base64_encode(sodium_crypto_box_publickey($pair));
        $secretKey = base64_encode(sodium_crypto_box_secretkey($pair));

        if (! $this->writeEnvValue($envPath, 'BACKUP_RECOVERY_PUBLIC_KEY', $publicKey)) {
            $this->error('Konnte .env nicht schreiben: ' . $envPath);

            return self::FAILURE;
        }

        AuditLog::create([
            'organization_id' => null,
            'user_id' => null,
            'event' => 'backup.recoveryKeyGenerated',
            'auditable_type' => self::class,
            'auditable_id' => 0,
            'changes' => ['public_key' => $publicKey, 'forced' => (bool) $this->option('force')],
            'ip' => null,
            'user_agent' => null,
        ]);

        $this->info('Recovery-Public-Key (in .env hinterlegt):');
        $this->line($publicKey);
        $this->newLine();
        $this->info('Recovery-Secret-Key — EINZIGE Anzeige, wird nirgends gespeichert:');
        $this->line($secretKey);
        $this->newLine();
        $this->warn('Secret-Key JETZT in den Offline-Tresor — nach diesem Aufruf ist er nicht wiederherstellbar und gehört NIE auf den Server.');
        $this->line('Recovery-Umschläge entstehen erst ab dem nächsten Backup-Lauf; ältere Snapshots sind weiter nur über den Master-Key lesbar.');
        $this->newLine();
        $this->comment('Tipp: php artisan config:clear, damit der Public-Key sofort greift.');

        sodium_memzero($secretKey);

        return self::SUCCESS;
    }
}
