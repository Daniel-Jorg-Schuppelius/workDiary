<?php
/*
 * Created on   : Tue Sep 29 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : InvoicePlaneServiceProvider.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Plugins\InvoicePlane;

use App\Plugins\InvoicePlane\Schema\{NullVoucherReaderFactory, VoucherReaderFactory};
use App\Plugins\InvoicePlane\Services\{InvoicePlaneArticleCatalogSource, InvoicePlaneVoucherPullService};
use App\Services\Finance\Accounting\Vouchers\VoucherPullerRegistry;
use App\Services\Platform\Catalog\ArticleCatalog;
use Illuminate\Support\ServiceProvider;

/**
 * InvoicePlane hat mangels API keine Plugin-Klasse (Feature 086) und läuft
 * daher nicht über die Plugin-Discovery. Dieser Provider trägt die Anbindung in
 * die Kern-Registries ein (MVP-1031), statt dass der Kern sie kennt.
 */
class InvoicePlaneServiceProvider extends ServiceProvider {
    public function register(): void {
        // Ohne Pilotinstanz gibt es keinen InvoicePlane-Leser — und damit
        // keinen erfundenen Beleg (Feature 086).
        $this->app->bind(VoucherReaderFactory::class, NullVoucherReaderFactory::class);
        // Ohne Plugin-Klasse gibt es keine plugin_settings-Zeile: der Modulschalter
        // gilt so auch für die Plugin-Bindung des Zeitplans und den Puller (MVP-1490).
        config(['plugins.invoiceplane.enabled' => (bool) config('invoiceplane.enabled')]);
    }

    public function boot(): void {
        $this->app->make(VoucherPullerRegistry::class)->register(InvoicePlaneVoucherPullService::class);
        // Artikelkatalog (Feature 185, gap-09): `ip_products` erreicht den Kern nur über diese Quelle.
        $this->app->make(ArticleCatalog::class)->register($this->app->make(InvoicePlaneArticleCatalogSource::class));
    }
}
