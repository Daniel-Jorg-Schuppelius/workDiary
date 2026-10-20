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
 * dem SHA-256 des Belegs abgelegt. Gezeigt werden die ersten Seiten und seit
 * Phase 155 zusätzlich die letzte (E8) — Summen stehen vorn oder hinten.
 */
final class PageImages {
    public const DPI = 110;

    public const MAX_PAGES = 3;

    public function __construct(private readonly PageImageSource $renderer) {}

    /**
     * Seiten, die der Editor zeigt: 1 bis MAX_PAGES und die letzte Seite.
     *
     * @return list<int>
     */
    public static function shownPages(int $pageCount): array {
        $pages = range(1, max(1, min(self::MAX_PAGES, $pageCount)));
        if ($pageCount > self::MAX_PAGES) {
            $pages[] = $pageCount;
        }

        return $pages;
    }

    /** Absoluter Pfad des PNG der Seite; null, wenn das Original kein PDF ist oder das Rendern scheitert. */
    public function pathFor(IncomingEInvoice $incoming, int $page): ?string {
        if ($page < 1) {
            return null;
        }
        $pdf = TemplateRecognizer::pdfPathFor($incoming);
        if ($pdf === null) {
            return null;
        }
        $disk = Storage::disk('local');
        $directory = 'recognition/' . $incoming->sha256;
        $relative = $directory . '/page-' . $page . '.png';
        if (! $disk->exists($relative)) {
            $disk->makeDirectory($directory);
            $rendered = $this->renderer->render($pdf, $disk->path($directory), $page, $page, self::DPI);
            $file = $rendered[0] ?? null;
            $target = $disk->path($relative);
            if ($file !== null && $file !== $target && File::isFile($file)) {
                File::move($file, dirname($target), basename($target));
            }
        }

        return $disk->exists($relative) ? $disk->path($relative) : null;
    }

    /**
     * @param  list<int>  $candidates  Seiten laut {@see shownPages()}
     * @return list<int> Seiten, für die ein Bild vorliegt oder entsteht
     */
    public function pages(IncomingEInvoice $incoming, array $candidates): array {
        $pages = [];
        foreach ($candidates as $page) {
            if ($this->pathFor($incoming, $page) === null) {
                break;
            }
            $pages[] = $page;
        }

        return $pages;
    }
}
