<?php
/*
 * Created on   : Sat Oct 24 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ShopOrderStatus.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Enums\Integration;

use App\Enums\Concerns\HasOptions;
use App\Enums\Contracts\HasLabel;

/**
 * Stand einer Shop-Bestellung (WooCommerce, Shopware, PrestaShop; Feature 175),
 * abgeleitet aus einem stabilen Feld des Shops. Den Lebenszyklus führt der
 * Shop — WorkDiary spiegelt ihn nur, deshalb ohne Übergangstabelle.
 */
enum ShopOrderStatus: string implements HasLabel {
    use HasOptions;

    case Open = 'open';
    case Processing = 'processing';
    case Completed = 'completed';
    case Cancelled = 'cancelled';
    case Refunded = 'refunded';

    public function label(): string {
        return (string) __('enums.integration.shop_order_status.' . $this->value);
    }

    public function tone(): string {
        return match ($this) {
            self::Open => 'warning',
            self::Processing => 'info',
            self::Completed => 'success',
            self::Cancelled, self::Refunded => 'plain',
        };
    }

    /** Storniert oder erstattet — legt der Import nur an, wenn die Einstellung es verlangt. */
    public function isVoid(): bool {
        return $this === self::Cancelled || $this === self::Refunded;
    }
}
