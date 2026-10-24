<?php
/*
 * Created on   : Sat Oct 24 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ShopOrder.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Plugins\Support\ShopOrders;

use Illuminate\Database\Eloquent\Builder;

/** Gespiegelte Shop-Bestellung, siehe {@see IsShopOrder}. */
interface ShopOrder {
    public function displayNumber(): string;

    public function buyerLabel(): ?string;

    /**
     * Bestellnummer, Shop-ID oder Käufer enthalten den Suchbegriff.
     *
     * @param  Builder<\Illuminate\Database\Eloquent\Model>  $query
     */
    public static function whereMatching(Builder $query, string $term): void;
}
