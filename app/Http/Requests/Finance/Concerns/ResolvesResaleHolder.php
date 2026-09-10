<?php
/*
 * Created on   : Thu Sep 10 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ResolvesResaleHolder.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Http\Requests\Finance\Concerns;

use App\Models\{Customer, ForeignCustomer};
use Illuminate\Validation\Validator;

/**
 * Halterwahl des Reselling-Registers (Feature 152, Review 2026-09-10): die
 * eine Stelle für „Kunde, Fremdkunde, eigener Bestand oder offen" — Abo-Dialog,
 * Lizenzabtretung und Inbox-Zuordnung prüfen und lösen den Halter hier.
 * Erwartet dekodierte IDs (`DecodesSqidInputs`); die Org-Grenze prüft
 * `ExistsInCurrentOrganization` an den Feldern.
 *
 * Modi: `customer`/`partner` brauchen einen Kunden, `foreign` einen Fremdkunden,
 * `own`/`none` keinen. Ein gewählter Fremdkunde muss zum gewählten Kunden
 * gehören; ein archivierter Fremdkunde wird nicht zum neuen Halter.
 */
trait ResolvesResaleHolder {
    /**
     * @param  array<string, mixed>  $data  validationData()
     * @param  int|null  $currentForeignId  bereits gespeicherter Fremdkunde (bleibt auch archiviert erlaubt)
     */
    protected function validateHolder(Validator $validator, string $mode, array $data, ?int $currentForeignId = null): void {
        $customerId = self::holderId($data['customer_id'] ?? null);
        $foreignId = self::holderId($data['foreign_customer_id'] ?? null);
        if (in_array($mode, ['customer', 'partner'], true) && $customerId === null) {
            $validator->errors()->add('customer_id', (string) __('resale.error.customer_required'));
        }
        if ($mode === 'foreign' && $foreignId === null) {
            $validator->errors()->add('foreign_customer_id', (string) __('resale.error.foreign_required'));
        }
        if ($foreignId === null || ! in_array($mode, ['customer', 'foreign'], true)) {
            return;
        }
        $foreign = ForeignCustomer::query()->find($foreignId);
        if ($foreign === null) {
            return; // ExistsInCurrentOrganization meldet das Feld
        }
        if ($customerId !== null && (int) $foreign->customer_id !== $customerId) {
            $validator->errors()->add('foreign_customer_id', (string) __('resale.holder_error.foreign_mismatch'));
        }
        if ($foreign->archived_at !== null && $foreign->id !== $currentForeignId) {
            $validator->errors()->add('foreign_customer_id', (string) __('resale.holder_error.foreign_archived'));
        }
    }

    /**
     * Halter aus der validierten Eingabe. Kunde + Fremdkunde gewählt ⇒ der
     * Fremdkunde hält, die Rechnung geht an den Kunden (`billedTo`).
     *
     * @param  array<string, mixed>  $data
     * @return array{customer: Customer|null, foreign: ForeignCustomer|null, own: bool}
     */
    protected function resolveHolder(string $mode, array $data): array {
        $customerId = self::holderId($data['customer_id'] ?? null);
        $foreignId = self::holderId($data['foreign_customer_id'] ?? null);
        $foreign = in_array($mode, ['customer', 'foreign'], true) && $foreignId !== null ? ForeignCustomer::query()->find($foreignId) : null;
        $customer = in_array($mode, ['customer', 'partner', 'foreign'], true) && $customerId !== null ? Customer::query()->find($customerId) : null;
        if ($foreign !== null && $customer === null) {
            $customer = $foreign->customer;
        }

        return ['customer' => $customer, 'foreign' => $foreign, 'own' => $mode === 'own'];
    }

    private static function holderId(mixed $value): ?int {
        return is_numeric($value) && (int) $value > 0 ? (int) $value : null;
    }
}
