<?php
/*
 * Created on   : Sat Oct 24 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : RemoteOrder.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Plugins\Support\ShopOrders;

use App\Enums\Integration\ShopOrderStatus;
use Carbon\CarbonImmutable;
use CommonToolkit\Enums\CurrencyCode;
use CommonToolkit\Helper\Data\NumberHelper;
use CommonToolkit\ValueObjects\Money;

/**
 * Eine Shop-Bestellung in der gemeinsamen Form (Feature 175). Beträge als
 * Dezimalzeichenkette, Positionen nur als Text — WorkDiary bucht daraus
 * nichts, es zeigt und ordnet zu. `buyerKey` ist die stabile Käuferkennung
 * (Kundenkonto des Shops oder `guest:<e-mail>`), nie der Name.
 * `refundedAmount` ist null, wenn der Shop die Erstattungen nicht nennt.
 */
final readonly class RemoteOrder {
    /**
     * @param  list<array{name: string, sku: ?string, quantity: string, total: ?string}>  $items
     */
    public function __construct(
        public string $id,
        public ?string $number,
        public ShopOrderStatus $status,
        public ?string $remoteStatus,
        public ?CurrencyCode $currency,
        public ?string $grossAmount,
        public ?string $netAmount,
        public ?CarbonImmutable $orderedAt,
        public ?CarbonImmutable $modifiedAt,
        public ?string $buyerKey,
        public ?string $buyerName,
        public ?string $buyerEmail,
        public ?string $buyerCompany,
        public array $items,
        public ?string $refundedAmount = null,
    ) {}

    /** Betrag aus der Shop-Antwort als Dezimalzeichenkette; Zahlen über das Toolkit, nicht-numerische Werte ergeben null. */
    public static function amount(mixed $value): ?string {
        if (is_int($value) || is_float($value)) {
            return NumberHelper::toUSFormat($value, 2);
        }

        return is_string($value) && is_numeric(trim($value)) ? NumberHelper::toUSFormat(trim($value), 2) : null;
    }

    public function grossMoney(): ?Money {
        return $this->grossAmount !== null ? Money::ofNullable($this->grossAmount, $this->currency ?? CurrencyCode::Euro) : null;
    }

    /** @return array{name: ?string, email: ?string, company: ?string}|null */
    public function buyer(): ?array {
        if ($this->buyerName === null && $this->buyerEmail === null && $this->buyerCompany === null) {
            return null;
        }

        return ['name' => $this->buyerName, 'email' => $this->buyerEmail, 'company' => $this->buyerCompany];
    }
}
