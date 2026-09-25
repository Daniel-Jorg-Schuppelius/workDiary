<?php
/*
 * Created on   : Fri Sep 25 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : SyncBaseInterestRateCommand.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Console\Commands\Invoicing;

use App\Services\Invoicing\BaseInterestRateService;
use Illuminate\Console\Command;
use Throwable;

/** Holt den Basiszinssatz nach § 247 BGB von der Bundesbank (MVP-879). */
class SyncBaseInterestRateCommand extends Command {
    protected $signature = 'invoicing:base-rate-sync';

    protected $description = 'Importiert den Basiszinssatz nach § 247 BGB aus der Bundesbank-Zeitreihe.';

    public function handle(BaseInterestRateService $rates): int {
        try {
            $changed = $rates->import();
        } catch (Throwable $e) {
            $this->error('Import fehlgeschlagen: ' . $e->getMessage());

            return self::FAILURE;
        }

        $this->info("Basiszinssatz abgeglichen: {$changed} Perioden neu oder geändert.");

        return self::SUCCESS;
    }
}
