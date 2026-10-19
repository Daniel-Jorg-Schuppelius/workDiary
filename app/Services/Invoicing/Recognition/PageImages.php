<?php
/*
 * Created on   : Mon Oct 19 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : PageImages.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Invoicing\Recognition;

use App\Models\Invoicing\IncomingEInvoice;
use App\Services\Invoicing\Recognition\Contracts\PageImageSource;
use CommonToolkit\Helper\FileSystem\File;
use Illuminate\Support\Facades\Storage;

/**
 * Seitenbilder für den Zonen-Editor (Phase 151, E5): nur auf Anfrage, unter
 * dem SHA-256 des Belegs abgelegt, höchstens die ersten Seiten.
 */
final class PageImages {
    public const DPI = 110;

    public const MAX_PAGES = 3;

    public function __construct(private readonly PageImageSource $renderer) {}

    /** Absoluter Pfad des PNG der Seite; null, wenn das Original kein PDF ist oder das Rendern scheitert. */
    public function pathFor(IncomingEInvoice $incoming, int $page): ?string {
        if ($page < 1 || $page > self::MAX_PAGES) {
            return null;
        }
        $pdf = TemplateRecognizer::pdfPathFor($incoming);
        if ($pdf === null) {
            return null;
        }
        $disk = Storage::disk('local');
        $relative = 'recognition/' . $incoming->sha256 . '/page-' . $page . '.png';
        if (! $disk->exists($relative)) {
            $disk->makeDirectory('recognition/' . $incoming->sha256);
            $rendered = $this->renderer->render($pdf, $disk->path('recognition/' . $incoming->sha256), 1, self::MAX_PAGES, self::DPI);
            foreach ($rendered as $index => $file) {
                $target = $disk->path('recognition/' . $incoming->sha256 . '/page-' . ($index + 1) . '.png');
                if ($file !== $target && File::isFile($file)) {
                    File::move($file, dirname($target), basename($target));
                }
            }
        }

        return $disk->exists($relative) ? $disk->path($relative) : null;
    }

    /** @return list<int> Seiten, für die ein Bild vorliegt oder entsteht */
    public function pages(IncomingEInvoice $incoming): array {
        $pages = [];
        for ($page = 1; $page <= self::MAX_PAGES; $page++) {
            if ($this->pathFor($incoming, $page) === null) {
                break;
            }
            $pages[] = $page;
        }

        return $pages;
    }
}
