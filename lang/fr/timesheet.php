<?php
/*
 * Created on   : Sun May 17 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : timesheet.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

return [
    'titles' => [
        'index' => 'Feuille de temps',
        'show' => 'Feuille de temps n°:id',
    ],
    'fields' => [
        'date' => 'Date',
        'project' => 'Projet',
        'user' => 'Employé',
        'status' => 'Statut',
        'started_at' => 'Début',
        'ended_at' => 'Fin',
        'break_minutes' => 'Pause (min)',
        'duration' => 'Durée',
        'kind' => 'Type',
        'description' => 'Description',
        'notes' => 'Notes',
    ],
    'totals' => [
        'work' => 'Total travail',
        'break' => 'Total pause',
        'material_net' => 'Total matériel (net)',
    ],
    'sections' => [
        'entries' => 'Saisies de temps',
        'materials' => 'Matériaux',
        'customer_release' => 'Validation client',
        'notes' => 'Notes',
    ],
    'signature' => [
        'signed_at' => 'Signé le :datetime',
        'ip' => 'IP :ip',
        'hash' => 'SHA-256 : :hash',
        'none' => '— aucune signature —',
    ],
    // Material auf „Heute“, auch offline (MVP-1594).
    'day_material' => [
        'action' => 'Matériel',
        'title' => 'Saisir du matériel',
        'hint' => 'Le matériel est ajouté à votre feuille de temps ouverte du projet pour ce jour ; s’il n’y en a pas, elle est créée. Sans connexion, il est enregistré sur l’appareil et transmis au prochain contact.',
    ],
];
