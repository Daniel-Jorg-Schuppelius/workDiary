<?php
/*
 * Created on   : Mon Jul 20 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : CalendlyServiceProvider.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Plugins\Calendly;

use App\Models\DiaryEntry;
use App\Plugins\Calendly\Console\CalendlyBackfillCommand;
use App\Plugins\Calendly\Observers\CalendlyDiaryEntryObserver;
use App\Plugins\Support\PluginServiceProviderBase;

/**
 * Plugin-eigener ServiceProvider (Feature 095). Wird vom Core-
 * {@see \App\Providers\PluginServiceProvider} geladen, sobald CalendlyPlugin in
 * der Registry steht. Liefert Config-Defaults, lädt Routes + Views, registriert
 * den Cancel-Sync-Trigger (P5) und stellt den Backfill-Command bereit.
 */
class CalendlyServiceProvider extends PluginServiceProviderBase {
    protected function pluginId(): string {
        return CalendlyPlugin::ID;
    }

    protected function bootPlugin(): void {
        // Outbound-Cancel-Sync (P5): app-seitiger Storno eines bestätigten
        // Calendly-Termins wird best effort gegen Calendly abgeglichen.
        DiaryEntry::observe(CalendlyDiaryEntryObserver::class);

        if ($this->app->runningInConsole()) {
            $this->commands([
                CalendlyBackfillCommand::class,
            ]);
        }
    }
}
