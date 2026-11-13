<?php
/*
 * Created on   : Thu Nov 12 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : GuideImageRedactor.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Knowledge\Pages\Guide;

use CommonToolkit\Helper\FileSystem\File;

/**
 * Schwärzt Bereiche eines Bildschirmfotos (Feature 191, MVP-1668): die
 * Bildpunkte unter den Rechtecken werden schwarz und das Bild neu kodiert —
 * damit verschwinden auch Metadaten. Das Ergebnis ersetzt das Original; eine
 * Überdeckung im Browser wäre keine Schwärzung.
 *
 * Bildschirmfotos sind Rasterbilder; der PDF-Weg des Toolkits
 * ({@see \PDFToolkit\Helper\PDFRedactHelper}) passt nicht, ein Bild-Schwärzer
 * fehlt im php-common-toolkit (Kandidat). Koordinaten sind relativ (0–1) zum
 * aufgerichteten Bild, wie der Browser es zeigt — die EXIF-Drehung wird
 * deshalb vor dem Schwärzen angewandt.
 */
final class GuideImageRedactor {
    public const array SUPPORTED = ['image/jpeg', 'image/png', 'image/webp'];

    public function supports(?string $mime): bool {
        return in_array(mb_strtolower(trim((string) $mime)), self::SUPPORTED, true);
    }

    /**
     * @param  list<array{x: float, y: float, w: float, h: float}>  $rects
     * @return string|null neu kodiertes Bild, null wenn es sich nicht lesen ließ
     */
    public function redact(string $bytes, string $mime, array $rects): ?string {
        $mime = mb_strtolower(trim($mime));
        if (! $this->supports($mime) || ! function_exists('imagecreatefromstring')) {
            return null;
        }
        $image = @imagecreatefromstring($bytes);
        if ($image === false) {
            return null;
        }
        if ($mime === 'image/jpeg') {
            $image = $this->orient($image, $bytes);
        }

        $width = imagesx($image);
        $height = imagesy($image);
        $black = (int) imagecolorallocate($image, 0, 0, 0);
        foreach ($rects as $rect) {
            $x1 = (int) floor(max(0.0, min(1.0, $rect['x'])) * $width);
            $y1 = (int) floor(max(0.0, min(1.0, $rect['y'])) * $height);
            $x2 = (int) ceil(max(0.0, min(1.0, $rect['x'] + $rect['w'])) * $width);
            $y2 = (int) ceil(max(0.0, min(1.0, $rect['y'] + $rect['h'])) * $height);
            if ($x2 > $x1 && $y2 > $y1) {
                imagefilledrectangle($image, $x1, $y1, $x2 - 1, $y2 - 1, $black);
            }
        }

        ob_start();
        $ok = match ($mime) {
            'image/jpeg' => imagejpeg($image, null, 92),
            'image/png' => imagepng($image),
            'image/webp' => function_exists('imagewebp') && imagewebp($image),
            default => false,
        };
        $out = (string) ob_get_clean();

        return $ok && $out !== '' ? $out : null;
    }

    /** EXIF-Drehung anwenden, damit die Koordinaten zum gezeigten Bild passen. */
    private function orient(\GdImage $image, string $bytes): \GdImage {
        if (! function_exists('exif_read_data')) {
            return $image;
        }
        $exif = File::withTemp($bytes, static fn (string $path): array|false => @exif_read_data($path), 'guide-exif-', 'jpg');
        $orientation = is_array($exif) ? (int) ($exif['Orientation'] ?? 1) : 1;
        if (in_array($orientation, [2, 4, 5, 7], true)) {
            imageflip($image, IMG_FLIP_HORIZONTAL);
        }
        $angle = match ($orientation) {
            3, 4 => 180,
            5, 6 => -90,
            7, 8 => 90,
            default => 0,
        };
        if ($angle === 0) {
            return $image;
        }
        $rotated = imagerotate($image, $angle, 0);

        return $rotated instanceof \GdImage ? $rotated : $image;
    }
}
