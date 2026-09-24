<?php
/*
 * Created on   : Thu Sep 24 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : RetentionPolicyProvider.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Retention\Contracts;

use App\Services\Retention\RetentionPolicy;

/**
 * Erweiterungspunkt der Aufbewahrung (MVP-863): Jedes Modul meldet seine
 * Löschbereiche selbst ({@see RetentionPolicy}: überfällige Datensätze,
 * Ausnahmen, eigene Löschlogik) über `Manifest::extensions()`; die Plattform
 * kennt kein Fachmodul mehr.
 */
interface RetentionPolicyProvider {
    /** @return list<RetentionPolicy> */
    public function policies(): array;
}
