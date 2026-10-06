<?php
/*
 * Created on   : Fri Sep 25 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : LexofficeSyncVoucherCategoriesCommand.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Plugins\Lexoffice\Console;

use App\Console\Concerns\IteratesOrganizations;
use App\Plugins\Lexoffice\LexofficeConfig;
use App\Plugins\Lexoffice\Services\LexofficeVoucherCategorySync;
use Illuminate\Console\Command;
use Illuminate\Contracts\Cache\LockTimeoutException;
use Illuminate\Support\Facades\Cache;

/**
 * Kategoriezeilen der gespiegelten Lexoffice-Einkaufsbelege nachladen
 * (MVP-905), rückwirkend in Häppchen wie die Positionen.
 */
class LexofficeSyncVoucherCategoriesCommand extends Command {
    use IteratesOrganizations;

    protected $signature = 'lexoffice:sync-voucher-categories ' . self::ORGANIZATION_OPTION . ' {--limit=200 : Belege je Lauf} {--all : Alle fehlenden Belege in Häppchen nachladen}';

    protected $description = 'Lädt Buchungskategorien und die Kategoriezeilen der Lexoffice-Einkaufsbelege nach.';

    public function handle(): int {
        $limit = max(1, (int) $this->option('limit'));
        foreach ($this->organizationsToProcess() as $org) {
            $config = LexofficeConfig::resolve($org->id);
            if ($config['enabled'] !== true || ! is_string($config['api_key']) || $config['api_key'] === '') {
                continue;
            }
            try {
                Cache::lock(LexofficeConfig::apiLockKey((int) $org->id), 3600)->block(LexofficeConfig::API_LOCK_WAIT_SCHEDULED, function () use ($org, $config, $limit): void {
                    try {
                        $sync = new LexofficeVoucherCategorySync($config['api_key'], $config['base_url'], $config['request_interval']);
                        do {
                            $result = $sync->syncMissing($org, $limit);
                            $this->line("Organisation #{$org->id} ({$org->name}): {$result['synced']} Belege, {$result['failed']} Fehler, {$result['remaining']} offen");
                        } while ($this->option('all') && $result['remaining'] > 0 && $result['synced'] > 0);
                    } catch (\Throwable $e) {
                        $this->error("  Fehler: {$e->getMessage()}");
                    }
                });
            } catch (LockTimeoutException) {
                $this->warn("Organisation #{$org->id} ({$org->name}): anderer Lexoffice-Lauf blockiert — übersprungen.");
            }
        }

        return self::SUCCESS;
    }
}
