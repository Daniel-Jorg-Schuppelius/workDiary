<?php
/*
 * Created on   : Sun Aug 30 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : MediaResponder.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Media;

use App\Models\Attachment;
use App\Models\Media\MediaRendition;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\{BinaryFileResponse, Response, StreamedResponse};

/**
 * Mediendateien ausliefern (Feature 150).
 *
 * **Warum nicht einfach `Storage::response()`:** das liefert eine
 * `StreamedResponse`, und die kennt keine **Range-Anfragen**. Folge: in
 * einem Video lässt sich nicht springen — der Browser muss von vorn laden,
 * um in die Mitte zu kommen. Bei einem zwanzigminütigen Unterweisungsvideo
 * ist das der Unterschied zwischen benutzbar und nicht.
 *
 * `BinaryFileResponse` beantwortet `Range` mit `206 Partial Content` und
 * setzt `Accept-Ranges`. Das setzt allerdings eine Datei im lokalen
 * Dateisystem voraus — bei entfernten Ablagen (S3 & Co.) bleibt nur der
 * Strom, dann fällt die Auslieferung darauf zurück.
 */
class MediaResponder {
    /** Ableitung ausliefern — mit Sprungmöglichkeit, wo es geht. */
    public function rendition(MediaRendition $rendition): Response {
        return $this->deliver(
            (string) $rendition->disk,
            (string) $rendition->path,
            basename((string) $rendition->path),
            (string) $rendition->mime,
        );
    }

    /** Originaldatei eines Anhangs ausliefern. */
    public function attachment(Attachment $attachment): Response {
        return $this->deliver(
            (string) $attachment->disk,
            (string) $attachment->path,
            (string) $attachment->original_name,
            (string) $attachment->mime,
        );
    }

    private function deliver(string $disk, string $path, string $name, string $mime): Response {
        $storage = Storage::disk($disk);

        // Nur lokale Ablagen liefern einen echten Dateisystempfad. Bei
        // entfernten (S3 & Co.) zeigt `path()` ins Leere — dann bleibt der
        // Strom, also ohne Springen, aber wenigstens abspielbar. Genau
        // deshalb entscheidet `is_file()` und nicht der Ablagename.
        $absolute = $storage->path($path);

        if (is_file($absolute)) {
            $response = new BinaryFileResponse($absolute);
            $response->setAutoLastModified();
            $response->headers->set('Content-Type', $mime);
            $response->setContentDisposition('inline', $name, $this->asciiName($name));

            return $response;
        }

        /** @var StreamedResponse $streamed */
        $streamed = $storage->response($path, $name);

        return $streamed;
    }

    /**
     * Rückfallname ohne Sonderzeichen — Umlaute im Dateinamen brechen
     * sonst den Content-Disposition-Kopf in älteren Browsern.
     */
    private function asciiName(string $name): string {
        $ascii = preg_replace('/[^\x20-\x7E]/', '_', $name) ?? 'datei';

        return $ascii !== '' ? $ascii : 'datei';
    }
}
