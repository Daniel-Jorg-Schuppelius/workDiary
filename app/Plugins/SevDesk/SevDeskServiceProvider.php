<?php
/*
 * Created on   : Sat Jul 12 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : SevDeskServiceProvider.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Plugins\SevDesk;

use App\Plugins\SevDesk\Services\{SevDeskTarget, SevDeskVoucherPullService};
use App\Plugins\Support\PluginServiceProviderBase;
use App\Services\Finance\Accounting\Vouchers\VoucherPullerRegistry;
use App\Services\Finance\Targets\FacturationTargetRegistry;

/**
 * Bootet das sevDesk-Plugin (MVP-125): hängt die plugin-eigenen
 * Config-Defaults unter `plugins.sevdesk.*` ein. Keine eigenen Routen/Views —
 * Konfiguration läuft über die Auto-Form der Plugin-Karte, die Übergabe über
 * den {@see \App\Plugins\SevDesk\Services\SevDeskTarget}.
 */
class SevDeskServiceProvider extends PluginServiceProviderBase {
    protected function pluginId(): string {
        return SevDeskPlugin::ID;
    }

    protected function registerPlugin(): void {
        // Beleg-Rückabruf (Feature 122, MVP-611).
        $this->commands([
            Console\SevDeskPullVouchersCommand::class,
        ]);
    }

    protected function bootPlugin(): void {
        // Faktura-Übergabe (MVP-1032): der Kern kennt das Ziel nur über die Registry.
        $this->app->make(FacturationTargetRegistry::class)->register(SevDeskTarget::class);

        // Beleg-Rückabruf (MVP-1031): der Kern kennt die Anbindung nur über die Registry.
        $this->app->make(VoucherPullerRegistry::class)->register(SevDeskVoucherPullService::class);
    }
}
