<?php
/*
 * Created on   : Sat Oct 24 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : FireflyServiceProvider.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Plugins\Firefly;

use App\Modules\ModuleRegistry;
use App\Plugins\Support\PluginServiceProviderBase;
use App\Services\Search\Contracts\SearchDomain;

class FireflyServiceProvider extends PluginServiceProviderBase {
    protected function pluginId(): string {
        return FireflyPlugin::ID;
    }

    protected function registerPlugin(): void {
        $this->commands([Console\FireflySyncCommand::class]);
    }

    protected function bootPlugin(): void {
        $this->app->make(ModuleRegistry::class)->contribute(SearchDomain::class, Search\FireflyTransactionSearchDomain::class);
    }
}
