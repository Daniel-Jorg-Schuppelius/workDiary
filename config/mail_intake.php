<?php
/*
 * Created on   : Mon Jul 06 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : mail_intake.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

/**
 * E-Mail-Eingang (Feature 056, MVP-117). Policy für die temporäre Übernahme
 * von Anhängen beim Intake (Rang 7): Anhänge werden nur persistiert, wenn sie
 * die Größen- und MIME-Whitelist bestehen — alles andere (v. a. ausführbare
 * Typen) wird als „gefährlich" verworfen und im Snapshot mit Grund vermerkt.
 */
return [
    'attachments' => [
        // Obergrenze je Anhang (Default 25 MB). Größere werden verworfen.
        'max_bytes' => (int) env('MAIL_INTAKE_MAX_ATTACHMENT_BYTES', 25 * 1024 * 1024),

        // MIME-Whitelist: nur belegtypische Formate werden persistiert bzw.
        // ins DMS übernommen. Ausführbare/Skript-Typen fehlen bewusst.
        'allowed_mimes' => [
            'application/pdf',
            'image/png',
            'image/jpeg',
            'image/gif',
            'image/webp',
            'image/tiff',
            'image/bmp',
            'text/plain',
            'text/csv',
            'text/xml',
            'application/xml',
            'application/msword',
            'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
            'application/vnd.ms-excel',
            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'application/vnd.ms-powerpoint',
            'application/vnd.openxmlformats-officedocument.presentationml.presentation',
            'application/vnd.oasis.opendocument.text',
            'application/vnd.oasis.opendocument.spreadsheet',
            'application/vnd.oasis.opendocument.presentation',
            'application/zip',
        ],
    ],
];
