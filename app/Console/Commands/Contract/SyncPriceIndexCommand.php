<?php
/*
 * Created on   : Sun Sep 27 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : SyncPriceIndexCommand.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Console\Commands\Contract;

use App\Services\Contract\PriceIndexService;
use Illuminate\Console\Command;
use Throwable;

/** Holt den Verbraucherpreisindex aus der Bundesbank-Reihe (MVP-952); neue Werte warten auf Freigabe. */
class SyncPriceIndexCommand extends Command {
    protected $signature = 'contracts:price-index-sync';

    protected $description = 'Importiert den Verbraucherpreisindex (Basis 2020 = 100) aus der Bundesbank-Zeitreihe.';

    public function handle(PriceIndexService $index): int {
        try {
            $changed = $index->import();
        } catch (Throwable $e) {
            $this->error('Import fehlgeschlagen: ' . $e->getMessage());

            return self::FAILURE;
        }

        $this->info("Verbraucherpreisindex abgeglichen: {$changed} Monatswerte neu oder geändert.");

        return self::SUCCESS;
    }
}
