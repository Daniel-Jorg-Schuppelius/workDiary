<?php
/*
 * Created on   : Tue Jun 16 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : StockCountStatus.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Enums\Inventory;

use App\Enums\Concerns\HasOptions;
use App\Enums\Contracts\HasLabel;

/**
 * Status einer stichtagsbezogenen Inventur (Feature 048, MVP-069).
 */
enum StockCountStatus: string implements HasLabel {
    use HasOptions;

    case Counting = 'counting';   // Sollbestand eingefroren, Zählung läuft
    case Review = 'review';       // Differenzen werden geprüft
    case Completed = 'completed'; // Differenzen freigegeben & gebucht
    case Cancelled = 'cancelled';

    public function label(): string {
        return __('inventory.count_status.' . $this->value);
    }

    public function isOpen(): bool {
        return $this === self::Counting || $this === self::Review;
    }
}
