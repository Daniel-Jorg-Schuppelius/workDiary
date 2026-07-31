<?php
/*
 * Created on   : Fri Jul 31 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : PrintChartPalette.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Support;

/**
 * Feste Hex-Farben für die Print-Diagramme der Report-PDFs
 * (resources/views/reports/pdf/charts/). dompdf löst weder CSS-Variablen
 * noch color-mix() auf — die Abstufungen werden deshalb hier vorberechnet.
 * Bildschirm-Charts bleiben bei Theme-Klassen (fill-primary …); diese
 * Palette gilt NUR für den Druck.
 */
final class PrintChartPalette {
    /** Druck-Serienfarben (unterscheidbar auch in Graustufen-Kopien). */
    private const SERIES = ['#1d4ed8', '#7e22ce', '#0f766e', '#b45309', '#be185d'];

    /** Basiston der Heat-Abstufung (Druck-Pendant zur Primary-Tönung). */
    private const HEAT_BASE = [29, 78, 216]; // #1d4ed8

    public static function series(int $index): string {
        return self::SERIES[$index % count(self::SERIES)];
    }

    /**
     * Vorberechnete Heat-Zellfarbe: 8 % … 60 % Tönung auf Weiß
     * (Skala wie x-charts.heatmap am Bildschirm).
     */
    public static function heat(float $value, float $max): string {
        if ($value <= 0 || $max <= 0) {
            return '#ffffff';
        }

        $alpha = (8 + min(1.0, $value / $max) * 52) / 100;
        [$r, $g, $b] = self::HEAT_BASE;

        return sprintf(
            '#%02x%02x%02x',
            (int) round(255 + ($r - 255) * $alpha),
            (int) round(255 + ($g - 255) * $alpha),
            (int) round(255 + ($b - 255) * $alpha),
        );
    }

    public static function axis(): string {
        return '#d1d5db';
    }

    public static function barTrack(): string {
        return '#f3f4f6';
    }
}
