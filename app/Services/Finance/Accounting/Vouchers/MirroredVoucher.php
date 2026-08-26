<?php
/*
 * Created on   : Wed Aug 26 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : MirroredVoucher.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Finance\Accounting\Vouchers;

use App\Enums\Billing\{DocumentDirection, DocumentKind};

/**
 * Ein Beleg eines Buchhaltungssystems, bereits auf die anbieterneutrale
 * Sprache übersetzt (Feature 122, MVP-731).
 *
 * Die Übersetzung passiert im jeweiligen {@see VoucherPuller} — dort, wo man
 * die Anbieter-Semantik kennt. Der {@see VoucherMirror} bekommt nur noch
 * fertige Werte und muss nie raten, was „CREDIT", „D" oder Status 4 bedeutet.
 *
 * Beträge sind **Dezimalstrings** (nie float): easybill liefert Cents,
 * sevDesk Dezimalzahlen, InvoicePlane DECIMAL-Spalten — das gemeinsame Format
 * ist der String, den die `decimal:2`-Spalte auch zurückgibt.
 */
final readonly class MirroredVoucher {
    /**
     * @param  array<string, mixed>  $payload  Rohantwort des Fremdsystems (Nachweis).
     */
    public function __construct(
        public string $externalId,
        public DocumentDirection $direction,
        public DocumentKind $kind,
        public ?string $rawType = null,
        public ?string $rawStatus = null,
        /** draft|open|paid|cancelled — normalisierter Zustand im Fremdsystem. */
        public string $state = 'open',
        public ?string $number = null,
        public ?string $date = null,
        public ?string $dueDate = null,
        public ?string $paidDate = null,
        public ?string $totalAmount = null,
        public ?string $netAmount = null,
        public ?string $openAmount = null,
        public string $currency = 'EUR',
        public bool $archived = false,
        public bool $isCancellation = false,
        public ?string $cancelsExternalId = null,
        public ?string $contactExternalId = null,
        public ?string $supplierName = null,
        public ?string $customerNumber = null,
        public ?string $sourceChangedAt = null,
        public array $payload = [],
    ) {}
}
