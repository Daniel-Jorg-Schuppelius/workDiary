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
use CommonToolkit\Helper\FileSystem\{File as ToolkitFile, Folder};
use CommonToolkit\Helper\Office\OfficeHelper;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use PDFToolkit\Readers\TesseractReader;
use PDFToolkit\Registries\PDFReaderRegistry;
use Throwable;

/**
 * Text einer Dokumentversion über das php-pdf-toolkit: PDF inklusive OCR,
 * Bilder über Tesseract, Klartext direkt, Textverarbeitung (DOCX/DOC/ODT/RTF)
 * über LibreOffice (MVP-913). Andere Formate liefern keinen Text.
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

        return $this->extractFile($disk->path((string) $version->path), (string) $version->original_name, (string) $version->mime);
    }

    /**
     * Text einer lokalen Datei (z. B. Upload), Format nach Dateiname/MIME.
     *
     * @throws DocumentTextUnavailableException
     */
    public function extractFile(string $path, string $originalName, string $mime = ''): string {
        $extension = mb_strtolower(pathinfo($originalName, PATHINFO_EXTENSION));
        $mime = mb_strtolower($mime);

        try {
            $text = match (true) {
                $extension === 'pdf' || $mime === 'application/pdf' => PDFReaderRegistry::getInstance()
                    ->extractText($path, ['language' => 'deu+eng', 'qualityCheck' => true])
                    ->getTextOrDefault(),
                in_array($extension, ['jpg', 'jpeg', 'png', 'tif', 'tiff'], true) || str_starts_with($mime, 'image/') => (string) (new TesseractReader)
                    ->extractTextFromImage($path, ['language' => 'deu+eng', 'qualityCheck' => true]),
                in_array($extension, ['txt', 'md', 'csv'], true) || str_starts_with($mime, 'text/') => ToolkitFile::read($path),
                // MVP-913: Textverarbeitung über LibreOffice → Klartext.
                in_array($extension, ['docx', 'doc', 'odt', 'rtf'], true) => $this->officeText($path),
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

    private function officeText(string $path): string {
        if (! OfficeHelper::isAvailable()) {
            throw new DocumentTextUnavailableException(DocumentTextFailure::Unsupported);
        }
        $dir = storage_path('app/tmp/office-text-' . Str::random(12));
        Folder::create($dir, 0775, true);
        try {
            $text = OfficeHelper::convertToFile($path, 'txt', $dir, 60.0);

            return $text !== null ? ToolkitFile::readAsUtf8($text) : throw new DocumentTextUnavailableException(DocumentTextFailure::Failed);
        } finally {
            Folder::delete($dir, true);
        }
    }
}
