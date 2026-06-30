<?php
/*
 * Created on   : Mon Jun 30 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : SecurityRekeyEncryptedCommand.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\{Crypt, DB};

/**
 * Verschlüsselt alle `encrypted`-Felder mit dem aktuellen APP_KEY neu.
 *
 * Voraussetzung: Während des Laufs muss der ALTE Schlüssel in
 * `APP_PREVIOUS_KEYS` und der NEUE in `APP_KEY` stehen — dann entschlüsselt
 * Laravel mit beiden und verschlüsselt mit dem neuen. Der Command liest jedes
 * Feld roh, entschlüsselt es (alt oder neu) und schreibt es mit dem neuen
 * Schlüssel zurück. Idempotent: ein zweiter Lauf schadet nicht.
 *
 * Felder mit NULL/leer werden übersprungen (ein leerer String ist keine
 * gültige Payload, siehe LexofficeContactSync-Vorfall).
 */
class SecurityRekeyEncryptedCommand extends Command {
    protected $signature = 'security:rekey-encrypted {--dry-run : Nur zählen/prüfen, nichts schreiben}';

    protected $description = 'Schlüsselt alle verschlüsselten DB-Felder auf den aktuellen APP_KEY um (APP_PREVIOUS_KEYS muss den alten Key enthalten).';

    /** @var array<class-string, list<string>> Model → verschlüsselte Felder */
    private const MAP = [
        \App\Models\User::class => ['two_factor_secret', 'two_factor_recovery_codes', 'tax_identification_number', 'social_security_number'],
        \App\Models\Auth\TwoFactorCredential::class => ['secret', 'data'],
        \App\Models\ContactAddress::class => ['street', 'supplement', 'zip', 'city'],
        \App\Models\ContactBankAccount::class => ['account_holder', 'iban', 'bic'],
        \App\Models\Finance\BankAccount::class => ['iban', 'bic', 'account_holder'],
        \App\Models\Finance\BankTransaction::class => ['counterparty_name', 'counterparty_iban', 'purpose'],
        \App\Models\PluginSetting::class => ['settings'],
        \App\Models\Integration\WebhookEndpoint::class => ['secret'],
        \App\Models\SoftwareInstallation::class => ['license_key'],
        \App\Models\SupplierCatalogSource::class => ['remote_password'],
        \App\Models\Location\LocationPoint::class => ['lat', 'lng'],
    ];

    public function handle(): int {
        $dry = (bool) $this->option('dry-run');
        $this->info(($dry ? '[DRY-RUN] ' : '') . 'Re-Key verschlüsselter Felder …');

        $sumRe = 0;
        $sumErr = 0;

        foreach (self::MAP as $class => $fields) {
            if (! class_exists($class)) {
                continue;
            }
            $model = new $class;
            $table = $model->getTable();
            $key = $model->getKeyName();

            foreach ($fields as $field) {
                $re = 0;
                $err = 0;

                DB::table($table)
                    ->select([$key, $field])
                    ->whereNotNull($field)
                    ->where($field, '!=', '')
                    ->orderBy($key)
                    ->chunkById(500, function ($rows) use ($table, $field, $key, $dry, &$re, &$err): void {
                        foreach ($rows as $row) {
                            try {
                                $plain = Crypt::decryptString($row->{$field});
                                if (! $dry) {
                                    DB::table($table)
                                        ->where($key, $row->{$key})
                                        ->update([$field => Crypt::encryptString($plain)]);
                                }
                                $re++;
                            } catch (\Throwable $e) {
                                $err++;
                                $this->warn("  {$table}.{$field} #{$row->{$key}}: {$e->getMessage()}");
                            }
                        }
                    }, $key);

                $this->line(sprintf('  %-24s %-22s umgeschlüsselt=%d fehler=%d', $table, $field, $re, $err));
                $sumRe += $re;
                $sumErr += $err;
            }
        }

        $this->info(($dry ? '[DRY-RUN] ' : '') . "Fertig — umgeschlüsselt: {$sumRe}, Fehler: {$sumErr}");

        return $sumErr === 0 ? self::SUCCESS : self::FAILURE;
    }
}
