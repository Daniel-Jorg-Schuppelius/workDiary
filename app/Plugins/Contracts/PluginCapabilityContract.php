<?php
/*
 * Created on   : Sun Aug 02 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : PluginCapabilityContract.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Plugins\Contracts;

/**
 * Vertrag einer Plugin-Fähigkeit (Review 2026-08, W5e / Entscheidung E-6).
 *
 * Kern-Fähigkeiten liefert das geschlossene Enum {@see PluginCapability};
 * Plugins außerhalb von app/Plugins (Escape-Hatch `config('plugins.classes')`)
 * können eigene Fähigkeiten als Implementierung dieses Interfaces mitbringen
 * und über die {@see \App\Plugins\CapabilityRegistry} registrieren.
 *
 * Konsumenten (z. B. {@see \App\Plugins\PluginManager::withCapability()})
 * vergleichen über {@see identifier()} — nie über Instanz-Identität.
 */
interface PluginCapabilityContract {
    /** Stabiler Maschinen-Identifier (snake_case, z. B. 'contact_sync'). */
    public function identifier(): string;

    /** Übersetztes UI-Label (Badge in der Plugin-Übersicht). */
    public function label(): string;

    /**
     * Das Contract-Interface, das ein Plugin mit dieser Fähigkeit implementieren
     * muss — erzwungen von `plugin:doctor` und dem PluginContractTest.
     *
     * @return class-string
     */
    public function interface(): string;
}
