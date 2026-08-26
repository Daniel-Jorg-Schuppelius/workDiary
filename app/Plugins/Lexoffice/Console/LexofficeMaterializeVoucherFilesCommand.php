<?php
/*
 * Created on   : Mon Aug 24 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : LexofficeMaterializeVoucherFilesCommand.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Plugins\Lexoffice\Console;

use App\Console\Concerns\IteratesOrganizations;
use App\Models\LexofficeVoucher;
use App\Plugins\Lexoffice\{LexofficeConfig, LexofficeVoucherFileService};
use Illuminate\Console\Command;

/**
 * Sichert alle Lexoffice-Belegbilder lokal (Feature 110-Folgeschnitt,
 * MVP-690 — Vollscan G3): Pflichtschritt vor dem Abschluss eines
 * Buchhaltungswechsels mit Quelle Lexoffice — nach Vertragsende ist die
 * API weg, die GoBD-Aufbewahrung bleibt.
 */
class LexofficeMaterializeVoucherFilesCommand extends Command {
    use IteratesOrganizations;

    protected $signature = 'lexoffice:materialize-voucher-files ' . self::ORGANIZATION_OPTION;

    protected $description = 'Sichert Lexoffice-Belegbilder lokal (GoBD-Aufbewahrung nach Vertragsende); Pflichtschritt vor Abschluss eines Buchhaltungswechsels.';

    public function handle(): int {
        $organizations = $this->organizationsToProcess();
        if ($organizations->isEmpty()) {
            $this->warn('Keine Organisationen gefunden.');

            return self::SUCCESS;
        }

        $failures = 0;
        foreach ($organizations as $org) {
            $config = LexofficeConfig::resolve($org->id);
            if (! is_string($config['api_key']) || $config['api_key'] === '') {
                $this->warn("Organisation #{$org->id} ({$org->name}): Lexoffice nicht konfiguriert — übersprungen.");

                continue;
            }

            $service = new LexofficeVoucherFileService($config['api_key'], $config['base_url']);
            $stored = 0;
            $empty = 0;
            $errors = 0;

            LexofficeVoucher::query()
                ->where('organization_id', $org->id)
                ->whereNull('file_materialized_at')
                ->orderBy('id')
                ->chunkById(100, function ($vouchers) use ($service, &$stored, &$empty, &$errors): void {
                    foreach ($vouchers as $voucher) {
                        try {
                            $service->materialize($voucher) ? $stored++ : $empty++;
                        } catch (\Throwable $e) {
                            $errors++;
                            $this->warn("  Beleg #{$voucher->id} ({$voucher->voucher_number}): {$e->getMessage()}");
                        }
                    }
                });

            $this->info("Organisation #{$org->id} ({$org->name}): gesichert {$stored}, ohne Belegbild {$empty}, Fehler {$errors}");
            $failures += $errors;
        }

        return $failures === 0 ? self::SUCCESS : self::FAILURE;
    }
}
