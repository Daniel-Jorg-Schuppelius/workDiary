<?php
/*
 * Created on   : Fri Oct 23 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : RedmineServiceProvider.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Plugins\Redmine;

use App\Plugins\Support\PluginServiceProviderBase;

/** Bootet den Redmine-Import (Routen, Views, Texte, Migrationen und Sync-Befehl). */
class RedmineServiceProvider extends PluginServiceProviderBase {
    protected function pluginId(): string {
        return RedminePlugin::ID;
    }

    protected function registerPlugin(): void {
        $this->commands([Console\RedmineSyncCommand::class]);
    }

    /** Datenführerschaft (MVP-1592): „Einmalig übernehmen“ spielt die Quellaufgabe erneut ein. */
    protected function bootPlugin(): void {
        $this->app->make(\App\Modules\ModuleRegistry::class)->contribute(\App\Services\Integration\Contracts\OwnershipConflictReplay::class, Services\RedmineImportService::class);
    }
}
