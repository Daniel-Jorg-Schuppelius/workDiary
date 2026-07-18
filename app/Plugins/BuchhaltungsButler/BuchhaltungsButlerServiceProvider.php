<?php
/*
 * Created on   : Sat Jul 18 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : BuchhaltungsButlerServiceProvider.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Plugins\BuchhaltungsButler;

use App\Models\Invoice;
use App\Plugins\BuchhaltungsButler\Observers\BhbInvoiceObserver;
use App\Plugins\BuchhaltungsButler\Services\BhbOutboxDispatcher;
use App\Services\Integration\IntegrationOutboxDispatcherResolver;
use Illuminate\Support\ServiceProvider;

/**
 * Bootet das BuchhaltungsButler-Plugin (MVP-432): Config-Defaults, Invoice-
 * Observer (Statuswechsel → Outbox-Enqueue) und Outbox-Dispatcher. Keine
 * eigenen Routen/Views — Konfiguration über die Auto-Form der Plugin-Karte.
 */
class BuchhaltungsButlerServiceProvider extends ServiceProvider {
    public function register(): void {
        $this->mergeConfigFrom(__DIR__ . '/config.php', 'plugins.' . BuchhaltungsButlerPlugin::ID);
    }

    public function boot(): void {
        Invoice::observe(BhbInvoiceObserver::class);
        app(IntegrationOutboxDispatcherResolver::class)->register(new BhbOutboxDispatcher());
    }
}
