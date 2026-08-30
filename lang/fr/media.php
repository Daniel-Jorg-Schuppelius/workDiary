<?php
/*
 * Created on   : Sun Aug 30 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : media.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

/*
 * Video-Transcoding (Feature 150).
 */

return [
    'section' => 'Médias',
    'field' => [
        'state' => 'Traitement',
        'duration' => 'Durée',
        'resolution' => 'Résolution',
        'subtitles' => 'Sous-titres',
        'transcribe_locale' => 'Langue parlée',
    ],
    'help' => [
        'processing' => 'La vidéo est en cours de conversion. Elle apparaîtra une fois prête.',
        'subtitle_upload' => 'Fichier WebVTT (.vtt). Les sous-titres sont obligatoires en cas de vente aux consommateurs (WCAG 1.2.2) — une piste automatique ne compte qu’après relecture.',
        'transcribe' => 'La reconnaissance vocale s’exécute sur ce serveur ; aucune donnée n’est transmise à des tiers. Le résultat est un brouillon et ne compte comme preuve qu’après relecture.',
    ],
    'action' => [
        'transcribe' => 'Générer automatiquement',
        'mark_reviewed' => 'Marquer comme relue',
        'remove_subtitle' => 'Supprimer la piste de sous-titres',
    ],
    'label' => [
        'awaits_review' => 'en attente de relecture',
        'reviewed_on' => 'relue le :date',
        'machine_short' => 'automatique',
    ],
    'errors' => [
        'ffmpeg_missing' => 'Aucun traitement vidéo n’est installé sur ce serveur (ffmpeg manquant).',
        'source_missing' => 'Le fichier téléversé est introuvable.',
        'unreadable' => 'Le fichier n’a pas pu être lu comme vidéo.',
        'too_long' => 'La vidéo dépasse :minutes minutes et ne sera pas traitée.',
        'target_unwritable' => 'Le dossier de stockage n’a pas pu être créé.',
        'no_rendition' => 'Aucune version lisible n’a pu être produite.',
        'not_webvtt' => 'Le fichier n’est pas un fichier de sous-titres WebVTT.',
        'whisper_missing' => 'Aucune reconnaissance vocale n’est installée sur ce serveur (Whisper manquant).',
        'transcription_failed' => 'La reconnaissance vocale n’a pas fourni de piste de sous-titres exploitable.',
        'not_a_subtitle' => 'Cette version dérivée n’est pas une piste de sous-titres.',
        'job_failed' => 'Le traitement a été interrompu.',
    ],
    'flash' => [
        'subtitle_added' => 'Piste de sous-titres ajoutée.',
        'transcription_queued' => 'Les sous-titres sont en cours de génération. Selon la durée, cela prend quelques minutes ; vous serez averti.',
        'subtitle_reviewed' => 'Piste de sous-titres marquée comme relue.',
        'subtitle_removed' => 'Piste de sous-titres supprimée.',
    ],
];
