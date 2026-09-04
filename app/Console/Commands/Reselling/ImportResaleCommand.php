<?php
/*
 * Created on   : Fri Sep 04 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ImportResaleCommand.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Console\Commands\Reselling;

use App\Models\Organization;
use App\Models\Reselling\ResaleImport;
use App\Services\Reselling\Register\MarketplaceImporter;
use Illuminate\Console\Command;

/**
 * Anbieter-Exporte von der Konsole ins Reselling-Register bringen
 * (Feature 152, MVP-759) — dieselbe Pipeline wie der Import-Dialog.
 */
class ImportResaleCommand extends Command {
    protected $signature = 'resale:import
        {--org= : Organisation (ID)}
        {--telekom= : Telekom Cloud Marketplace purchases.csv}
        {--qualityhosting= : Quality-Hosting-Vertragsexport (XLSX)}
        {--pricelist= : Quality-Hosting-Preisliste (XLSX)}';

    protected $description = 'Anbieter-Exporte (Telekom, Quality Hosting, Preisliste) ins Reselling-Register importieren';

    public function handle(MarketplaceImporter $importer): int {
        $organization = Organization::query()->find((int) $this->option('org'));
        if ($organization === null) {
            $this->error('Organisation nicht gefunden (--org=ID).');

            return self::FAILURE;
        }
        $files = [];
        foreach ([ResaleImport::KIND_PURCHASES => 'telekom', ResaleImport::KIND_CONTRACTS => 'qualityhosting', ResaleImport::KIND_PRICELIST => 'pricelist'] as $kind => $option) {
            $path = (string) ($this->option($option) ?? '');
            if ($path === '') {
                continue;
            }
            if (! is_file($path)) {
                $this->error("Datei nicht gefunden: {$path}");

                return self::FAILURE;
            }
            $files[$kind] = ['name' => basename($path), 'path' => $path];
        }
        if ($files === []) {
            $this->error('Keine Datei angegeben (--telekom, --qualityhosting, --pricelist).');

            return self::FAILURE;
        }

        $failed = false;
        foreach ($importer->import($organization, null, $files) as $record) {
            if ($record->status === \App\Enums\Reselling\ImportStatus::Failed) {
                $failed = true;
                $this->error(sprintf('%s: %s', $record->kindLabel(), (string) $record->error));

                continue;
            }
            $this->info(sprintf('%s: %d Zeilen, %d neu, %d geändert, %d unverändert, %d ohne Halter', $record->kindLabel(), $record->rows_total, $record->rows_created, $record->rows_updated, $record->rows_unchanged, $record->rows_unassigned));
        }

        return $failed ? self::FAILURE : self::SUCCESS;
    }
}
