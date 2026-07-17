<?php
/*
 * Created on   : Fri Jul 11 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : TableStylePreset.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Enums\DocumentDesign;

use App\Enums\Concerns\HasOptions;
use App\Enums\Contracts\HasLabel;

/**
 * Kuratierte Tabellenstil-Presets (MVP-299). Kein freies CSS: anpassbar sind
 * nur die hier definierten Schlüssel innerhalb der Grenzen aus bounds().
 * Fachliche Formatierung (Währung, Menge, Datum, Steuer) bleibt bei den
 * Formatierern der Module und wird vom Preset nicht überschrieben.
 */
enum TableStylePreset: string implements HasLabel {
    use HasOptions;

    case Clear = 'clear';
    case Compact = 'compact';
    case LowLine = 'low_line';

    public function label(): string {
        return match ($this) {
            self::Clear => __('Klar'),
            self::Compact => __('Kompakt'),
            self::LowLine => __('Linienarm'),
        };
    }

    /** @return array<string, mixed> Vollständige Stilwerte des Presets. */
    public function settings(): array {
        $base = [
            'font_family' => 'DejaVu Sans',
            'font_size' => 11,
            'line_height' => 1.35,
            'cell_padding_v' => 4,
            'cell_padding_h' => 6,
            'text_color' => '#222222',
            'accent_color' => '#333333',
            'header_fill' => '#f3f3f3',
            'header_text_color' => '#222222',
            'zebra' => false,
            'zebra_fill' => '#fafafa',
            'grid' => 'horizontal',
            'repeat_header' => true,
            'highlight_totals' => true,
        ];

        return match ($this) {
            self::Clear => $base,
            self::Compact => array_merge($base, [
                'font_size' => 9,
                'line_height' => 1.2,
                'cell_padding_v' => 2,
                'cell_padding_h' => 4,
                'zebra' => true,
            ]),
            self::LowLine => array_merge($base, [
                'grid' => 'minimal',
                'header_fill' => '#ffffff',
                'zebra' => false,
            ]),
        };
    }

    /**
     * Erlaubte Overrides mit sicheren Grenzen. Farben werden zusätzlich per
     * Kontrastprüfung im Preflight abgesichert.
     *
     * @return array<string, array{type: string, min?: float, max?: float, options?: array<int, string>}>
     */
    public static function bounds(): array {
        return [
            'font_family' => ['type' => 'option', 'options' => ['DejaVu Sans', 'DejaVu Serif', 'Helvetica', 'Courier']],
            'font_size' => ['type' => 'number', 'min' => 8, 'max' => 14],
            'line_height' => ['type' => 'number', 'min' => 1.0, 'max' => 1.8],
            'cell_padding_v' => ['type' => 'number', 'min' => 1, 'max' => 10],
            'cell_padding_h' => ['type' => 'number', 'min' => 2, 'max' => 12],
            'text_color' => ['type' => 'color'],
            'accent_color' => ['type' => 'color'],
            'header_fill' => ['type' => 'color'],
            'header_text_color' => ['type' => 'color'],
            'zebra' => ['type' => 'bool'],
            'zebra_fill' => ['type' => 'color'],
            'grid' => ['type' => 'option', 'options' => ['horizontal', 'full', 'minimal']],
            'repeat_header' => ['type' => 'bool'],
            'highlight_totals' => ['type' => 'bool'],
        ];
    }
}
