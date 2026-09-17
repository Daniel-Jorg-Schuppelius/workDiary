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
        'checkin' => 'Check-in (QR/NFC)',
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
    'checkpoint_kind' => [
        'site' => 'Site',
        'vehicle' => 'Véhicule',
    ],
    'checkin' => [
        'title' => 'Check-in',
        'subtitle' => 'Arrivée et départ avec le code sur le site ou le véhicule.',
        'state' => [
            'in' => 'Vous êtes pointé depuis :time.',
            'out' => 'Vous n\'êtes pas pointé actuellement.',
        ],
        'action' => [
            'in' => 'Arrivée',
            'out' => 'Départ',
        ],
        'location_hint' => 'La position est vérifiée une fois lors du pointage (rayon de :radius m). Elle n\'est pas enregistrée.',
        'flash' => [
            'in' => 'Arrivée à « :name » enregistrée.',
            'out' => 'Départ à « :name » enregistré.',
        ],
        'error' => [
            'already_in' => 'Vous êtes déjà pointé.',
            'not_in' => 'Vous n\'êtes pas pointé.',
            'no_center' => 'Ce point a un rayon mais aucun emplacement. Veuillez contacter l\'administration.',
            'location_required' => 'Ce point nécessite votre position.',
            'too_far' => 'Vous êtes à :distance m ; :radius m sont autorisés.',
            'location_denied' => 'La position n\'a pas pu être déterminée. Veuillez autoriser la localisation.',
        ],
    ],
];
