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
        'state' => 'Processing',
        'duration' => 'Duration',
        'resolution' => 'Resolution',
        'subtitles' => 'Subtitles',
        'transcribe_locale' => 'Spoken language',
    ],
    'help' => [
        'processing' => 'The video is being converted. It will appear once it is ready.',
        'subtitle_upload' => 'WebVTT file (.vtt). Subtitles are mandatory when selling to consumers (WCAG 1.2.2) — a machine-generated track only counts after review.',
        'transcribe' => 'Speech recognition runs on this server; no data is sent to third parties. The result is a draft and only counts as evidence after review.',
    ],
    'action' => [
        'transcribe' => 'Generate automatically',
        'mark_reviewed' => 'Mark as reviewed',
        'remove_subtitle' => 'Remove subtitle track',
    ],
    'label' => [
        'awaits_review' => 'awaiting review',
        'reviewed_on' => 'reviewed on :date',
        'machine_short' => 'machine-generated',
    ],
    'errors' => [
        'ffmpeg_missing' => 'No video processing is set up on this server (ffmpeg is missing).',
        'source_missing' => 'The uploaded file was not found.',
        'unreadable' => 'The file could not be read as a video.',
        'too_long' => 'The video is longer than :minutes minutes and will not be processed.',
        'target_unwritable' => 'The storage folder could not be created.',
        'no_rendition' => 'No playable version could be produced.',
        'not_webvtt' => 'The file is not a WebVTT subtitle file.',
        'whisper_missing' => 'No speech recognition is set up on this server (Whisper is missing).',
        'transcription_failed' => 'Speech recognition did not return a usable subtitle track.',
        'not_a_subtitle' => 'This rendition is not a subtitle track.',
        'job_failed' => 'Processing was aborted.',
    ],
    'flash' => [
        'subtitle_added' => 'Subtitle track added.',
        'transcription_queued' => 'Subtitles are being generated. Depending on length this takes a few minutes; you will be notified.',
        'subtitle_reviewed' => 'Subtitle track marked as reviewed.',
        'subtitle_removed' => 'Subtitle track removed.',
    ],
];
