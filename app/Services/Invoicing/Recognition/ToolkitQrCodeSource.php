<?php
/*
 * Created on   : Thu Nov 12 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ToolkitQrCodeSource.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Invoicing\Recognition;

use App\Services\Invoicing\Recognition\Contracts\QrCodeSource;
use PDFToolkit\Config\Config;
use PDFToolkit\Helper\BarcodeHelper;

/**
 * QR-Codes über `zbarimg` (pdf-toolkit, {@see BarcodeHelper}); der Befehl
 * entsteht im CommandBuilder des config-toolkit, Pfade gehen roh hinein.
 * Ohne `zbarimg` bzw. ohne Seitenraster (`pdftoppm`) meldet die Quelle
 * „nicht verfügbar“, der Rechnungseingang zeigt dann „GiroCode nicht lesbar“.
 */
final class ToolkitQrCodeSource implements QrCodeSource {
    public function isAvailable(): bool {
        return BarcodeHelper::isAvailable();
    }

    public function scanPdf(string $pdfPath, array $pages, int $dpi): array {
        if ($pages === [] || ! Config::getInstance()->isExecutableAvailable('pdftoppm-range-png')) {
            return [];
        }

        return BarcodeHelper::scanPages($pdfPath, $pages, $dpi);
    }

    public function scanImage(string $imagePath): array {
        return BarcodeHelper::scanImage($imagePath);
    }
}
