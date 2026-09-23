<?php
/*
 * Created on   : Sat Sep 20 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : DocumentTextExtractor.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Document;

use App\Enums\Document\DocumentTextFailure;
use App\Exceptions\DocumentTextUnavailableException;
use App\Models\Document\DocumentVersion;
use CommonToolkit\Helper\FileSystem\File as ToolkitFile;
use Illuminate\Support\Facades\Storage;
use PDFToolkit\Readers\TesseractReader;
use PDFToolkit\Registries\PDFReaderRegistry;
use Throwable;

/**
 * Text einer Dokumentversion über das php-pdf-toolkit: PDF inklusive OCR,
 * Bilder über Tesseract, Klartext direkt. Andere Formate liefern keinen Text.
 *
 * Lag bis MVP-819 privat im KI-Vorschlagsdienst; seit der Tätigkeitsindex
 * denselben Text braucht, steht die Extraktion an einer Stelle. Der Aufrufer
 * entscheidet über den Umgang mit dem Fehlschlag — der KI-Pfad meldet ihn der
 * Person, der Index hält ihn still fest und versucht es nicht erneut.
 */
class DocumentTextExtractor {
    /** @throws DocumentTextUnavailableException */
    public function extract(DocumentVersion $version): string {
        $disk = Storage::disk((string) $version->disk);
        if (! $disk->exists((string) $version->path)) {
            throw new DocumentTextUnavailableException(DocumentTextFailure::VersionMissing);
        }
        $path = $disk->path((string) $version->path);

        $extension = mb_strtolower(pathinfo((string) $version->original_name, PATHINFO_EXTENSION));
        $mime = mb_strtolower((string) $version->mime);

        try {
            $text = match (true) {
                $extension === 'pdf' || $mime === 'application/pdf' => PDFReaderRegistry::getInstance()
                    ->extractText($path, ['language' => 'deu+eng', 'qualityCheck' => true])
                    ->getTextOrDefault(),
                in_array($extension, ['jpg', 'jpeg', 'png', 'tif', 'tiff'], true) || str_starts_with($mime, 'image/') => (string) (new TesseractReader)
                    ->extractTextFromImage($path, ['language' => 'deu+eng', 'qualityCheck' => true]),
                in_array($extension, ['txt', 'md', 'csv'], true) || str_starts_with($mime, 'text/') => ToolkitFile::read($path),
                default => throw new DocumentTextUnavailableException(DocumentTextFailure::Unsupported),
            };
        } catch (DocumentTextUnavailableException $e) {
            throw $e;
        } catch (Throwable $e) {
            throw new DocumentTextUnavailableException(DocumentTextFailure::Failed, $e);
        }

        $text = trim($text);
        if ($text === '') {
            throw new DocumentTextUnavailableException(DocumentTextFailure::Empty);
        }

        return $text;
    }
}
