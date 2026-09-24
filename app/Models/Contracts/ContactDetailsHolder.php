<?php
/*
 * Created on   : Thu Sep 24 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ContactDetailsHolder.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Models\Contracts;

use App\Models\Contacts\{ContactAddress, ContactBankAccount};
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphMany;

/**
 * Partei mit Kontaktdaten-Satelliten (MVP-869): Adressen in
 * `contact_addresses`, Bankverbindungen in `contact_bank_accounts`;
 * Implementierung liefert {@see \App\Models\Concerns\HasContactAndBankDetails}.
 */
interface ContactDetailsHolder {
    /** @return MorphMany<ContactAddress, covariant Model> */
    public function addresses(): MorphMany;

    /** @return MorphMany<ContactBankAccount, covariant Model> */
    public function bankAccounts(): MorphMany;

    public function primaryAddress(): ?ContactAddress;

    public function primaryBankAccount(): ?ContactBankAccount;

    public function contactAddressKind(): string;
}
