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
use App\Plugins\Easybill\Services\{EasybillTarget, EasybillVoucherPullService};
use App\Plugins\Support\PluginServiceProviderBase;
use App\Services\Finance\Accounting\Vouchers\VoucherPullerRegistry;
use App\Services\Finance\Targets\FacturationTargetRegistry;

/**
 * Bootet das easybill-Plugin (MVP-431): Config-Defaults unter
 * `plugins.easybill.*` + Sync-Command für den Beleg-Rückabruf. Keine eigenen
 * Routen/Views — Konfiguration über die Auto-Form der Plugin-Karte, die
 * Übergabe über den {@see \App\Plugins\Easybill\Services\EasybillTarget}.
 */
class EasybillServiceProvider extends PluginServiceProviderBase {
    protected function pluginId(): string {
        return EasybillPlugin::ID;
    }

    protected function registerPlugin(): void {
        $this->commands([EasybillSyncCommand::class]);
    }

    protected function bootPlugin(): void {
        // Faktura-Übergabe (MVP-1032): der Kern kennt das Ziel nur über die Registry.
        $this->app->make(FacturationTargetRegistry::class)->register(EasybillTarget::class);

        // Beleg-Rückabruf (MVP-1031): der Kern kennt die Anbindung nur über die Registry.
        $this->app->make(VoucherPullerRegistry::class)->register(EasybillVoucherPullService::class);
    }
}
