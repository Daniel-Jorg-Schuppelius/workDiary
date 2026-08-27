<?php
/*
 * Created on   : Thu Aug 27 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : WidgetWidth.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Enums\Dashboard;

use App\Enums\Concerns\HasOptions;
use App\Enums\Contracts\HasLabel;

/**
 * Breite einer Dashboard-Kachel im zweispaltigen Raster. `Full` belegt ab
 * `lg` beide Spalten; darunter ist ohnehin alles einspaltig.
 */
enum WidgetWidth: string implements HasLabel {
    use HasOptions;

    case Half = 'half';
    case Full = 'full';

    public function label(): string {
        return match ($this) {
            self::Half => (string) __('dashboard.width.half'),
            self::Full => (string) __('dashboard.width.full'),
        };
    }

    /** Grid-Klasse für das Kachel-Raster (lg:grid-cols-2). */
    public function columnClass(): string {
        return match ($this) {
            self::Half => '',
            self::Full => 'lg:col-span-2',
        };
    }

    public static function tryFromValue(?string $value): ?self {
        return $value === null ? null : self::tryFrom($value);
    }
}
