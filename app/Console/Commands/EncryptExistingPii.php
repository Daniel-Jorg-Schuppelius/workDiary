<?php
/*
 * Created on   : Sun Jun 07 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : EncryptExistingPii.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Support\Facades\{Crypt, DB};

/**
 * Verschlüsselt vorhandene Klartext-PII in den Spalten, die jetzt einen
 * encrypted-Cast tragen. Liest ROH (an den Casts vorbei), erkennt bereits
 * verschlüsselte Werte und überspringt sie → mehrfach ausführbar.
 *
 * WICHTIG: Vor dem Lauf ein Backup erstellen. Die Werte hängen am APP_KEY.
 */
class EncryptExistingPii extends Command {
    protected $signature = 'security:encrypt-existing {--dry-run : Nur zählen, nichts schreiben}';

    protected $description = 'Verschlüsselt vorhandene Klartext-PII-Felder (idempotent).';

    /** @var array<string, list<string>> */
    private const TARGETS = [
        'users' => ['tax_identification_number', 'social_security_number'],
        'contact_bank_accounts' => ['account_holder', 'iban', 'bic'],
        'contact_addresses' => ['street', 'supplement', 'zip', 'city'],
    ];

    public function handle(): int {
        $dry = (bool) $this->option('dry-run');
        $total = 0;

        foreach (self::TARGETS as $table => $columns) {
            if (! DB::getSchemaBuilder()->hasTable($table)) {
                continue;
            }
            $encrypted = 0;

            DB::table($table)->orderBy('id')->chunkById(500, function ($rows) use ($table, $columns, $dry, &$encrypted): void {
                foreach ($rows as $row) {
                    $update = [];
                    foreach ($columns as $col) {
                        $value = $row->{$col} ?? null;
                        if ($value === null || $value === '' || $this->isEncrypted((string) $value)) {
                            continue;
                        }
                        $update[$col] = Crypt::encryptString((string) $value);
                    }
                    if ($update !== []) {
                        $encrypted++;
                        if (! $dry) {
                            DB::table($table)->where('id', $row->id)->update($update);
                        }
                    }
                }
            });

            $this->line(sprintf('%-24s %d %s', $table, $encrypted, $dry ? 'zu verschlüsseln' : 'verschlüsselt'));
            $total += $encrypted;
        }

        $this->info(($dry ? 'Dry-Run: ' : '') . "Fertig. {$total} Datensätze betroffen.");

        return self::SUCCESS;
    }

    /** Bereits verschlüsselt, wenn sich der Wert als Laravel-Cipher entschlüsseln lässt. */
    private function isEncrypted(string $value): bool {
        try {
            Crypt::decryptString($value);

            return true;
        } catch (DecryptException) {
            return false;
        }
    }
}
