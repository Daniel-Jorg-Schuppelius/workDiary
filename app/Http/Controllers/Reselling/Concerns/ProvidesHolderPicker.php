<?php
/*
 * Created on   : Tue Sep 29 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ProvidesHolderPicker.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Http\Controllers\Reselling\Concerns;

use App\Models\Customer\{Customer, ForeignCustomer};
use App\Support\Sqid;

/** Daten der Halterwahl (`resaleHolderPicker`) für Abo- und Lizenzdialoge. */
trait ProvidesHolderPicker {
    /**
     * Kunden und ihre nicht archivierten Fremdkunden (Sqids) — der
     * Fremdkunden-Schritt erscheint nur bei Kunden, die welche haben.
     *
     * @return array{customers: \Illuminate\Database\Eloquent\Collection<int, Customer>, foreignByCustomer: array<string, list<array{sqid: string, name: string}>>}
     */
    private function holderPicker(): array {
        $foreignByCustomer = [];
        foreach (ForeignCustomer::query()->whereNull('archived_at')->orderBy('name')->get(['id', 'name', 'customer_id']) as $foreign) {
            $foreignByCustomer[Sqid::encode(Customer::class, (int) $foreign->customer_id)][] = ['sqid' => $foreign->sqid, 'name' => (string) $foreign->name];
        }

        return [
            'customers' => Customer::query()->orderBy('name')->get(['id', 'name']),
            'foreignByCustomer' => $foreignByCustomer,
        ];
    }
}
