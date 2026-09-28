<?php
/*
 * Created on   : Mon Sep 28 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ClubMemberMasterDataSection.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Privacy\SubjectData;

use App\Models\Club\{ClubGuardian, ClubMember};
use Illuminate\Database\Eloquent\Model;

/** Stammdaten eines Vereinsmitglieds samt Erziehungsberechtigten (MVP-1009). */
class ClubMemberMasterDataSection extends AbstractSubjectSection {
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
        $this->expect($subject, ClubMember::class);
        /** @var ClubMember $m */
        $m = $subject;

        return [
            'fields' => [
                'member_no' => $this->field(__('Mitgliedsnummer'), $m->member_no),
                'first_name' => $this->field(__('Vorname'), $m->first_name),
                'last_name' => $this->field(__('Nachname'), $m->last_name),
                'birth_date' => $this->field(__('Geburtsdatum'), $this->date($m->birth_date)),
                'email' => $this->field(__('E-Mail'), $m->email),
                'phone' => $this->field(__('Telefon'), $m->phone),
                'kind' => $this->field(__('Mitgliedsart'), $m->kind),
                'joined_on' => $this->field(__('Eintritt'), $this->date($m->joined_on)),
                'left_on' => $this->field(__('Austritt'), $this->date($m->left_on)),
                'notes' => $this->field(__('Notizen'), $m->notes),
            ],
            'lists' => [
                __('Erziehungsberechtigte') => array_values($m->guardians()->orderBy('id')->get()->map(fn (ClubGuardian $g): array => [
                    'name' => $this->str($g->name),
                    'email' => $this->str($g->email),
                    'phone' => $this->str($g->phone),
                    'valid_from' => $this->date($g->valid_from),
                    'valid_to' => $this->date($g->valid_to),
                    'revoked_at' => $this->str($g->revoked_at),
                ])->all()),
            ],
        ];
    }
}
