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

use Illuminate\Support\ServiceProvider;

/**
 * Bootet das sevDesk-Plugin (MVP-125): hängt die plugin-eigenen
 * Config-Defaults unter `plugins.sevdesk.*` ein. Keine eigenen Routen/Views —
 * Konfiguration läuft über die Auto-Form der Plugin-Karte, die Übergabe über
 * den {@see \App\Services\Finance\Targets\SevDeskTarget}.
 */
class SevDeskServiceProvider extends ServiceProvider {
    public function register(): void {
        $this->mergeConfigFrom(__DIR__ . '/config.php', 'plugins.' . SevDeskPlugin::ID);
    }
}
