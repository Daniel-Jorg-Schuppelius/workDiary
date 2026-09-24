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

use App\Modules\{ManifestChecker, ModuleRegisterDocument, ModuleRegistry};
use CommonToolkit\Helper\FileSystem\File;
use Illuminate\Console\Command;

/**
 * Prüft die Modul-Manifeste auf Vollständigkeit (Tabellen, Lizenzcodes, Routen, Ordner, Rechtegruppen). (MVP-861)
 * Liegt das Architektur-Repo daneben, muss das Modulregister dem Generatorstand entsprechen (MVP-873).
 */
class ModulesCheckCommand extends Command {
    protected $signature = 'modules:check';

    protected $description = 'Prüft die Modul-Manifeste auf Vollständigkeit (Tabellen, Lizenzcodes, Routen, Ordner, Rechtegruppen).';

    public function handle(ModuleRegistry $registry): int {
        $violations = (new ManifestChecker($registry, base_path()))->check();
        $register = base_path(ModuleRegisterDocument::RELATIVE_PATH);
        if (File::exists($register)) {
            /** @var array<string, string> $helpRoutes */
            $helpRoutes = (array) config('help-topics.routes', []);
            if (File::read($register) !== (new ModuleRegisterDocument($registry))->render($helpRoutes)) {
                $violations[] = 'modul-register.md ist veraltet — php artisan modules:doc (MVP-873).';
            }
        }
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
