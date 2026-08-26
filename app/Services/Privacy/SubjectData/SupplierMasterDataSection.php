<?php
/*
 * Created on   : Mon Aug 24 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : SupplierMasterDataSection.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Privacy\SubjectData;

use App\Models\Supplier;
use Illuminate\Database\Eloquent\Model;

/** Lieferanten-Stammdaten (relevant bei Einzelunternehmern/natürlichen Personen). */
class SupplierMasterDataSection extends AbstractSubjectSection {
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
        $this->expect($subject, Supplier::class);
        /** @var Supplier $s */
        $s = $subject;

        return ['fields' => [
            'number' => $this->field(__('Lieferantennummer'), $s->number),
            'vendor_number' => $this->field(__('Kreditorennummer'), $s->vendor_number),
            'name' => $this->field(__('Name'), $s->name),
            'company' => $this->field(__('Firma'), $s->company),
            'contact_name' => $this->field(__('Ansprechpartner'), $s->contact_name),
            'email' => $this->field(__('E-Mail'), $s->email),
            'phone' => $this->field(__('Telefon'), $s->phone),
            'mobile' => $this->field(__('Mobil'), $s->mobile),
            'fax' => $this->field(__('Fax'), $s->fax),
            'homepage' => $this->field(__('Homepage'), $s->homepage),
            'address_street' => $this->field(__('Straße'), $s->address_street),
            'address_zip' => $this->field(__('PLZ'), $s->address_zip),
            'address_city' => $this->field(__('Ort'), $s->address_city),
            'country' => $this->field(__('Land'), $s->country),
            'vat_id' => $this->field(__('USt-IdNr.'), $s->vat_id),
            'tax_number' => $this->field(__('Steuernummer'), $s->tax_number),
            'bank_account_holder' => $this->field(__('Kontoinhaber'), $s->bank_account_holder),
            'bank_iban' => $this->field(__('IBAN'), $s->bank_iban),
            'bank_bic' => $this->field(__('BIC'), $s->bank_bic),
            'bank_name' => $this->field(__('Bank'), $s->bank_name),
            'comment' => $this->field(__('Notizen'), $s->comment),
        ]];
    }
}
