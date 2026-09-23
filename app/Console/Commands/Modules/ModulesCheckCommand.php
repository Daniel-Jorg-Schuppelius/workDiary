<?php
/*
 * Created on   : Wed Sep 23 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ModulesCheckCommand.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Console\Commands\Modules;

use App\Modules\{ManifestChecker, ModuleRegistry};
use Illuminate\Console\Command;

/**
 * Prüft die Modul-Manifeste auf Vollständigkeit (Tabellen, Lizenzcodes, Routen, Ordner, Rechtegruppen). (MVP-861)
 */
class ModulesCheckCommand extends Command {
    protected $signature = 'modules:check';

    protected $description = 'Prüft die Modul-Manifeste auf Vollständigkeit (Tabellen, Lizenzcodes, Routen, Ordner, Rechtegruppen).';

    public function handle(ModuleRegistry $registry): int {
        $violations = (new ManifestChecker($registry, base_path()))->check();
        if ($violations === []) {
            $this->info(sprintf('Manifeste vollständig: %d Module, %d Tabellen zugeordnet.', count($registry->all()), array_sum(array_map(static fn ($m): int => count($m->tables()), $registry->all()))));

            return self::SUCCESS;
        }
        $this->error(sprintf('Manifeste unvollständig: %d Verstöße.', count($violations)));
        foreach ($violations as $line) {
            $this->line('  ' . $line);
        }

        return self::FAILURE;
    }
}
