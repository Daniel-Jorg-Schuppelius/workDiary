<?php
/*
 * Created on   : Mon Oct 26 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : TransferSource.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Finance\Contracts;

use App\Enums\Finance\TransferChannel;
use App\Models\Customer\Customer;
use App\Models\Finance\{BillingTransfer, BillingTransferPosition};
use Illuminate\Support\Collection;

/**
 * Übergabequelle eines Moduls (Feature 185, MVP-1422): Zeit und Material
 * liest der Übergabedienst fest, jeden weiteren Kanal über diesen
 * Erweiterungspunkt (`ModuleRegistry::extensions(TransferSource::class)`).
 * Die Quelle liefert die übergabefähigen Zeilen, baut die Positionen und
 * führt die Verbrauchsmarke ihrer Quellen — der Dienst kennt das Modul nicht.
 *
 * @phpstan-type SourceRow array{type: string, id: int, date: string|null, quantity: float, amount: float, unit: string|null, unit_price: float|null, tax_rate: float|null, cost_position: string|null}
 */
interface TransferSource {
    public function channel(): TransferChannel;

    /**
     * Übergabefähige Quellen des Kunden im Zeitraum, ohne die, die eine
     * bestätigte oder übergebene Übergabe schon hält.
     *
     * @param  array{from?: string|\DateTimeInterface|null, to?: string|\DateTimeInterface|null}  $period
     * @param  list<int>|null  $sourceIds  Auswahl (Schnittmenge), null = alle
     * @return Collection<int, SourceRow>
     */
    public function collect(Customer $customer, array $period, ?array $sourceIds): Collection;

    /**
     * Positionen aus den Zeilen der Übergabe (ungespeichert; der Dienst friert sie beim Bestätigen ein).
     *
     * @return Collection<int, BillingTransferPosition>
     */
    public function positions(BillingTransfer $transfer): Collection;

    /**
     * Verbrauchsmarke der Quellen setzen (übertragen) oder lösen (storniert,
     * verworfen). `$heldElsewhere` nennt je Quelltyp die IDs, die eine andere
     * bestätigte Übergabe hält — die bleiben verbraucht.
     *
     * @param  array<string, list<int>>  $heldElsewhere
     * @return int geänderte Quellen
     */
    public function setConsumed(BillingTransfer $transfer, bool $consumed, array $heldElsewhere): int;
}
