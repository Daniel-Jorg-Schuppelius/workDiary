<?php
/*
 * Created on   : Thu Nov 12 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : GuideImageAnnotator.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Knowledge\Pages\Guide;

use App\Enums\Knowledge\KnowledgeGuideMarkerKind;
use CommonToolkit\Helper\FileSystem\File;

/**
 * Zeichnet die Markierungen eines Anleitungsschritts für das PDF ins Bild
 * (Feature 191, MVP-1668). Auf dem Bildschirm liegen sie als Überlagerung
 * über dem Bild und bleiben änderbar; im PDF trägt eine Überlagerung nicht
 * zuverlässig, deshalb eine eingebrannte Kopie — das gespeicherte Bild bleibt
 * unverändert.
 */
final class GuideImageAnnotator {
    private const string FONT = 'vendor/dompdf/dompdf/lib/fonts/DejaVuSans-Bold.ttf';

    /**
     * @param  list<array<string, mixed>>  $markers
     * @return string|null PNG oder null, wenn sich das Bild nicht lesen ließ
     */
    public function burn(string $bytes, array $markers): ?string {
        if (! function_exists('imagecreatefromstring')) {
            return null;
        }
        $image = @imagecreatefromstring($bytes);
        if ($image === false) {
            return null;
        }
        if ($markers !== []) {
            $this->draw($image, $markers);
        }
        ob_start();
        $ok = imagepng($image);
        $out = (string) ob_get_clean();

        return $ok && $out !== '' ? $out : null;
    }

    /** @param  list<array<string, mixed>>  $markers */
    private function draw(\GdImage $image, array $markers): void {
        $width = imagesx($image);
        $height = imagesy($image);
        $stroke = max(2, (int) round($width / 300));
        $red = (int) imagecolorallocate($image, 220, 38, 38);
        $white = (int) imagecolorallocate($image, 255, 255, 255);
        $ink = (int) imagecolorallocate($image, 17, 24, 39);
        $font = base_path(self::FONT);
        $hasFont = function_exists('imagettftext') && File::exists($font);
        $size = max(10.0, $width / 70);
        imagesetthickness($image, $stroke);

        foreach ($markers as $marker) {
            $kind = KnowledgeGuideMarkerKind::tryFrom((string) ($marker['kind'] ?? ''));
            $x = (int) round((float) ($marker['x'] ?? 0) * $width);
            $y = (int) round((float) ($marker['y'] ?? 0) * $height);
            $label = (string) ($marker['label'] ?? '');
            match ($kind) {
                KnowledgeGuideMarkerKind::Frame => imagerectangle($image, $x, $y, (int) round(((float) ($marker['x'] ?? 0) + (float) ($marker['w'] ?? 0)) * $width), (int) round(((float) ($marker['y'] ?? 0) + (float) ($marker['h'] ?? 0)) * $height), $red),
                KnowledgeGuideMarkerKind::Arrow => $this->arrow($image, $x, $y, (int) round((float) ($marker['x2'] ?? 0) * $width), (int) round((float) ($marker['y2'] ?? 0) * $height), $stroke, $red),
                KnowledgeGuideMarkerKind::Number => $this->badge($image, $x, $y, $label, $size, $red, $white, $hasFont ? $font : null),
                KnowledgeGuideMarkerKind::Text => $this->label($image, $x, $y, $label, $size, $red, $ink, $white, $hasFont ? $font : null),
                null => null,
            };
        }
    }

    private function arrow(\GdImage $image, int $x1, int $y1, int $x2, int $y2, int $stroke, int $color): void {
        imageline($image, $x1, $y1, $x2, $y2, $color);
        $angle = atan2($y2 - $y1, $x2 - $x1);
        $head = 6 * $stroke + 8;
        imagefilledpolygon($image, [
            $x2, $y2,
            (int) round($x2 - $head * cos($angle - M_PI / 7)), (int) round($y2 - $head * sin($angle - M_PI / 7)),
            (int) round($x2 - $head * cos($angle + M_PI / 7)), (int) round($y2 - $head * sin($angle + M_PI / 7)),
        ], $color);
    }

    private function badge(\GdImage $image, int $x, int $y, string $label, float $size, int $fill, int $text, ?string $font): void {
        $radius = (int) round($size * 1.1);
        imagefilledellipse($image, $x, $y, 2 * $radius, 2 * $radius, $fill);
        $this->text($image, $x, $y, $label, $size, $text, $font, true);
    }

    private function label(\GdImage $image, int $x, int $y, string $label, float $size, int $border, int $text, int $fill, ?string $font): void {
        if ($label === '') {
            return;
        }
        $padding = (int) round($size / 2);
        [$w, $h] = $this->measure($label, $size, $font);
        imagefilledrectangle($image, $x, $y, $x + $w + 2 * $padding, $y + $h + 2 * $padding, $fill);
        imagerectangle($image, $x, $y, $x + $w + 2 * $padding, $y + $h + 2 * $padding, $border);
        $this->text($image, $x + $padding, $y + $padding, $label, $size, $text, $font, false);
    }

    /** Text oben links bzw. zentriert an ($x, $y). */
    private function text(\GdImage $image, int $x, int $y, string $label, float $size, int $color, ?string $font, bool $centered): void {
        [$w, $h] = $this->measure($label, $size, $font);
        $left = $centered ? $x - intdiv($w, 2) : $x;
        $top = $centered ? $y - intdiv($h, 2) : $y;
        if ($font !== null) {
            imagettftext($image, $size, 0, $left, $top + $h, $color, $font, $label);

            return;
        }
        imagestring($image, 5, $left, $top, $label, $color);
    }

    /** @return array{0: int, 1: int} Breite und Höhe des Textes in Pixeln */
    private function measure(string $label, float $size, ?string $font): array {
        if ($font !== null) {
            $box = imagettfbbox($size, 0, $font, $label);
            if ($box !== false) {
                return [abs($box[2] - $box[0]), abs($box[7] - $box[1])];
            }
        }

        return [imagefontwidth(5) * mb_strlen($label), imagefontheight(5)];
    }
}
