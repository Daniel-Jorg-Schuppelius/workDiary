<?php
/*
 * Created on   : Mon Aug 24 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : PortalUserMasterDataSection.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Privacy\SubjectData;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;

/**
 * Stammdaten eines Kundenportal-Kontos (users.customer_id, MVP-510):
 * bewusst OHNE HR-Felder — ein Portal-Nutzer hat keine Beschäftigungsdaten,
 * und die Auskunft darf nicht mehr offenlegen als zur Art gehört.
 */
class PortalUserMasterDataSection extends AbstractSubjectSection {
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
            'email' => $this->field(__('E-Mail'), $u->email),
            'phone' => $this->field(__('Telefon'), $u->phone),
            'mobile' => $this->field(__('Mobil'), $u->mobile),
            'customer' => $this->field(__('Kundenzuordnung'), $u->customer_id !== null
                ? \App\Models\Customer::query()->withoutGlobalScopes()
                    ->where('organization_id', $u->organization_id)
                    ->whereKey($u->customer_id)
                    ->value('name')
                : null),
            'portal_invited_at' => $this->field(__('Portal-Einladung am'), $u->portal_invited_at),
            'deactivated_at' => $this->field(__('Deaktiviert am'), $u->deactivated_at),
        ]];
    }
}
