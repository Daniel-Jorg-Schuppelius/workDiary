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
    'subtitle' => 'Actions saisies hors ligne sur cet appareil — en attente, en conflit ou refusées.',
    'notice' => 'Cette liste n’existe que sur cet appareil. Les entrées en attente sont transmises automatiquement dès qu’une connexion est disponible ; les entrées refusées peuvent être renvoyées ou supprimées. Les conflits exigent une décision : quelqu’un d’autre a modifié le même enregistrement.',
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
    ],
    'action' => [
        'retry' => 'Réappliquer',
        'discard' => 'Supprimer',
        'take_server' => 'Conserver l’autre version',
        'force_local' => 'Envoyer ma version',
    ],
    'conflict_hint' => 'État du serveur : :server',
    'photos_queued' => 'Photos en file d’attente : :count',
];
