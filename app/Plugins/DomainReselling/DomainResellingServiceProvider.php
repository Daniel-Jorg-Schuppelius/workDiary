<?php
/*
 * Created on   : Thu Jul 16 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : DomainResellingServiceProvider.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Plugins\DomainReselling;

use App\Plugins\Support\PluginServiceProviderBase;

/**
 * Plugin-eigener ServiceProvider (Feature 083). Merged nur die Plugin-Config;
 * die Verwaltungs-UX (Verbindungen, Domains, Reseller, Berichte) liegt
 * app-seitig unter `admin.domain-provider.*`/`domains.*`/`domain-reseller.*`.
 */
class DomainResellingServiceProvider extends PluginServiceProviderBase {
    protected function pluginId(): string {
        return DomainResellingPlugin::ID;
    }
}
