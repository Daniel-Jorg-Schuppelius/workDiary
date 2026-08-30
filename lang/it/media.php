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
    'section' => 'Media',
    'field' => [
        'state' => 'Elaborazione',
        'duration' => 'Durata',
        'resolution' => 'Risoluzione',
        'subtitles' => 'Sottotitoli',
        'transcribe_locale' => 'Lingua parlata',
    ],
    'help' => [
        'processing' => 'Il video è in conversione. Comparirà appena pronto.',
        'subtitle_upload' => 'File WebVTT (.vtt). I sottotitoli sono obbligatori nella vendita ai consumatori (WCAG 1.2.2): una traccia automatica vale solo dopo la revisione.',
        'transcribe' => 'Il riconoscimento vocale gira su questo server; nessun dato viene inviato a terzi. Il risultato è una bozza e vale come prova solo dopo la revisione.',
    ],
    'action' => [
        'transcribe' => 'Genera automaticamente',
        'mark_reviewed' => 'Segna come revisionata',
        'remove_subtitle' => 'Rimuovi traccia di sottotitoli',
    ],
    'label' => [
        'awaits_review' => 'in attesa di revisione',
        'reviewed_on' => 'revisionata il :date',
        'machine_short' => 'automatica',
    ],
    'errors' => [
        'ffmpeg_missing' => 'Su questo server non è configurata l’elaborazione video (manca ffmpeg).',
        'source_missing' => 'Il file caricato non è stato trovato.',
        'unreadable' => 'Il file non è leggibile come video.',
        'too_long' => 'Il video supera :minutes minuti e non verrà elaborato.',
        'target_unwritable' => 'Non è stato possibile creare la cartella di archiviazione.',
        'no_rendition' => 'Non è stato possibile produrre una versione riproducibile.',
        'not_webvtt' => 'Il file non è un file di sottotitoli WebVTT.',
        'whisper_missing' => 'Su questo server non è configurato il riconoscimento vocale (manca Whisper).',
        'transcription_failed' => 'Il riconoscimento vocale non ha prodotto una traccia di sottotitoli utilizzabile.',
        'not_a_subtitle' => 'Questa versione derivata non è una traccia di sottotitoli.',
        'job_failed' => 'L’elaborazione è stata interrotta.',
    ],
    'flash' => [
        'subtitle_added' => 'Traccia di sottotitoli aggiunta.',
        'transcription_queued' => 'I sottotitoli sono in elaborazione. A seconda della durata servono alcuni minuti; riceverai una notifica.',
        'subtitle_reviewed' => 'Traccia di sottotitoli segnata come revisionata.',
        'subtitle_removed' => 'Traccia di sottotitoli rimossa.',
    ],
];
