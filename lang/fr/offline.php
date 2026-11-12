<?php
/*
 * Created on   : Tue Jul 14 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : offline.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

// Offline-Sync (Feature 035): Offline-Änderungs-Seite + Statusanzeige.
return [
    'title' => 'Modifications hors ligne',
    'subtitle' => 'Actions saisies hors ligne — en attente, en conflit ou refusées.',
    'notice' => 'Les entrées en attente et refusées n’existent que sur cet appareil ; celles en attente sont transmises automatiquement dès qu’une connexion est disponible. Les conflits exigent votre décision : les données ont changé depuis la saisie (par exemple le pointage, ou la journée a été clôturée entre-temps) ou une information obligatoire manque. Les conflits ouverts apparaissent aussi ici lorsqu’ils sont nés sur un autre appareil.',
    'empty' => 'Aucune modification hors ligne sur cet appareil.',
    'section' => [
        'pending' => 'En attente',
        'rejected' => 'Rejetées',
        'conflict' => 'Conflits',
    ],
    'type' => [
        'clock_in' => 'Pointage d’arrivée',
        'clock_out' => 'Pointage de départ',
        'comment' => 'Commentaire de commande',
        'form' => 'Formulaire',
        'attendance_correct' => 'Correction de pointage',
        'time_entry' => 'Temps de projet',
        'material_usage' => 'Matériel',
        'learning_unit' => 'Unité d’apprentissage terminée',
        'inventory_count' => 'Scan d’inventaire',
        'tour_arrive' => 'Arrêt de tournée : arrivée',
        'tour_depart' => 'Arrêt de tournée : départ',
        'patrol_scan' => 'Point de contrôle',
        'takeoff_line' => 'Ligne de métré',
    ],
    'action' => [
        'retry' => 'Réappliquer',
        'discard' => 'Supprimer',
        'take_server' => 'Conserver l’autre version',
        'force_local' => 'Envoyer ma version',
        'edit' => 'Modifier',
        'send_edited' => 'Envoyer la version modifiée',
        'cancel_edit' => 'Annuler',
    ],
    'conflict_hint' => 'État du serveur : :server',
    'photos_queued' => 'Photos en file d’attente : :count',
    // Konflikte entscheiden (MVP-1595).
    'field' => [
        'missing' => 'Manquant',
        'lock' => 'Verrou',
    ],
    'remote_hint' => 'Saisi sur un autre appareil — le contenu s’y trouve. Ici, vous pouvez seulement abandonner le conflit.',
    'resolve_failed' => 'La décision n’a pas pu être transmise. Veuillez réessayer avec une connexion.',
    'unresolved' => 'ouvert',
    'flash' => [
        'resolved' => 'Conflit résolu.',
        'not_found' => 'Ce conflit n’existe plus.',
    ],
];
