<?php
/*
 * Created on   : Tue Jun 16 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ArticleType.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Enums\Article;

use App\Enums\Concerns\HasOptions;
use App\Enums\Contracts\HasLabel;

/**
 * Artikelarten des kanonischen Artikelstamms (Feature 048, MVP-060).
 * Steuert fachliches Standardverhalten (z. B. herstellbar/lagerfähig), das
 * über die Flags am Artikel aber explizit übersteuerbar bleibt.
 */
enum ArticleType: string implements HasLabel {
    use HasOptions;

    case Raw = 'raw';                 // Rohstoff
    case Consumable = 'consumable';   // Verbrauchsmaterial
    case Merchandise = 'merchandise'; // Handelsware
    case SemiFinished = 'semifinished'; // Halbfabrikat
    case Finished = 'finished';       // Fertigerzeugnis
    case Service = 'service';         // Leistung

    public function label(): string {
        return match ($this) {
            self::Raw => __('article.type.raw'),
            self::Consumable => __('article.type.consumable'),
            self::Merchandise => __('article.type.merchandise'),
            self::SemiFinished => __('article.type.semifinished'),
            self::Finished => __('article.type.finished'),
            self::Service => __('article.type.service'),
        };
    }

    /** Leistungen sind grundsätzlich nicht lagerfähig. */
    public function defaultStockable(): bool {
        return $this !== self::Service;
    }
}
