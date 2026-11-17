<?php
/*
 * Created on   : Thu Nov 12 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : QrCodeSource.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Invoicing\Recognition\Contracts;

use App\Services\Invoicing\Recognition\ToolkitQrCodeSource;
use Illuminate\Container\Attributes\Bind;

/**
 * Strichcodes und QR-Codes eines Belegs (Feature 208, MVP-1663): im Betrieb
 * `zbarimg` über das pdf-toolkit, in Tests eine Fälschung — wie Wortboxen und
 * Seitenbilder ({@see WordBoxSource}, {@see PageImageSource}).
 */
#[Bind(ToolkitQrCodeSource::class)]
interface QrCodeSource {
    /** Werkzeuge vorhanden (zbarimg, für PDF zusätzlich das Seitenraster). */
    public function isAvailable(): bool;

    /**
     * Codes je Seite einer PDF; Seiten ohne Code fehlen.
     *
     * @param  list<int>  $pages  Seiten (1-basiert), zusammenhängend gerastert von der kleinsten bis zur größten
     * @return array<int, list<array{type: string, data: string}>>
     */
    public function scanPdf(string $pdfPath, array $pages, int $dpi): array;

    /** @return list<array{type: string, data: string}> */
    public function scanImage(string $imagePath): array;
}
