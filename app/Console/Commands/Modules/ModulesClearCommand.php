<?php
/*
 * Created on   : Wed Sep 23 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ModulesClearCommand.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Console\Commands\Modules;

use App\Modules\ModuleRegistry;
use Illuminate\Console\Command;

/**
 * Entfernt den Manifest-Cache. (MVP-861)
 */
class ModulesClearCommand extends Command {
    protected $signature = 'modules:clear';

    protected $description = 'Entfernt den Manifest-Cache.';

    public function handle(ModuleRegistry $registry): int {
        $registry->clearCache();
        $this->info('Manifest-Cache entfernt.');

        return self::SUCCESS;
    }
}
