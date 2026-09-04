<?php
/*
 * Created on   : Fri Sep 04 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : LexofficeSyncVoucherLinesCommand.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Plugins\Lexoffice\Console;

use App\Console\Concerns\IteratesOrganizations;
use App\Plugins\Lexoffice\{LexofficeConfig, LexofficeVoucherLineSync};
use Illuminate\Console\Command;
use Illuminate\Contracts\Cache\LockTimeoutException;
use Illuminate\Support\Facades\Cache;

/**
 * Positionen der gespiegelten Lexoffice-Rechnungen nachladen (Feature 152,
 * MVP-760). Rückwirkend in Häppchen (`--limit`), damit das Ratenlimit hält;
 * `--all` läuft, bis nichts mehr fehlt.
 */
class LexofficeSyncVoucherLinesCommand extends Command {
    use IteratesOrganizations;

    protected $signature = 'lexoffice:sync-voucher-lines ' . self::ORGANIZATION_OPTION . ' {--limit=200 : Rechnungen je Lauf} {--all : Alle fehlenden Rechnungen in Häppchen nachladen}';

    protected $description = 'Lädt Positionen und Belegtexte der gespiegelten Lexoffice-Rechnungen in `lexoffice_voucher_lines` nach.';

    public function handle(): int {
        $organizations = $this->organizationsToProcess();
        if ($organizations->isEmpty()) {
            $this->warn('Keine Organisationen gefunden.');

            return self::SUCCESS;
        }
        $limit = max(1, (int) $this->option('limit'));
        foreach ($organizations as $org) {
            $config = LexofficeConfig::resolve($org->id);
            if ($config['enabled'] !== true || ! is_string($config['api_key']) || $config['api_key'] === '') {
                continue;
            }
            $lock = Cache::lock(LexofficeConfig::apiLockKey($org->id), 3600);
            try {
                $lock->block(600);
            } catch (LockTimeoutException) {
                $this->warn("Organisation #{$org->id} ({$org->name}): anderer Lexoffice-Lauf blockiert — übersprungen.");

                continue;
            }
            try {
                $sync = new LexofficeVoucherLineSync($config['api_key'], $config['base_url']);
                do {
                    $result = $sync->syncMissing($org, $limit);
                    $this->line("Organisation #{$org->id} ({$org->name}): {$result['synced']} Rechnungen, {$result['lines']} Positionen, {$result['failed']} Fehler, {$result['remaining']} offen");
                } while ($this->option('all') && $result['remaining'] > 0 && $result['synced'] > 0);
            } catch (\Throwable $e) {
                $this->error("  Fehler: {$e->getMessage()}");
            } finally {
                $lock->release();
            }
        }

        return self::SUCCESS;
    }
}
