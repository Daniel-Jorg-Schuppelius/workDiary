<?php
/*
 * Created on   : Fri Jul 17 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : PluginOrgContext.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Plugins\Support;

use App\Models\Organization;

/**
 * Zentrale Auflösung des Container-gebundenen Org-Kontexts der Plugin-Schicht
 * (Konsolidierung B7): ersetzt das kopierte
 * `app()->bound('currentOrganization') ? app('currentOrganization') : null`
 * samt instanceof-Guard in Plugins, Configs und Intake-Controllern.
 */
final class PluginOrgContext {
    public static function currentOrNull(): ?Organization {
        $organization = app()->bound('currentOrganization') ? app('currentOrganization') : null;

        return $organization instanceof Organization ? $organization : null;
    }

    public static function currentId(): ?int {
        $organization = self::currentOrNull();

        return $organization !== null ? (int) $organization->id : null;
    }
}
