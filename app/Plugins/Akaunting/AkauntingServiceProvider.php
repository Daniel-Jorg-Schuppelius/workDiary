<?php
/*
 * Created on   : Sat Oct 24 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : AkauntingServiceProvider.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Plugins\Akaunting;

use App\Plugins\Akaunting\Services\{AkauntingVoucherLinks, AkauntingVoucherPullService};
use App\Plugins\Support\PluginServiceProviderBase;
use App\Services\Billing\Feed\DocumentFeedSourceRegistry;
use App\Services\Finance\Accounting\Vouchers\VoucherPullerRegistry;

class AkauntingServiceProvider extends PluginServiceProviderBase {
    protected function pluginId(): string {
        return AkauntingPlugin::ID;
    }

    protected function bootPlugin(): void {
        // Beleg-Rückabruf: der Kern kennt die Anbindung nur über die Registry.
        $this->app->make(VoucherPullerRegistry::class)->register(AkauntingVoucherPullService::class);
        // Link zum Original im Belegfluss.
        $this->app->make(DocumentFeedSourceRegistry::class)->register(new AkauntingVoucherLinks);
    }
}
