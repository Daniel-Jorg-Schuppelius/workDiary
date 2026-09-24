<?php
/*
 * Created on   : Thu Sep 24 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : PluginCapabilitySource.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Plugins\Support\Contracts;

/**
 * Fähigkeiten eines Plugins aus Fachregistries für die Admin-Übersicht
 * (Welle 4.2): das Fachmodul sagt, ob ein Plugin bei ihm eingetragen ist.
 * Registrierung über `Manifest::extensions()`.
 */
interface PluginCapabilitySource {
    /** Anzeige-Label der Fähigkeit oder null, wenn das Plugin sie nicht hat. */
    public function labelFor(string $pluginId): ?string;
}
