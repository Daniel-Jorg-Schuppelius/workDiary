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
    'section' => 'Medien',
    'field' => [
        'state' => 'Verarbeitung',
        'duration' => 'Dauer',
        'resolution' => 'Auflösung',
        'subtitles' => 'Untertitel',
        'transcribe_locale' => 'Sprache der Aufnahme',
    ],
    'help' => [
        'processing' => 'Das Video wird gerade umgerechnet. Es erscheint, sobald es fertig ist.',
        'subtitle_upload' => 'WebVTT-Datei (.vtt). Untertitel sind beim Verkauf an Verbraucher Pflicht (WCAG 1.2.2) — eine maschinelle Fassung zählt erst nach Durchsicht.',
        'transcribe' => 'Die Spracherkennung läuft auf diesem Server; es werden keine Daten an Dritte übertragen. Das Ergebnis ist ein Entwurf und wird erst nach Durchsicht als Nachweis gezählt.',
    ],
    'action' => [
        'transcribe' => 'Automatisch erzeugen',
        'mark_reviewed' => 'Als durchgesehen markieren',
        'remove_subtitle' => 'Untertitelspur entfernen',
    ],
    'label' => [
        'awaits_review' => 'wartet auf Durchsicht',
        'reviewed_on' => 'durchgesehen am :date',
        'machine_short' => 'maschinell',
    ],
    'errors' => [
        'ffmpeg_missing' => 'Auf diesem Server ist keine Videoverarbeitung eingerichtet (ffmpeg fehlt).',
        'source_missing' => 'Die hochgeladene Datei wurde nicht gefunden.',
        'unreadable' => 'Die Datei ließ sich nicht als Video lesen.',
        'too_long' => 'Das Video ist länger als :minutes Minuten und wird nicht verarbeitet.',
        'target_unwritable' => 'Der Ablageordner ließ sich nicht anlegen.',
        'no_rendition' => 'Es ließ sich keine abspielbare Fassung erzeugen.',
        'not_webvtt' => 'Die Datei ist keine WebVTT-Untertiteldatei.',
        'whisper_missing' => 'Auf diesem Server ist keine Spracherkennung eingerichtet (Whisper fehlt).',
        'transcription_failed' => 'Die Spracherkennung lieferte keine verwertbare Untertitelspur.',
        'not_a_subtitle' => 'Diese Ableitung ist keine Untertitelspur.',
        'job_failed' => 'Die Verarbeitung wurde abgebrochen.',
    ],
    'flash' => [
        'subtitle_added' => 'Untertitelspur hinterlegt.',
        'transcription_queued' => 'Die Untertitel werden erzeugt. Das dauert je nach Länge einige Minuten; Sie werden benachrichtigt.',
        'subtitle_reviewed' => 'Untertitelspur als durchgesehen vermerkt.',
        'subtitle_removed' => 'Untertitelspur entfernt.',
    ],
];
