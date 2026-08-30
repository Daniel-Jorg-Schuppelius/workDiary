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
    'section' => 'Medios',
    'field' => [
        'state' => 'Procesamiento',
        'duration' => 'Duración',
        'resolution' => 'Resolución',
        'subtitles' => 'Subtítulos',
        'transcribe_locale' => 'Idioma hablado',
    ],
    'help' => [
        'processing' => 'El vídeo se está convirtiendo. Aparecerá en cuanto esté listo.',
        'subtitle_upload' => 'Archivo WebVTT (.vtt). Los subtítulos son obligatorios al vender a consumidores (WCAG 1.2.2): una pista automática solo cuenta tras su revisión.',
        'transcribe' => 'El reconocimiento de voz se ejecuta en este servidor; no se envían datos a terceros. El resultado es un borrador y solo cuenta como prueba tras su revisión.',
    ],
    'action' => [
        'transcribe' => 'Generar automáticamente',
        'mark_reviewed' => 'Marcar como revisada',
        'remove_subtitle' => 'Eliminar pista de subtítulos',
    ],
    'label' => [
        'awaits_review' => 'pendiente de revisión',
        'reviewed_on' => 'revisada el :date',
        'machine_short' => 'automática',
    ],
    'errors' => [
        'ffmpeg_missing' => 'En este servidor no hay procesamiento de vídeo configurado (falta ffmpeg).',
        'source_missing' => 'No se encontró el archivo subido.',
        'unreadable' => 'El archivo no se pudo leer como vídeo.',
        'too_long' => 'El vídeo dura más de :minutes minutos y no se procesará.',
        'target_unwritable' => 'No se pudo crear la carpeta de almacenamiento.',
        'no_rendition' => 'No se pudo generar ninguna versión reproducible.',
        'not_webvtt' => 'El archivo no es un archivo de subtítulos WebVTT.',
        'whisper_missing' => 'En este servidor no hay reconocimiento de voz configurado (falta Whisper).',
        'transcription_failed' => 'El reconocimiento de voz no devolvió una pista de subtítulos utilizable.',
        'not_a_subtitle' => 'Esta versión derivada no es una pista de subtítulos.',
        'job_failed' => 'El procesamiento se interrumpió.',
    ],
    'flash' => [
        'subtitle_added' => 'Pista de subtítulos añadida.',
        'transcription_queued' => 'Se están generando los subtítulos. Según la duración tarda unos minutos; recibirá un aviso.',
        'subtitle_reviewed' => 'Pista de subtítulos marcada como revisada.',
        'subtitle_removed' => 'Pista de subtítulos eliminada.',
    ],
];
