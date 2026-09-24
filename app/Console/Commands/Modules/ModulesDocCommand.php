<?php
/*
 * Created on   : Thu Sep 24 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ModulesDocCommand.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Console\Commands\Modules;

use App\Modules\{ModuleRegisterDocument, ModuleRegistry};
use CommonToolkit\Helper\FileSystem\{File, Folder};
use Illuminate\Console\Command;

/** Schreibt das Modulregister ins Architektur-Repo (MVP-873). */
class ModulesDocCommand extends Command {
    protected $signature = 'modules:doc {--path= : Zieldatei (Standard: ../WorkDiary-Architecture/modul-register.md)}';

    protected $description = 'Erzeugt ../WorkDiary-Architecture/modul-register.md aus den Modul-Manifesten.';

    public function handle(ModuleRegistry $registry): int {
        $option = $this->option('path');
        $path = is_string($option) && $option !== '' ? $option : base_path(ModuleRegisterDocument::RELATIVE_PATH);
        if (! Folder::exists(dirname($path))) {
            $this->warn('Zielordner nicht vorhanden — Modulregister nicht geschrieben: ' . dirname($path));

            return self::SUCCESS;
        }
        /** @var array<string, string> $helpRoutes */
        $helpRoutes = (array) config('help-topics.routes', []);
        File::write($path, (new ModuleRegisterDocument($registry))->render($helpRoutes));
        $this->info('Modulregister geschrieben: ' . $path);

        return self::SUCCESS;
    }
}
