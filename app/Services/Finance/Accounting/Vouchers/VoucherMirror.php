<?php
/*
 * Created on   : Wed Aug 26 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : VoucherMirror.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Finance\Accounting\Vouchers;

use App\Models\{Customer, ExternalReference, Supplier};
use App\Models\Finance\AccountingVoucher;
use Illuminate\Support\Carbon;

/**
 * Schreibstelle der Belegspiegelung (Feature 122, MVP-731).
 *
 * Jeder Anbieter-Puller übersetzt seine Rohantwort in einen
 * {@see MirroredVoucher}; hier landet sie — an genau einer Stelle — in
 * `accounting_vouchers`. Das ist der Grund für den Umweg über das DTO: die
 * Dublettenregel (Org + Plugin + external_id), die Kontaktzuordnung und der
 * Inkrement-Marker sind für alle Anbieter dieselben und sollen es bleiben.
 *
 * Der Kontakt wird zugeordnet, nicht angelegt: Ein Beleg aus der Buchhaltung
 * ist kein Grund, in workDiary einen Kunden oder Lieferanten zu erfinden.
 */
class VoucherMirror {
    /**
     * @param  array{read: int, created: int, updated: int, skipped: int}  $counters
     */
    public function store(int $organizationId, string $pluginId, MirroredVoucher $voucher, array &$counters): void {
        if (trim($voucher->externalId) === '') {
            $counters['skipped']++;

            return;
        }

        $row = AccountingVoucher::query()->firstOrNew([
            'organization_id' => $organizationId,
            'plugin_id' => $pluginId,
            'external_id' => $voucher->externalId,
        ]);
        $existed = $row->exists;

        [$customerId, $supplierId] = $this->resolveContact($organizationId, $pluginId, $voucher);

        $row->fill([
            'contact_external_id' => $voucher->contactExternalId,
            'customer_id' => $customerId,
            'supplier_id' => $supplierId,
            'voucher_type' => $voucher->rawType,
            'voucher_status' => $voucher->rawStatus,
            'voucher_state' => $voucher->state,
            'direction' => $voucher->direction->value,
            'document_kind' => $voucher->kind->value,
            'is_cancellation' => $voucher->isCancellation,
            'cancels_external_id' => $voucher->cancelsExternalId,
            'voucher_number' => $voucher->number,
            'voucher_date' => $voucher->date,
            'due_date' => $voucher->dueDate,
            'paid_date' => $voucher->paidDate,
            'total_amount' => $voucher->totalAmount,
            'net_amount' => $voucher->netAmount,
            'open_amount' => $voucher->openAmount,
            'currency' => $voucher->currency !== '' ? $voucher->currency : 'EUR',
            'archived' => $voucher->archived,
            'payload' => $voucher->payload,
            'synced_at' => Carbon::now(),
            'source_changed_at' => $voucher->sourceChangedAt,
        ]);
        $row->save();

        $counters[$existed ? 'updated' : 'created']++;
    }

    /**
     * Jüngster bekannter Änderungsstand im Fremdsystem — der Startpunkt des
     * nächsten Laufs. `null` = noch nie gespiegelt (Erstlauf über `$pages`).
     */
    public function lastSourceChange(int $organizationId, string $pluginId): ?Carbon {
        $value = AccountingVoucher::query()
            ->withoutGlobalScopes()
            ->where('organization_id', $organizationId)
            ->where('plugin_id', $pluginId)
            ->max('source_changed_at');

        return is_string($value) && $value !== '' ? Carbon::parse($value) : null;
    }

    /** @return array{read: int, created: int, updated: int, skipped: int} */
    public static function counters(): array {
        return ['read' => 0, 'created' => 0, 'updated' => 0, 'skipped' => 0];
    }

    /**
     * Ganzzahlige Centbeträge (easybill) ohne Umweg über float in einen
     * Dezimalstring: 150 → "1.50", -2599 → "-25.99".
     */
    public static function fromCents(mixed $cents): ?string {
        if (! is_int($cents) && ! (is_string($cents) && preg_match('/^-?\d+$/', $cents) === 1)) {
            return null;
        }
        $value = (int) $cents;
        $sign = $value < 0 ? '-' : '';
        $abs = abs($value);

        return $sign . intdiv($abs, 100) . '.' . str_pad((string) ($abs % 100), 2, '0', STR_PAD_LEFT);
    }

    /** Dezimalwert eines Fremdsystems (Zahl oder Zahlstring) als Dezimalstring. */
    public static function decimal(mixed $value): ?string {
        return is_numeric($value) ? number_format((float) $value, 2, '.', '') : null;
    }

    /** Datumswert eines Fremdsystems als `Y-m-d`; leer/unlesbar → null. */
    public static function date(mixed $value): ?string {
        $raw = is_scalar($value) ? trim((string) $value) : '';
        if ($raw === '' || $raw === '0000-00-00') {
            return null;
        }

        try {
            return Carbon::parse($raw)->toDateString();
        } catch (\Throwable) {
            return null;
        }
    }

    /** Zeitstempel eines Fremdsystems als ISO-String; leer/unlesbar → null. */
    public static function timestamp(mixed $value): ?string {
        $raw = is_scalar($value) ? trim((string) $value) : '';
        if ($raw === '' || str_starts_with($raw, '0000-00-00')) {
            return null;
        }

        try {
            return Carbon::parse($raw)->toDateTimeString();
        } catch (\Throwable) {
            return null;
        }
    }

    /**
     * Kunde/Lieferant zuordnen — in dieser Reihenfolge: bereits gepushte
     * Kontaktreferenz (external_id), Kundennummer, Lieferantenname.
     *
     * @return array{0: ?int, 1: ?int}
     */
    private function resolveContact(int $organizationId, string $pluginId, MirroredVoucher $voucher): array {
        $externalId = trim((string) $voucher->contactExternalId);
        if ($externalId !== '') {
            $reference = ExternalReference::query()
                ->forPlugin($organizationId, $pluginId, 'contact')
                ->where('external_id', $externalId)
                ->first();
            if ($reference instanceof ExternalReference) {
                if ($reference->referenceable_type === (new Customer())->getMorphClass()) {
                    return [(int) $reference->referenceable_id, null];
                }
                if ($reference->referenceable_type === (new Supplier())->getMorphClass()) {
                    return [null, (int) $reference->referenceable_id];
                }
            }
        }

        $number = trim((string) $voucher->customerNumber);
        if ($number !== '') {
            $customer = Customer::query()
                ->withoutGlobalScopes()
                ->where('organization_id', $organizationId)
                ->where('number', $number)
                ->first();
            if ($customer instanceof Customer) {
                return [(int) $customer->getKey(), null];
            }
        }

        $supplierName = trim((string) $voucher->supplierName);
        if ($supplierName !== '') {
            $supplier = Supplier::query()
                ->withoutGlobalScopes()
                ->where('organization_id', $organizationId)
                ->where('name', $supplierName)
                ->first();
            if ($supplier instanceof Supplier) {
                return [null, (int) $supplier->getKey()];
            }
        }

        return [null, null];
    }
}
