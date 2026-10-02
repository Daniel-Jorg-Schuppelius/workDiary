<?php
/*
 * Created on   : Fri Oct 02 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : OnlinePaymentSnapshot.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Plugins\Support\Payments;

use App\Enums\Invoicing\OnlinePaymentStatus;
use Carbon\CarbonImmutable;
use CommonToolkit\ValueObjects\Money;

/** Stand einer Zahlung, wie ihn der Anbieter auf Nachfrage meldet. */
final readonly class OnlinePaymentSnapshot {
    public function __construct(
        public OnlinePaymentStatus $status,
        /** Gezahlter Betrag laut Anbieter — muss zum Auftrag passen, sonst wird nichts gebucht. */
        public Money $amount,
        public ?CarbonImmutable $paidAt = null,
        /** Gebühr des Anbieters, soweit er sie je Zahlung meldet. */
        public ?Money $fee = null,
        public ?Money $refunded = null,
        public ?string $method = null,
    ) {}
}
