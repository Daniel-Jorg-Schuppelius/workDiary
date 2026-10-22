<?php
/*
 * Created on   : Mon Oct 19 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ToolkitWordBoxSource.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Invoicing\Recognition;

use App\Services\Invoicing\Recognition\Contracts\WordBoxSource;
use CommonToolkit\Enums\HashAlgorithm;
use CommonToolkit\Helper\FileSystem\File;
use Illuminate\Support\Facades\{Cache, Log};
use PDFToolkit\Entities\WordBox;
use PDFToolkit\Helper\PDFTextProvider;
use Throwable;

/**
 * Wortboxen aus dem pdf-toolkit (0.27): Textlayer, bei Scans OCR. Zwischen-
 * gespeichert je Dateiinhalt einen Tag — Editor, Vorschau und Probelauf lesen
 * dieselben Belege wiederholt (Sicherheitsaudit 2026-10-21, pub-3). Ein leeres
 * Ergebnis bleibt ungespeichert, damit ein kurzer OCR-Ausfall nicht hängen bleibt.
 */
final class ToolkitWordBoxSource implements WordBoxSource {
    private const CACHE_PREFIX = 'word-boxes:1:';

    public function boxesFor(string $pdfPath): array {
        try {
            $key = self::CACHE_PREFIX . File::hash($pdfPath, HashAlgorithm::SHA256);
            $cached = Cache::get($key);
            if (is_array($cached)) {
                return array_values(array_map(static fn (array $row): WordBox => WordBox::fromArray($row), array_filter($cached, 'is_array')));
            }
            $boxes = (new PDFTextProvider($pdfPath))->wordBoxes();
        } catch (Throwable $e) {
            Log::info('Wortboxen nicht lesbar', ['path' => basename($pdfPath), 'error' => $e->getMessage()]);

            return [];
        }
        if ($boxes !== []) {
            Cache::put($key, array_map(static fn (WordBox $box): array => $box->toArray(), $boxes), now()->addDay());
        }

        return $boxes;
    }
}
