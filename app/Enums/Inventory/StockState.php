<?php
/*
 * Created on   : Tue Jun 16 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : StockState.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Enums\Inventory;

use App\Enums\Concerns\HasOptions;
use App\Enums\Contracts\HasLabel;

/**
 * Bestandszustand einer Lagerbewegung/-menge (Feature 048, MVP-067).
 *
 *   Verfügbar = Physisch − Reserviert − Gesperrt − Qualitätsprüfung
 *
 * Beschädigt, gesperrt und in QS bleiben physisch vorhanden, sind aber nicht
 * frei verwendbar; Ausschuss ist kein verwendbarer Bestand.
 */
enum StockState: string implements HasLabel {
    use HasOptions;

    case Physical = 'physical';
    case Reserved = 'reserved';
    case Blocked = 'blocked';
    case Quality = 'quality';
    case Damaged = 'damaged';
    case Scrap = 'scrap';

    public function label(): string {
        return match ($this) {
            self::Physical => __('inventory.state.physical'),
            self::Reserved => __('inventory.state.reserved'),
            self::Blocked => __('inventory.state.blocked'),
            self::Quality => __('inventory.state.quality'),
            self::Damaged => __('inventory.state.damaged'),
            self::Scrap => __('inventory.state.scrap'),
        };
    }

    /** Zustände, die die verfügbare Menge mindern (vom physischen Bestand abgezogen). */
    public function reducesAvailability(): bool {
        return match ($this) {
            self::Reserved, self::Blocked, self::Quality => true,
            self::Physical, self::Damaged, self::Scrap => false,
        };
    }
}
