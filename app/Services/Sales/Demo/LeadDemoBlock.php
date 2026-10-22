<?php
/*
 * Created on   : Thu Oct 22 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : LeadDemoBlock.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Sales\Demo;

use App\Enums\Sales\{LeadSource, LeadStatus};
use App\Models\Platform\Organization;
use App\Models\Sales\Lead;
use App\Services\Demo\Contracts\{DemoBlock, DemoSeedContext};
use App\Services\Sales\LeadService;

/**
 * Lead-Pipeline zur Vorführung (MVP-1288): je Quelle ein Lead in einem
 * anderen Stand, einer davon konvertiert — Futter für Liste und Auswertung.
 */
final class LeadDemoBlock implements DemoBlock {
    public function __construct(private readonly LeadService $leads) {}

    public function supports(DemoSeedContext $context): bool {
        return $context->moduleActive('module.vertrieb');
    }

    public function seed(DemoSeedContext $context): array {
        $organization = $context->organization;
        $actor = $context->actor;

        $rows = [
            ['Musterbau Schmidt GmbH (Demo)', 'Anna', 'Schmidt', LeadSource::Referral, LeadStatus::Qualified],
            ['Kanzlei Weber & Partner (Demo)', 'Jonas', 'Weber', LeadSource::Web, LeadStatus::Contacted],
            ['Praxis am Markt (Demo)', 'Lea', 'Brandt', LeadSource::TradeFair, LeadStatus::New],
            ['Autohaus Krüger (Demo)', 'Tim', 'Krüger', LeadSource::Phone, LeadStatus::Qualified],
            ['Bäckerei Sonnenschein (Demo)', 'Eva', 'Sonne', LeadSource::Web, LeadStatus::Contacted],
        ];

        $count = 0;
        foreach ($rows as $i => [$company, $firstName, $lastName, $source, $status]) {
            $lead = Lead::query()->create([
                'organization_id' => $organization->id,
                'company' => $company,
                'contact_first_name' => $firstName,
                'contact_last_name' => $lastName,
                'email' => 'lead' . ($i + 1) . '.demo@example.com',
                'source' => $source->value,
                'interest' => (string) __('IT-Betreuung und Arbeitsplatz-Ausstattung'),
                'status' => LeadStatus::New->value,
                'responsible_user_id' => $actor->id,
                'created_by' => $actor->id,
            ]);
            if ($status !== LeadStatus::New) {
                $this->leads->transition($lead, LeadStatus::Contacted);
            }
            if ($status === LeadStatus::Qualified) {
                $this->leads->transition($lead, LeadStatus::Qualified);
            }
            $count++;
        }

        // Eine Konversion mit dem Hauptkunden, damit die Quote nicht bei null
        // steht — ohne zusätzlichen Kunden, die Blueprint-Zähler bleiben stabil.
        $won = Lead::query()->where('organization_id', $organization->id)->where('company', 'Autohaus Krüger (Demo)')->first();
        if ($won !== null && $context->mainCustomer !== null) {
            $this->leads->convert($won, $actor, $context->mainCustomer);
        }

        return ['leads' => $count];
    }

    public function purge(Organization $organization): void {
        Lead::query()->withoutGlobalScopes()->where('organization_id', $organization->id)->delete();
    }
}
