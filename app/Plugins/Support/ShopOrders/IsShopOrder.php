<?php
/*
 * Created on   : Sat Oct 24 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : IsShopOrder.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Plugins\Support\ShopOrders;

use App\Casts\MoneyCast;
use App\Enums\Integration\{MarketplaceInboxStatus, ShopOrderStatus};
use App\Models\Customer\Customer;
use CommonToolkit\Enums\CurrencyCode;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Spiegel einer Shop-Bestellung (WooCommerce, Shopware, PrestaShop): eine Zeile
 * je Bestellung und Organisation, Kundenbezug über die Marktplatz-Zuordnung.
 * Käuferdaten tragen nur Name, E-Mail und Firma; die Aufbewahrung räumt sie ab.
 */
trait IsShopOrder {
    public function initializeIsShopOrder(): void {
        $this->mergeFillable([
            'organization_id', 'remote_order_id', 'order_number', 'status', 'remote_status_code', 'currency',
            'gross_amount', 'net_amount', 'refunded_amount', 'buyer_external_id', 'buyer', 'items', 'ordered_at',
            'remote_modified_at', 'remote_deleted_at', 'customer_id', 'inbox_status',
        ]);
        $this->mergeCasts([
            'status' => ShopOrderStatus::class,
            'currency' => CurrencyCode::class,
            'gross_amount' => MoneyCast::class . ':currency,2',
            'net_amount' => MoneyCast::class . ':currency,2',
            'refunded_amount' => MoneyCast::class . ':currency,2',
            'buyer' => 'array',
            'items' => 'array',
            'ordered_at' => 'immutable_datetime',
            'remote_modified_at' => 'immutable_datetime',
            'remote_deleted_at' => 'immutable_datetime',
            'inbox_status' => MarketplaceInboxStatus::class,
        ]);
    }

    /** @return BelongsTo<Customer, $this> */
    public function customer(): BelongsTo {
        return $this->belongsTo(Customer::class);
    }

    /**
     * Bestellnummer, Shop-ID oder Käufer — Liste und globale Suche (Feature 181).
     *
     * @param  Builder<\Illuminate\Database\Eloquent\Model>  $query
     */
    public static function whereMatching(Builder $query, string $term): void {
        $query->where(fn (Builder $q) => $q->whereLikeEscaped('order_number', $term)
            ->orWhereLikeEscaped('remote_order_id', $term)
            ->orWhereLikeEscaped('buyer->name', $term, ignoreCase: true)
            ->orWhereLikeEscaped('buyer->email', $term, ignoreCase: true)
            ->orWhereLikeEscaped('buyer->company', $term, ignoreCase: true));
    }

    public function displayNumber(): string {
        return '#' . ($this->getAttribute('order_number') ?? $this->getAttribute('remote_order_id'));
    }

    public function buyerLabel(): ?string {
        $buyer = (array) ($this->getAttribute('buyer') ?? []);
        $name = $buyer['company'] ?? $buyer['name'] ?? $buyer['email'] ?? null;

        return is_string($name) && $name !== '' ? $name : null;
    }
}
