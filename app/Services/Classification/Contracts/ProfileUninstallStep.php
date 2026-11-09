<?php
/*
 * Created on   : Mon Nov 09 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ProfileUninstallStep.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Classification\Contracts;

use App\Models\Platform\Organization;

/**
 * Optionaler Gegenpart eines {@see ProfileInstallStep} (MVP-1508): räumt beim
 * Deinstallieren auf, was der Schritt angelegt hat und niemand benutzt. Schritte
 * ohne diesen Vertrag lassen ihre Einträge als Stammdaten stehen.
 */
interface ProfileUninstallStep {
    /**
     * @param  array<mixed>  $rows  Zeilen des Profilabschnitts (Profil-Array, ungeprüft)
     * @param  list<string>  $remainingProfileCodes  danach noch installierte Profile
     * @return array{removed: int, kept: int}
     */
    public function uninstall(Organization $organization, array $rows, array $remainingProfileCodes): array;
}
