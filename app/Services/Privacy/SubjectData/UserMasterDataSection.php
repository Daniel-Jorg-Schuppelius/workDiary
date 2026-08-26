<?php
/*
 * Created on   : Mon Aug 24 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : UserMasterDataSection.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Privacy\SubjectData;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;

/**
 * Mitarbeiter-Stammdaten (Art. 15 Abs. 1 DSGVO): Identität, Kontakt,
 * Beschäftigung und Vergütung — inklusive der at-rest verschlüsselten
 * PII-Felder (Steuer-ID, SV-Nummer, CTI-Durchwahl), die die Casts beim
 * Lesen entschlüsseln.
 */
class UserMasterDataSection extends AbstractSubjectSection {
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
        $this->expect($subject, User::class);
        /** @var User $u */
        $u = $subject;

        return ['fields' => [
            'name' => $this->field(__('Name'), $u->name),
            'first_name' => $this->field(__('Vorname'), $u->first_name),
            'middle_names' => $this->field(__('Weitere Vornamen'), $u->middle_names),
            'last_name' => $this->field(__('Nachname'), $u->last_name),
            'date_of_birth' => $this->field(__('Geburtsdatum'), $this->date($u->date_of_birth)),
            'email' => $this->field(__('E-Mail'), $u->email),
            'phone' => $this->field(__('Telefon'), $u->phone),
            'mobile' => $this->field(__('Mobil'), $u->mobile),
            'fax' => $this->field(__('Fax'), $u->fax),
            'home_address' => $this->field(__('Anschrift'), $u->home_address),
            'cti_extension' => $this->field(__('CTI-Durchwahl'), $u->cti_extension),
            'personnel_number' => $this->field(__('Personalnummer'), $u->personnel_number),
            'tax_identification_number' => $this->field(__('Steuer-Identifikationsnummer'), $u->tax_identification_number),
            'social_security_number' => $this->field(__('Sozialversicherungsnummer'), $u->social_security_number),
            'health_insurance' => $this->field(__('Krankenkasse'), $u->health_insurance),
            'tax_class' => $this->field(__('Steuerklasse'), $u->tax_class),
            'child_allowances' => $this->field(__('Kinderfreibeträge'), $u->child_allowances),
            'church_tax' => $this->field(__('Kirchensteuer'), (bool) $u->church_tax),
            'employment_type' => $this->field(__('Beschäftigungsart'), $u->employment_type?->label()),
            'employment_start_date' => $this->field(__('Eintritt'), $this->date($u->employment_start_date)),
            'employment_end_date' => $this->field(__('Austritt'), $this->date($u->employment_end_date)),
            'payroll_hourly_wage' => $this->field(__('Stundenlohn (Lohn)'), $this->money($u->payroll_hourly_wage)),
        ]];
    }

    private function money(?\CommonToolkit\ValueObjects\Money $money): ?string {
        return $money === null ? null : $money->getAmount() . ' ' . $money->getCurrency()->value;
    }
}
