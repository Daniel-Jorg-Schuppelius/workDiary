<?php
/*
 * Created on   : Mon Aug 24 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : CustomerMasterDataSection.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Privacy\SubjectData;

use App\Models\Customer;
use Illuminate\Database\Eloquent\Model;

/** Kunden-Stammdaten inkl. Ansprechpartnerliste und Bankverbindung der Kundenzeile. */
class CustomerMasterDataSection extends AbstractSubjectSection {
    public function key(): string {
        return 'master_data';
    }

    public function title(): string {
        return __('Stammdaten');
    }

    public function portable(): bool {
        return true;
    }

    public function build(Model $subject): array {
        $this->expect($subject, Customer::class);
        /** @var Customer $c */
        $c = $subject;

        $result = ['fields' => [
            'number' => $this->field(__('Kundennummer'), $c->number),
            'name' => $this->field(__('Name'), $c->name),
            'company' => $this->field(__('Firma'), $c->company),
            'contact_name' => $this->field(__('Ansprechpartner'), $c->contact_name),
            'email' => $this->field(__('E-Mail'), $c->email),
            'phone' => $this->field(__('Telefon'), $c->phone),
            'mobile' => $this->field(__('Mobil'), $c->mobile),
            'fax' => $this->field(__('Fax'), $c->fax),
            'homepage' => $this->field(__('Homepage'), $c->homepage),
            'address_street' => $this->field(__('Straße'), $c->address_street),
            'address_zip' => $this->field(__('PLZ'), $c->address_zip),
            'address_city' => $this->field(__('Ort'), $c->address_city),
            'country' => $this->field(__('Land'), $c->country),
            'vat_id' => $this->field(__('USt-IdNr.'), $c->vat_id),
            'tax_number' => $this->field(__('Steuernummer'), $c->tax_number),
            'bank_account_holder' => $this->field(__('Kontoinhaber'), $c->bank_account_holder),
            'bank_iban' => $this->field(__('IBAN'), $c->bank_iban),
            'bank_bic' => $this->field(__('BIC'), $c->bank_bic),
            'bank_name' => $this->field(__('Bank'), $c->bank_name),
            'comment' => $this->field(__('Notizen'), $c->comment),
        ]];

        // Ansprechpartner (JSON-Spalte): Vollausgabe als Liste, nicht Teil der CSV-Zeile.
        $persons = $c->contact_persons ?? [];
        if ($persons !== []) {
            $result['lists'] = [__('Ansprechpartner') => array_values(array_map(
                fn(array $p): array => array_map(fn(mixed $v): ?string => $this->str($v), $p),
                $persons,
            ))];
        }

        return $result;
    }
}
