<?php
/*
 * Created on   : Sat Aug 01 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : BasicPreflightProvider.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Print\Preflight;

use App\Models\DocumentVersion;
use Illuminate\Support\Facades\Storage;

/**
 * Eingebauter Struktur-Preflight (MVP-459): prüft nur, was ohne externes
 * Werkzeug belastbar prüfbar ist — Datei vorhanden, nicht leer, erwarteter
 * Typ, PDF-Header intakt. Inhaltliche Prüfungen (Beschnitt, Auflösung,
 * Farbprofile) liefern externe Provider über denselben Vertrag; eine
 * begründete manuelle Freigabe bleibt zusätzlich möglich.
 */
class BasicPreflightProvider implements PreflightProvider {
    /** Für den Druck annehmbare Dateitypen. */
    private const SUPPORTED_MIMES = [
        'application/pdf',
        'image/jpeg',
        'image/png',
        'image/tiff',
    ];

    public function name(): string {
        return 'basic';
    }

    public function supports(DocumentVersion $version): bool {
        return true; // Struktur-Checks gelten für jede Produktionsdatei.
    }

    public function check(DocumentVersion $version): PreflightReport {
        $errors = [];
        $warnings = [];

        $disk = Storage::disk($version->disk);
        if (! $disk->exists($version->path)) {
            return new PreflightReport($this->name(), [(string) __('print.preflight.file_missing')]);
        }

        if ((int) $disk->size($version->path) === 0) {
            $errors[] = (string) __('print.preflight.file_empty');
        }

        $mime = (string) $version->mime;
        if ($mime !== '' && ! in_array($mime, self::SUPPORTED_MIMES, true)) {
            $warnings[] = (string) __('print.preflight.mime_unexpected', ['mime' => $mime]);
        }

        if ($mime === 'application/pdf') {
            $stream = $disk->readStream($version->path);
            $head = $stream !== null ? (string) fread($stream, 8) : '';
            if (is_resource($stream)) {
                fclose($stream);
            }
            if (! str_starts_with($head, '%PDF-')) {
                $errors[] = (string) __('print.preflight.pdf_header_invalid');
            }
        }

        return new PreflightReport($this->name(), $errors, $warnings);
    }
}
