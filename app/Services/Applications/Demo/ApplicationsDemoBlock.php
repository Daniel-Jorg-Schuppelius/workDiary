<?php
/*
 * Created on   : Thu Sep 24 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ApplicationsDemoBlock.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Applications\Demo;

use App\Models\Customer\Customer;
use App\Models\Platform\{Organization, User};
use App\Services\Demo\Contracts\{DemoBlock, DemoSeedContext};

/** Bewerbungs- und Ausschreibungs-Vorführung. Aus dem Demo-Showcase gelöst (Welle 4.1); Aufräumen übernimmt der generische Demo-Reset. */
final class ApplicationsDemoBlock implements DemoBlock {
    private DemoSeedContext $context;

    public function supports(DemoSeedContext $context): bool {
        return true;
    }

    public function seed(DemoSeedContext $context): array {
        $this->context = $context;
        $actor = $context->users->first();

        return [
            'applications' => $context->mainCustomer === null ? 0 : $this->seedApplications($context->organization, $context->mainCustomer, $actor),
        ];
    }

    public function purge(Organization $organization): void {}

    private function moduleActive(string $code): bool {
        return $this->context->moduleActive($code);
    }

    /**
     * Demo Feature 068: Ausschreibung (Go → Anforderung erledigt →
     * Einreichung → gewonnen) + Personalbewerbung (Gespräch → Bewertung →
     * Zusage → Mitarbeiter-Entwurf). Robust: Fehler brechen den Seed nicht.
     */
    private function seedApplications(Organization $organization, Customer $customer, ?User $actor): int {
        if (! $this->moduleActive('module.applications')) {
            return 0;
        }
        if ($actor === null) {
            return 0;
        }

        try {
            $tenders = app(\App\Services\Applications\TenderService::class);
            $opportunity = \App\Models\Applications\ApplicationOpportunity::query()->create([
                'organization_id' => $organization->id,
                'title' => (string) __('Rahmenvertrag Wartung Bürokomplex (Demo)'),
                'kind' => 'framework',
                'source' => 'Vergabeportal (Demo)',
                'customer_id' => $customer->id,
                'status' => 'in_progress',
                'submission_deadline' => \Carbon\Carbon::now()->addDays(14)->toDateString(),
                'estimated_value' => '48000.00',
                'probability' => 60,
                'responsible_user_id' => $actor->id,
                'created_by' => $actor->id,
            ]);
            $opportunity->requirements()->create([
                'organization_id' => $organization->id,
                'label' => (string) __('Referenzliste vergleichbarer Objekte'),
                'kind' => 'proof',
                'required' => true,
                'status' => 'done',
                'position' => 1,
            ]);
            $tenders->decideGo($opportunity, 'go', (string) __('Passt zur Auslastung im Winterhalbjahr.'), $actor);
            $tenders->submit($opportunity->refresh(), 'portal', null, $actor);
            $tenders->decide($opportunity->refresh(), 'won', null, $actor);

            $recruiting = app(\App\Services\Applications\RecruitingService::class);
            $requisition = \App\Models\Applications\JobRequisition::query()->create([
                'organization_id' => $organization->id,
                'title' => (string) __('Servicetechniker:in (Demo)'),
                'employment_type' => 'full_time',
                'status' => 'open',
                'responsible_user_id' => $actor->id,
                'created_by' => $actor->id,
            ]);
            ['application' => $application] = $recruiting->intake([
                'job_requisition_id' => $requisition->id,
                'candidate_name' => 'Kim Beispiel',
                'email' => 'kim.beispiel@example.test',
                'source' => 'website',
            ], $actor);
            $application->interviews()->create([
                'organization_id' => $organization->id,
                'scheduled_at' => \Carbon\Carbon::now()->subDays(3),
                'mode' => 'onsite',
                'interviewer_id' => $actor->id,
                'status' => 'done',
                'rating' => 5,
            ]);
            $recruiting->decide($application->refresh(), 'accepted', null, $actor);
            $recruiting->createEmployeeDraft($application->refresh(), $actor, [(string) __('Elektrofachkraft')]);

            return 2; // 1 Ausschreibung + 1 Bewerbungskette
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::info('Demo-Seeder: Bewerbungs-Demo übersprungen: ' . $e->getMessage());

            return 0;
        }
    }
}
