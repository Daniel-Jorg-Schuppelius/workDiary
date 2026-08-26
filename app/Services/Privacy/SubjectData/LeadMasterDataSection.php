<?php
/*
 * Created on   : Mon Aug 24 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : LeadMasterDataSection.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Privacy\SubjectData;

use App\Models\Lead;
use Illuminate\Database\Eloquent\Model;

/** Lead-Stammdaten (Vertriebskontakt vor Kundenanlage). */
class LeadMasterDataSection extends AbstractSubjectSection {
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
        $this->expect($subject, Lead::class);
        /** @var Lead $l */
        $l = $subject;

        return ['fields' => [
            'company' => $this->field(__('Firma'), $l->company),
            'contact_name' => $this->field(__('Ansprechpartner'), $l->contact_name),
            'email' => $this->field(__('E-Mail'), $l->email),
            'phone' => $this->field(__('Telefon'), $l->phone),
            'source' => $this->field(__('Quelle'), $l->source->label()),
            'interest' => $this->field(__('Interesse'), $l->interest),
            'status' => $this->field(__('Status'), $l->status->label()),
            'last_contact_at' => $this->field(__('Letzter Kontakt'), $l->last_contact_at),
            'anonymized_at' => $this->field(__('Anonymisiert am'), $l->anonymized_at),
        ]];
    }
}
