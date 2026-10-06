<?php
/*
 * Created on   : Tue Oct 06 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : approvals.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

use App\Enums\User\UserRole;

/*
 * Freigabeketten: Vorgaben je Organisation überschreibbar unter
 * settings.approvals.* (Registry: config/settings-registry.php).
 */
return [
    // Stufenart → zuständige Rolle im Genehmigungs-Eingang (Entscheidung 2026-10-06).
    'step_role' => [
        'commercial' => UserRole::Buchhaltung->value,
        'technical' => UserRole::Teamleitung->value,
        'hr' => UserRole::Personalverwaltung->value,
    ],
];
