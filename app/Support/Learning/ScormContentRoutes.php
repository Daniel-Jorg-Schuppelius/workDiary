<?php
/*
 * Created on   : Mon Sep 14 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ScormContentRoutes.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Support\Learning;

use Illuminate\Support\Facades\Route;

/**
 * Registriert die Routen des Inhalts-Hosts — nur, wenn einer konfiguriert ist, und
 * nur für genau diesen Host. Ohne Domain-Bindung wären sie auch am Anwendungs-Host
 * erreichbar, und der fremde Code liefe wieder im Ursprung der Anwendung.
 */
final class ScormContentRoutes {
    public static function register(): void {
        $host = ScormContentHost::host();

        if ($host === null) {
            return;
        }

        Route::domain($host)
            ->middleware('scorm-content')
            ->group(base_path('routes/scorm-content.php'));
    }
}
