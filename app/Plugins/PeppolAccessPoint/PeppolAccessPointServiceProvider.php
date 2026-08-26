<?php
/*
 * Created on   : Wed Aug 26 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : PeppolAccessPointServiceProvider.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Plugins\PeppolAccessPoint;

use App\Console\Commands\Peppol\PeppolReceiveCommand;
use App\Plugins\Support\PluginServiceProviderBase;
use App\Services\Peppol\{PeppolInvoiceDispatcher, SmpHttpClient};
use ERechnungToolkit\Contracts\{DnsNaptrResolverInterface, SmpHttpClientInterface, ValidatorInterface};
use ERechnungToolkit\Peppol\BisValidator;
use ERechnungToolkit\Peppol\Dns\SystemNaptrResolver;

/**
 * Plugin-eigener ServiceProvider (Feature 066, MVP-734).
 *
 * Bindet die beiden Transport-Nähte der Teilnehmerauflösung — HTTP zum SMP und
 * DNS/NAPTR zum SML. Beide sind Interfaces des erechnung-toolkits und genau
 * deshalb hier gebunden: Tests ersetzen sie durch Fakes, sodass in der
 * Testsuite weder eine HTTP-Verbindung noch eine DNS-Abfrage entsteht.
 */
class PeppolAccessPointServiceProvider extends PluginServiceProviderBase {
    protected function pluginId(): string {
        return PeppolAccessPointPlugin::ID;
    }

    protected function registerPlugin(): void {
        $this->app->bind(SmpHttpClientInterface::class, SmpHttpClient::class);
        $this->app->bind(DnsNaptrResolverInterface::class, SystemNaptrResolver::class);

        // Peppol-BIS-Vorprüfung des Versands. Kontextuell gebunden: das
        // Toolkit-Interface trägt mehrere Validatoren (XSD, KoSIT), hier ist
        // ausdrücklich die BIS-Teilmenge gemeint.
        $this->app->when(PeppolInvoiceDispatcher::class)
            ->needs(ValidatorInterface::class)
            ->give(static fn (): BisValidator => new BisValidator);

        if ($this->app->runningInConsole()) {
            $this->commands([
                PeppolReceiveCommand::class,
            ]);
        }
    }
}
