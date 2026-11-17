<?php
/*
 * Created on   : Wed Oct 07 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : UploadPurpose.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Enums\Attachments;

use App\Services\Attachments\FileAttacher;

/**
 * Zweck eines Uploads (MVP-1074): Formate und Mengengrenzen je Zweck. Die
 * Größe je Datei liefert {@see FileAttacher::maxKb()}, weil sie
 * organisationsweit einstellbar ist. Druckdaten erweitern die allgemeine
 * Positivliste nicht.
 */
enum UploadPurpose: string {
    case General = 'general';
    case PrintData = 'print_data';
    /**
     * Anhänge auf internen, angemeldeten Mitarbeiterflächen (Anhänge an
     * Trägern, Dokumente, Chat): wie General, zusätzlich Mail-Dateien
     * (Feature 204). Nie in Portalen, im Auskunftsportal oder im Kundeneingang.
     */
    case Internal = 'internal';

    /** @return list<string> */
    public function extensions(): array {
        return match ($this) {
            self::General => FileAttacher::ALLOWED_EXTENSIONS,
            // Mail-Dateien nur intern (Feature 204): Typ und Aufbau prüft FileAttacher::rejection().
            self::Internal => [...FileAttacher::ALLOWED_EXTENSIONS, ...FileAttacher::MAIL_EXTENSIONS],
            // SVG nur ablegen und herunterladen, nie im Browser rendern.
            self::PrintData => ['pdf', 'tif', 'tiff', 'eps', 'ai', 'jpg', 'jpeg', 'png', 'svg', 'zip'],
        };
    }

    /**
     * Am Inhalt erkannte MIME-Typen. Illustrator-Dateien sind PDF- oder
     * PostScript-basiert, EPS erkennt Fileinfo als PostScript.
     *
     * @return list<string>
     */
    public function mimes(): array {
        return match ($this) {
            // Mail-Typen stehen bewusst nicht in der flachen Liste: sie gelten nur zur passenden Endung.
            self::General, self::Internal => FileAttacher::ALLOWED_MIMES,
            self::PrintData => [
                'application/pdf',
                'application/postscript',
                'image/x-eps',
                'image/tiff',
                'image/jpeg',
                'image/png',
                'image/svg+xml',
                'application/zip',
                'application/x-zip-compressed',
            ],
        };
    }

    /** Höchstzahl Dateien je Einreichung. */
    public function maxFiles(): int {
        return match ($this) {
            self::General, self::Internal => 10,
            self::PrintData => 20,
        };
    }

    /** Gesamtgröße je Einreichung in KB. */
    public function maxTotalKb(): int {
        return match ($this) {
            self::General, self::Internal => 262144,
            self::PrintData => 1048576,
        };
    }
}
