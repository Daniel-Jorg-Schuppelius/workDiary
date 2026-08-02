<?php
/*
 * Created on   : Sat Jul 18 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : OrganizationContext.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Support;

use App\Models\Organization;

/**
 * Org-Kontext-Bind mit garantiertem Restore (Vollaudit 2026-07, M42): ersetzt
 * vier identische Kopien (IteratesOrganizations, DemoSeederService,
 * CloudIntakeRunner, CalendarEventPublishJob) und schließt das
 * sync-Driver-Leck der restore-losen Queue-Jobs — die Queue::before-Hygiene
 * greift beim sync-Driver bewusst nicht, dort blieb die Bindung sonst bis
 * Request-Ende stehen.
 */
final class OrganizationContext {
    /**
     * Führt $fn mit gebundener Organisation aus und stellt die vorherige
     * Bindung im finally wieder her (bzw. entfernt sie).
     *
     * @template TReturn
     *
     * @param  callable(): TReturn  $fn
     * @return TReturn
     */
    public static function run(Organization $organization, callable $fn): mixed {
        $bound = app()->bound('currentOrganization') ? app('currentOrganization') : null;
        $previous = $bound instanceof Organization ? $bound : null;
        app()->instance('currentOrganization', $organization);

        try {
            return $fn();
        } finally {
            if ($previous !== null) {
                app()->instance('currentOrganization', $previous);
            } else {
                app()->forgetInstance('currentOrganization');
            }
        }
    }

    /**
     * Führt $fn bewusst OHNE gebundene Organisation aus (globale Plugins,
     * Instanz-Ebene) und stellt die vorherige Bindung im finally wieder her —
     * ein rohes forgetInstance() würde in langlebigen Workern den Kontext
     * dauerhaft verlieren (Plugin-System-Review 2026-08, A13).
     *
     * @template TReturn
     *
     * @param  callable(): TReturn  $fn
     * @return TReturn
     */
    public static function runWithout(callable $fn): mixed {
        $bound = app()->bound('currentOrganization') ? app('currentOrganization') : null;
        $previous = $bound instanceof Organization ? $bound : null;
        app()->forgetInstance('currentOrganization');

        try {
            return $fn();
        } finally {
            if ($previous !== null) {
                app()->instance('currentOrganization', $previous);
            }
        }
    }
}
