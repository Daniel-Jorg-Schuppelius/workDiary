<?php
/*
 * Created on   : Sun Oct 04 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ChecksPluginSwitch.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Plugins\Support\Console;

use App\Plugins\Support\{PluginSettingsResolver, PluginTenantGate};

/**
 * Die eine Schalterprüfung der geplanten Plugin-Kommandos
 * (Konsolidierungs-Audit 2026-10, k2-06): schaltet eine Organisation ein
 * Plugin ab, stehen auch seine Läufe — die bestehende Verbindung allein hält
 * sie nicht am Leben. Gate `PluginCommandSwitchRuleTest`.
 */
trait ChecksPluginSwitch {
    /** @var array<string, bool> */
    private array $pluginSwitches = [];

    /** Org-Schalter vor Config, je Lauf einmal gelesen; für gesperrte Mandanten steht jeder Lauf. */
    protected function pluginEnabledFor(string $pluginId, int $organizationId): bool {
        return $this->pluginSwitches[$pluginId . '#' . $organizationId]
            ??= ! PluginTenantGate::blocks($organizationId) && PluginSettingsResolver::for($pluginId, $organizationId)->enabled();
    }
}
