<?php
/*
 * Created on   : Mon Aug 24 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : JobApplicationMasterDataSection.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Privacy\SubjectData;

use App\Models\Applications\JobApplication;
use Illuminate\Database\Eloquent\Model;

/**
 * Bewerber-Stammdaten: Name/E-Mail/Telefon/Notizen liegen at-rest
 * verschlüsselt (APP_KEY) — die Casts entschlüsseln beim Lesen.
 */
class JobApplicationMasterDataSection extends AbstractSubjectSection {
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
        $this->expect($subject, JobApplication::class);
        /** @var JobApplication $a */
        $a = $subject;

        return ['fields' => [
            'candidate_name' => $this->field(__('Name'), $a->candidate_name),
            'email' => $this->field(__('E-Mail'), $a->email),
            'phone' => $this->field(__('Telefon'), $a->phone),
            'source' => $this->field(__('Quelle'), $a->source),
            'status' => $this->field(__('Status'), $a->status),
            'received_at' => $this->field(__('Eingegangen am'), $a->received_at),
            'consent_talent_pool_at' => $this->field(__('Talent-Pool-Einwilligung am'), $a->consent_talent_pool_at),
            'consent_expires_on' => $this->field(__('Einwilligung läuft ab'), $this->date($a->consent_expires_on)),
            'retention_until' => $this->field(__('Aufbewahrung bis'), $this->date($a->retention_until)),
            'privacy_ack_at' => $this->field(__('Datenschutzhinweis bestätigt am'), $a->privacy_ack_at),
            'notes' => $this->field(__('Notizen'), $a->notes),
            'anonymized_at' => $this->field(__('Anonymisiert am'), $a->anonymized_at),
        ]];
    }
}
