<?php
/*
 * Created on   : Thu Sep 24 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ProfileInstallStep.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Classification\Contracts;

use App\Models\Platform\{Organization, User};

/**
 * Erweiterungspunkt der Branchenprofil-Installation (MVP-863): Ein Modul
 * installiert seinen Profilabschnitt selbst (Prozedurvorlagen, …) und meldet
 * den Schritt über `Manifest::extensions()`. Der Installer kennt kein Fachmodul.
 */
interface ProfileInstallStep {
    /** Schlüssel des Abschnitts im Profil und in den Zählern (z. B. `procedure_templates`). */
    public function key(): string;

    /**
     * @param  array<mixed>  $rows  Zeilen des Profilabschnitts (Profil-Array, ungeprüft)
     * @return array{created: int, skipped: int}
     */
    public function install(Organization $organization, array $rows, ?User $actor): array;
}
