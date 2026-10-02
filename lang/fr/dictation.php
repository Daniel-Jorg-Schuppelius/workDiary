<?php
/*
 * Created on   : Fri Oct 02 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : dictation.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

// Sprachdiktat (MVP-1060).
return [
    'action' => [
        'start' => 'Dicter',
        'stop' => 'Arrêter l’enregistrement',
        'working' => 'Transcription …',
    ],
    'field' => [
        'activity' => 'Tâche',
        'work_done' => 'Réalisé',
        'defects' => 'Défauts',
        'note' => 'Note',
    ],
    'error' => [
        'unavailable' => 'La reconnaissance vocale n’est pas configurée sur ce serveur.',
        'denied' => 'Pas d’accès au microphone — veuillez l’autoriser dans le navigateur.',
        'unsupported' => 'Ce navigateur ne peut pas enregistrer.',
        'audio_missing' => 'L’enregistrement n’est pas arrivé.',
        'no_speech' => 'Aucune parole n’a été reconnue dans l’enregistrement.',
    ],
];
