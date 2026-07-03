<?php
/*
 * Created on   : Tue May 26 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : BackupRotateTokenCommand.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Console\Commands;

use App\Models\AuditLog;
use CommonToolkit\Helper\Data\CryptoHelper;
use CommonToolkit\Helper\FileSystem\File as ToolkitFile;
use Illuminate\Console\Command;
use Illuminate\Support\Str;
use Throwable;

/**
 * Rotiert den Bearer-Token für POST /admin/backup/heartbeat (MVP-046 §5).
 *
 * Schreibt `BACKUP_HEARTBEAT_TOKEN=...` in die .env-Datei, gibt den neuen
 * Token einmalig auf STDOUT aus und legt ein Audit-Event
 * `backup.tokenRotated` an. Vorhandene Zeile wird ersetzt, sonst angehängt.
 */
class BackupRotateTokenCommand extends Command {
    protected $signature = 'workdiary:backup:rotate-token {--length=64 : Tokenlänge in Zeichen}';

    protected $description = 'Erzeugt einen neuen Backup-Heartbeat-Token und schreibt ihn in die .env-Datei.';

    public function handle(): int {
        $length = max(32, (int) $this->option('length'));
        $token = Str::random($length);

        $envPath = $this->resolveEnvPath();
        if ($envPath === null || ! is_file($envPath) || ! is_writable($envPath)) {
            $this->error('.env-Datei nicht gefunden oder nicht beschreibbar: ' . (string) $envPath);

            return self::FAILURE;
        }

        $contents = ToolkitFile::read($envPath);
        $replacement = 'BACKUP_HEARTBEAT_TOKEN=' . $token;

        if (preg_match('/^BACKUP_HEARTBEAT_TOKEN=.*$/m', $contents) === 1) {
            $contents = (string) preg_replace('/^BACKUP_HEARTBEAT_TOKEN=.*$/m', $replacement, $contents);
        } else {
            if ($contents !== '' && ! str_ends_with($contents, "\n")) {
                $contents .= "\n";
            }
            $contents .= $replacement . "\n";
        }

        try {
            ToolkitFile::write($envPath, $contents);
        } catch (Throwable) {
            $this->error('Konnte .env nicht schreiben: ' . $envPath);

            return self::FAILURE;
        }

        AuditLog::create([
            'organization_id' => null,
            'user_id' => null,
            'event' => 'backup.tokenRotated',
            'auditable_type' => self::class,
            'auditable_id' => 0,
            'changes' => [
                'token_hash' => CryptoHelper::hash($token),
                'length' => $length,
            ],
            'ip' => null,
            'user_agent' => null,
        ]);

        $this->info('Neuer Backup-Heartbeat-Token (bitte einmalig sicher übertragen):');
        $this->line($token);
        $this->newLine();
        $this->comment('Tipp: php artisan config:clear, damit der neue Token sofort greift.');

        return self::SUCCESS;
    }

    private function resolveEnvPath(): ?string {
        $path = app()->environmentFilePath();

        return $path !== '' ? $path : null;
    }
}
