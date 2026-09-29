<?php
/*
 * Created on   : Tue Sep 29 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : UpdateGeoDatabaseCommand.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Console\Commands\Security;

use App\Services\Security\{GeoDatabaseUpdateException, GeoDatabaseUpdater};
use Illuminate\Console\Command;

/**
 * Monatliche Aktualisierung der lokalen IP-Geodatenbank (Feature 085,
 * MVP-1021). Ohne GEOIP_AUTO_UPDATE läuft der Scheduler-Job leer durch: die
 * Datei pflegt dann der Betreiber selbst.
 */
class UpdateGeoDatabaseCommand extends Command {
    protected $signature = 'security:geoip-update {--force : Auch laden, wenn die installierte Datenbank aktuell ist}';

    protected $description = 'Lädt die DB-IP-Lite-Geodatenbank des Monats, prüft sie und tauscht sie atomar aus';

    public function handle(GeoDatabaseUpdater $updater): int {
        if (! (bool) config('geoip.update.enabled') && ! $this->option('force')) {
            $this->info('Automatische Aktualisierung ist abgeschaltet (GEOIP_AUTO_UPDATE).');

            return self::SUCCESS;
        }

        try {
            $result = $updater->update((bool) $this->option('force'));
        } catch (GeoDatabaseUpdateException $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }

        $this->info($result['status'] === 'updated'
            ? "Geodatenbank {$result['month']} installiert."
            : "Geodatenbank {$result['month']} ist aktuell.");

        return self::SUCCESS;
    }
}
