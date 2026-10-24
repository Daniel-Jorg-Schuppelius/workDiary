<?php
/*
 * Created on   : Fri Oct 23 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : GiteaServiceProvider.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Plugins\Gitea;

use App\Plugins\Support\PluginServiceProviderBase;

/** Bootet den Gitea-/Forgejo-Import (Routen, Texte, Migrationen und Sync-Befehl). */
class GiteaServiceProvider extends PluginServiceProviderBase {
    protected function pluginId(): string {
        return GiteaPlugin::ID;
    }

    protected function registerPlugin(): void {
        $this->commands([Console\GiteaSyncCommand::class]);
    }
}
