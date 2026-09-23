<?php
/*
 * Created on   : Mon Sep 14 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : WithPluginSecrets.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace Tests\Concerns;

use App\Models\Platform\{Organization, PluginSetting};
use LogicException;

/**
 * Plugin-Zugangsdaten dort hinterlegen, wo sie im Betrieb stehen: in den
 * Einstellungen der Organisation.
 *
 * Bis zum Sicherheitsaudit 2026-09-13 setzten die Plugin-Tests ihre
 * Geheimnisse über `config()`. Das funktionierte nur, weil Geheimnisse auf die
 * `.env` des Betreibers zurückfielen — genau der mandantenübergreifende Weg,
 * der seitdem geschlossen ist. Wer hier `config()` nutzt, testet eine
 * Konfiguration, die es in der Vorgabe nicht mehr gibt.
 */
trait WithPluginSecrets {
    /**
     * @param  array<string, mixed>  $settings
     */
    protected function pluginSecret(string $pluginId, array $settings, ?int $organizationId = null): PluginSetting {
        $organizationId ??= $this->pluginSecretOrganizationId();

        $row = PluginSetting::query()->withoutGlobalScopes()
            ->where('organization_id', $organizationId)
            ->where('plugin_id', $pluginId)
            ->first();

        if ($row === null) {
            $row = new PluginSetting();
            $row->organization_id = $organizationId;
            $row->plugin_id = $pluginId;
            $row->enabled = true;
        }

        $row->settings = array_merge((array) ($row->settings ?? []), $settings);
        $row->save();

        return $row;
    }

    /** Testklassen benennen ihre Organisation unterschiedlich; lieber laut scheitern als auf 0 schreiben. */
    private function pluginSecretOrganizationId(): int {
        foreach (['organization', 'org'] as $property) {
            $candidate = $this->{$property} ?? null;
            if ($candidate instanceof Organization && $candidate->exists) {
                return (int) $candidate->id;
            }
        }

        $bound = app()->bound('currentOrganization') ? app('currentOrganization') : null;
        if ($bound instanceof Organization && $bound->exists) {
            return (int) $bound->id;
        }

        throw new LogicException('pluginSecret(): keine Organisation gefunden - dritten Parameter setzen.');
    }
}
