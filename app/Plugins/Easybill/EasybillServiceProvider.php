<?php
/*
 * Created on   : Sat Jul 18 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : EasybillServiceProvider.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Plugins\Easybill;

use App\Plugins\Easybill\Console\EasybillSyncCommand;
use Illuminate\Support\ServiceProvider;

/**
 * Bootet das easybill-Plugin (MVP-431): Config-Defaults unter
 * `plugins.easybill.*` + Sync-Command für den Beleg-Rückabruf. Keine eigenen
 * Routen/Views — Konfiguration über die Auto-Form der Plugin-Karte, die
 * Übergabe über den {@see \App\Services\Finance\Targets\EasybillTarget}.
 */
class EasybillServiceProvider extends ServiceProvider {
    public function register(): void {
        $this->mergeConfigFrom(__DIR__ . '/config.php', 'plugins.' . EasybillPlugin::ID);

        if ($this->app->runningInConsole()) {
            $this->commands([EasybillSyncCommand::class]);
        }
    }
}
