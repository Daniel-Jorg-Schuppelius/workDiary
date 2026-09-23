<?php
/*
 * Created on   : Wed Sep 23 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ModulesCacheCommand.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Console\Commands\Modules;

use App\Modules\ModuleRegistry;
use Illuminate\Console\Command;

/**
 * Schreibt die Manifestliste nach bootstrap/cache/modules.php. (MVP-861)
 */
class ModulesCacheCommand extends Command {
    protected $signature = 'modules:cache';

    protected $description = 'Schreibt die Manifestliste nach bootstrap/cache/modules.php.';

    public function handle(ModuleRegistry $registry): int {
        $registry->cache();
        $this->info(sprintf('Manifest-Cache geschrieben: %d Module.', count($registry->all())));

        return self::SUCCESS;
    }
}
