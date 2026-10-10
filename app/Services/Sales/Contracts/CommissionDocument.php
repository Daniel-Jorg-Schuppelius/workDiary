<?php
/*
 * Created on   : Sat Oct 10 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : CommissionDocument.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Sales\Contracts;

use App\Enums\Invoicing\InvoiceStatus;
use CommonToolkit\Enums\CurrencyCode;
use CommonToolkit\ValueObjects\Money;
use Illuminate\Support\Carbon;

/**
 * Provisionsfähiger Beleg (MVP-1119): die lokale Rechnung oder eine
 * gespiegelte Rechnung des Buchhaltungsprogramms. Provisionszeilen hängen
 * über `documentType()`/`documentId()` (Morph-Alias) am Beleg.
 */
interface CommissionDocument {
    public function documentType(): string;

    public function documentId(): int;

    /** Lokale Rechnung, sonst null. */
    public function invoiceId(): ?int;

    public function organizationId(): int;

    public function customerId(): ?int;

    public function number(): string;

    public function currency(): CurrencyCode;

    /** Gestellt, teilweise bezahlt, bezahlt oder storniert. */
    public function status(): InvoiceStatus;

    /** Bemessungsgrundlage: Netto des Belegs. */
    public function netAmount(): Money;

    /** Netto der Positionen einer Artikelkategorie; null ohne solche Position. */
    public function netOfCategory(string $category): ?Money;

    /**
     * Anteil der Zahlungen am Belegbetrag, höchstens 1.
     *
     * @return numeric-string
     */
    public function paidShare(): string;

    public function paidOn(): ?Carbon;

    /** Stichtag der Periodenzuordnung, in der Regel der Zahltag. */
    public function earnedOn(): Carbon;

    public function cancelledOn(): ?Carbon;

    /** Gutschrift oder Storno, mindert die Provision des Ursprungsbelegs. */
    public function isReducing(): bool;

    public function origin(): ?self;

    public function salesUserId(): ?int;

    public function salesAgentId(): ?int;

    public function assignSales(?int $userId, ?int $agentId): void;
}
