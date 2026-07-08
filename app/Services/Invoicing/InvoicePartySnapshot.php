<?php
/*
 * Created on   : Wed Jul 08 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : InvoicePartySnapshot.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Invoicing;

use App\Models\Invoice;
use App\Services\Invoicing\EInvoice\XRechnungGenerator;

/**
 * Empfänger-/Verkäufer-Snapshot (Feature 066, MVP-162): friert beim
 * Ausstellen die Stammdaten BEIDER Parteien am Beleg ein — spätere
 * Stammdatenänderungen deuten ausgestellte Rechnungen nie um (§ 14 UStG,
 * GoBD). Verkäuferquelle ist dieselbe wie bei der E-Rechnung
 * (XRechnungGenerator::sellerData — keine zweite Leselogik).
 */
class InvoicePartySnapshot {
    public function __construct(private readonly XRechnungGenerator $einvoice) {}

    /** @return array{seller: array<string, mixed>, buyer: array<string, mixed>, frozen_at: string} */
    public function capture(Invoice $invoice): array {
        $customer = $invoice->customer;

        return [
            'seller' => $this->einvoice->sellerData($invoice),
            'buyer' => [
                'name' => (string) ($customer->name ?? ''),
                'street' => (string) ($customer->address_street ?? ''),
                'zip' => (string) ($customer->address_zip ?? ''),
                'city' => (string) ($customer->address_city ?? ''),
                'country' => strtoupper((string) ($customer->country ?? 'DE')),
                'vat_id' => (string) ($customer->vat_id ?? ''),
                'email' => (string) ($customer->email ?? ''),
            ],
            'frozen_at' => now()->toIso8601String(),
        ];
    }
}
