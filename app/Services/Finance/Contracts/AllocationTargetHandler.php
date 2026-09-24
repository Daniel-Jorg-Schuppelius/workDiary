<?php
/*
 * Created on   : Thu Sep 24 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : AllocationTargetHandler.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Finance\Contracts;

use App\Models\Finance\{BankTransaction, PaymentAllocation};
use Illuminate\Database\Eloquent\Model;

/**
 * Erweiterungspunkt der Bankzuordnung (MVP-863): Ein Modul bucht die Wirkung
 * einer Zuordnung auf sein Ziel (Beitragsforderung, …) selbst; Rechnung,
 * Spese und Kundenkonto bleiben im Finanzkern. Registrierung über
 * `Manifest::extensions()`.
 */
interface AllocationTargetHandler {
    public function supports(Model $target): bool;

    /** Bestätigte Zuordnung: Zahlung buchen oder eine bereits gebuchte wiedererkennen. */
    public function book(Model $target, BankTransaction $transaction, PaymentAllocation $allocation): void;

    /** Zuordnung aufgehoben: Wirkung zurücknehmen. */
    public function revert(PaymentAllocation $allocation): void;

    /** Rücklastschrift: kompensiert die gebuchte Zahlung genau einmal. */
    public function chargeback(PaymentAllocation $original, BankTransaction $returnTransaction, PaymentAllocation $compensation, ?string $reason): void;
}
