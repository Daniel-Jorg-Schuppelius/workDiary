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
    'subtitle' => 'Actions saisies hors ligne sur cet appareil — en attente ou rejetées.',
    'notice' => 'Cette liste est stockée uniquement sur cet appareil. Les entrées en attente se synchronisent automatiquement dès que la connexion est rétablie ; les entrées rejetées peuvent être réappliquées ou supprimées.',
    'empty' => 'Aucune modification hors ligne sur cet appareil.',
    'section' => [
        'pending' => 'En attente',
        'rejected' => 'Rejetées',
    ],
    'type' => [
        'clock_in' => 'Pointage d’arrivée',
        'clock_out' => 'Pointage de départ',
        'comment' => 'Commentaire de commande',
        'form' => 'Formulaire',
    ],
    'action' => [
        'retry' => 'Réappliquer',
        'discard' => 'Supprimer',
    ],
];
