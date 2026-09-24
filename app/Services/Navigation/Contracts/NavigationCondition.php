<?php
/*
 * Created on   : Thu Sep 24 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : NavigationCondition.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Navigation\Contracts;

use App\Models\Platform\{Organization, User};

/**
 * Erweiterungspunkt der Navigation (Welle 4.4): ein Fachmodul entscheidet,
 * ob einer seiner Einträge erscheint (Branchenprofil, sichtbare Inhalte,
 * eigenes Hauptbuch). Fehlt das Modul, bleibt der Eintrag verborgen.
 * Registrierung über `Manifest::extensions()`.
 */
interface NavigationCondition {
    /** Schlüssel, unter dem die Navigation die Bedingung abfragt. */
    public function key(): string;

    public function passes(?User $user, ?Organization $organization): bool;
}
