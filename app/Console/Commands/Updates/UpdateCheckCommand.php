<?php
/*
 * Created on   : Wed Jul 08 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : UpdateCheckCommand.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Console\Commands\Updates;

use App\Services\Updates\UpdateCheckService;
use Illuminate\Console\Command;

/**
 * Update-Verfügbarkeitsprüfung (MVP-054). Läuft als Registry-Job,
 * respektiert aber den Opt-in-Modus: nur `auto` prüft unbeaufsichtigt
 * nach außen (On-Premise-Default ist `manual` — kein Phone-Home).
 */
class UpdateCheckCommand extends Command {
    protected $signature = 'updates:check {--force : Auch im manual-Modus prüfen (interaktiver Aufruf)}';

    protected $description = 'Prüft den signierten Update-Feed auf neue Versionen für Anwendung und Plugins';

    public function handle(UpdateCheckService $updates): int {
        $mode = $updates->mode();

        if ($mode === UpdateCheckService::MODE_DISABLED) {
            $this->info('Update-Check ist deaktiviert (updates.check_mode=disabled).');

            return self::SUCCESS;
        }
        if ($mode === UpdateCheckService::MODE_MANUAL && !$this->option('force')) {
            $this->info('Update-Check steht auf manual — kein automatischer Abruf (--force für manuellen Lauf).');

            return self::SUCCESS;
        }

        try {
            $open = $updates->checkRemote();
            $this->info("Update-Check abgeschlossen: {$open} offene(s) Update(s).");

            return self::SUCCESS;
        } catch (\Throwable $e) {
            $this->error('Update-Check fehlgeschlagen: ' . $e->getMessage());

            return self::FAILURE;
        }
    }
}
