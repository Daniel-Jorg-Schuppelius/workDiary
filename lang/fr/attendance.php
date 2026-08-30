<?php
/*
 * Created on   : Sun May 17 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : attendance.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

return [
    // Statuts intermédiaires (MVP-532) : télétravail/course de service.
    'intermediate' => [
        'homeoffice' => 'Télétravail',
        'errand' => 'Course de service',
        'start_homeoffice' => 'Commencer le télétravail',
        'end_homeoffice' => 'Terminer le télétravail',
        'start_errand' => 'Commencer la course',
        'end_errand' => 'Terminer la course',
    ],
    'status' => [
        'open' => 'Ouvert',
        'closed' => 'Fermé',
        'auto_closed' => 'Fermé automatiquement',
        'adjusted' => 'Ajusté',
        'cancelled' => 'Annulé',
    ],
    'source' => [
        'clock' => 'Pointage',
        'manual' => 'Manuel',
        'import' => 'Import',
        'auto_close' => 'Fermeture auto',
        'terminal' => 'Terminal',
        'phone' => 'Téléphone',
        'learning' => 'Temps d\'apprentissage',
    ],
    'correction' => [
        'action' => [
            'create' => 'Créer',
            'update' => 'Modifier',
            'delete' => 'Supprimer',
        ],
    ],
    'error' => [
        'target_day_locked' => 'Le jour visé est clôturé ou le mois validé — veuillez demander une correction de temps.',
        'duration_too_long' => 'Un pointage ne peut pas dépasser :hours heures.',
    ],
];
