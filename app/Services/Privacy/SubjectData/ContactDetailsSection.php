<?php
/*
 * Created on   : Mon Aug 24 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ContactDetailsSection.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Privacy\SubjectData;

use App\Models\{ContactAddress, ContactBankAccount, Customer, Supplier};
use Illuminate\Database\Eloquent\Model;
use InvalidArgumentException;

/**
 * Kontaktdaten aus den `contact_*`-Tabellen (Kunde/Lieferant): Primäradresse
 * und -bankverbindung flach als Felder (→ Art.-20-CSV), alle Einträge als
 * Vollausgabe-Listen. Die at-rest verschlüsselten Felder entschlüsseln die
 * Casts beim Lesen.
 */
class ContactDetailsSection extends AbstractSubjectSection {
    public function key(): string {
        return 'contact_details';
    }

    public function title(): string {
        return __('Adressen & Bankverbindungen');
    }

    public function portable(): bool {
        return true;
    }

    public function build(Model $subject): array {
        if (! $subject instanceof Customer && ! $subject instanceof Supplier) {
            throw new InvalidArgumentException(self::class . ' erwartet Customer oder Supplier.');
        }

        $addresses = $subject->addresses()->orderBy('id')->get();
        $accounts = $subject->bankAccounts()->orderBy('id')->get();
        $primaryAddress = $addresses->firstWhere('is_primary', true) ?? $addresses->first();
        $primaryAccount = $accounts->firstWhere('is_primary', true) ?? $accounts->first();

        return [
            'fields' => [
                'primary_street' => $this->field(__('Straße (Primäradresse)'), $primaryAddress?->street),
                'primary_zip' => $this->field(__('PLZ (Primäradresse)'), $primaryAddress?->zip),
                'primary_city' => $this->field(__('Ort (Primäradresse)'), $primaryAddress?->city),
                'primary_country' => $this->field(__('Land (Primäradresse)'), $primaryAddress?->country_code),
                'primary_account_holder' => $this->field(__('Kontoinhaber (primär)'), $primaryAccount?->account_holder),
                'primary_iban' => $this->field(__('IBAN (primär)'), $primaryAccount?->iban),
                'primary_bic' => $this->field(__('BIC (primär)'), $primaryAccount?->bic),
            ],
            'lists' => [
                __('Adressen') => array_values($addresses->map(fn(ContactAddress $a): array => [
                    'kind' => $this->str($a->kind),
                    'street' => $this->str($a->street),
                    'supplement' => $this->str($a->supplement),
                    'zip' => $this->str($a->zip),
                    'city' => $this->str($a->city),
                    'country' => $this->str($a->country_code),
                    'primary' => $this->str((bool) $a->is_primary),
                ])->all()),
                __('Bankverbindungen') => array_values($accounts->map(fn(ContactBankAccount $b): array => [
                    'account_holder' => $this->str($b->account_holder),
                    'iban' => $this->str($b->iban),
                    'bic' => $this->str($b->bic),
                    'bank_name' => $this->str($b->bank_name),
                    'primary' => $this->str((bool) $b->is_primary),
                ])->all()),
            ],
        ];
    }
}
