<?php
/*
 * Created on   : Sun Aug 23 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ContactDetailsProjectionObserver.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Observers;

use App\Models\{ContactAddress, ContactBankAccount, Customer, Supplier};
use Illuminate\Database\Eloquent\Model;

/**
 * Projektion contact_* → Inline-Spalten (Vollscan 2026-08-23, F8, Entscheid
 * E6): contact_addresses/contact_bank_accounts sind führend; die ~33
 * Lesestellen von customers/suppliers address_… & bank_… bleiben gültig,
 * weil dieser Observer die primäre Adresse/Bankverbindung nach jedem
 * Save/Delete quiet zurückspiegelt (updateQuietly — keine Event-Kaskade).
 */
class ContactDetailsProjectionObserver {
    public function saved(Model $record): void {
        $this->project($record);
    }

    public function deleted(Model $record): void {
        $this->project($record);
    }

    private function project(Model $record): void {
        $parent = match (true) {
            $record instanceof ContactAddress => $record->addressable,
            $record instanceof ContactBankAccount => $record->accountable,
            default => null,
        };
        if (! ($parent instanceof Customer) && ! ($parent instanceof Supplier)) {
            return; // User u. a. haben keine Inline-Spalten.
        }

        if ($record instanceof ContactAddress) {
            $primary = $parent->primaryAddress();
            $parent->updateQuietly([
                'address_street' => $primary?->street,
                'address_zip' => $primary?->zip,
                'address_city' => $primary?->city,
                'country' => $primary?->country_code,
            ]);

            return;
        }

        $primary = $parent->primaryBankAccount();
        $parent->updateQuietly([
            'bank_account_holder' => $primary?->account_holder,
            'bank_iban' => $primary?->iban,
            'bank_bic' => $primary?->bic,
            'bank_name' => $primary?->bank_name,
        ]);
    }
}
