<?php
/*
 * Created on   : Tue Sep 29 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : NavigationContributor.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Plugins\Contracts;

use App\Models\Platform\User;

/**
 * Plugins, die Einträge in die Menüs des Kerns beisteuern (MVP-1037). Der Kern
 * fragt aktive Plugins und solche mit {@see ContributesWhileInactive}; Rechte
 * prüft das Plugin selbst.
 */
interface NavigationContributor {
    /**
     * Einträge je Ziel: Schlüssel einer Sidebar-Gruppe (z. B. `sales-billing`)
     * oder `admin` für das Systemmenü. Einträge haben die Form der Kern-Einträge
     * (`route`, `label`, `icon`, optional `matches`, `badge`, `modal`); im
     * Systemmenü nennt `folder` den Ordner (`finance`, `data`, …).
     *
     * @return array<string, list<array<string, mixed>>>
     */
    public function navigationItems(User $user): array;

    /**
     * Zusätzliche Treffer-Muster für Kern-Einträge, damit Plugin-Seiten den
     * passenden Menüpunkt aktiv markieren.
     *
     * @return array<string, list<string>> Kern-Route → Routenmuster
     */
    public function navigationMatches(): array;
}
