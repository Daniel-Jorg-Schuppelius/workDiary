<?php
/*
 * Created on   : Thu Nov 12 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : LeadIntakeDemoBlock.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Sales\Intake\Demo;

use App\Enums\Sales\{IntakeRiskLevel, LeadIntakeStage};
use App\Models\Platform\Organization;
use App\Models\Sales\{Lead, LeadIntake};
use App\Services\Demo\Contracts\{DemoBlock, DemoSeedContext};
use App\Services\Sales\Intake\{IntakeKeys, LeadIntakeService};
use App\Services\Sales\Intake\Packages\GwgIntakeCheckPackage;
use Illuminate\Support\Carbon;

/**
 * Aufnahmeakte zur Vorführung (Feature 200): am Demo-Lead „Kanzlei Weber &
 * Partner (Demo)“ eine Akte in der Stufe „Unterlagen“ mit Gesprächsbogen —
 * und, wenn das GwG-Paket eingeschaltet ist (Profil Steuerberatung), mit
 * erfundenen Angaben zu einer juristischen Person (Registerdaten, keine
 * Ausweisdaten, keine Gesundheitsangaben). Läuft nach dem Lead-Block.
 */
final class LeadIntakeDemoBlock implements DemoBlock {
    public const string DEMO_LEAD = 'Kanzlei Weber & Partner (Demo)';

    public function __construct(private readonly LeadIntakeService $intakes) {}

    public function supports(DemoSeedContext $context): bool {
        return $context->moduleActive('module.vertrieb');
    }

    public function seed(DemoSeedContext $context): array {
        $lead = Lead::query()->withoutGlobalScopes()
            ->where('organization_id', $context->organization->id)
            ->where('company', self::DEMO_LEAD)
            ->first();
        if (! $lead instanceof Lead || $this->intakes->current($lead) !== null) {
            return ['lead_intakes' => 0];
        }

        $intake = $this->intakes->open($lead, $context->actor);
        $values = [
            'request' => (string) __('lead_intake.demo.request'),
            'start_on' => Carbon::today()->addMonth()->startOfMonth()->toDateString(),
            'interview_notes' => (string) __('lead_intake.demo.notes'),
        ];
        $schema = $intake->fullSchema();
        if ($schema->has(IntakeKeys::package(GwgIntakeCheckPackage::KEY, 'party_kind'))) {
            foreach ([
                'party_kind' => 'legal',
                'company_name' => self::DEMO_LEAD,
                'legal_form' => 'Partnerschaftsgesellschaft mbB',
                'register_number' => 'PR 0000 (Demo)',
                'seat_address' => 'Musterstraße 1, 12345 Musterstadt',
                'representatives' => 'Jonas Weber (Demo), Mara Beispiel (Demo)',
                'verification_method' => 'own_register_check',
                'verified_on' => Carbon::today()->toDateString(),
                'beneficial_owners' => 'Jonas Weber (Demo), 60 %',
                'transparency_register_checked' => true,
                'transparency_register_on' => Carbon::today()->toDateString(),
                'pep_status' => 'no',
                'purpose' => (string) __('lead_intake.demo.purpose'),
                'suspicion_checked' => true,
                'risk_level' => IntakeRiskLevel::Normal->value,
            ] as $field => $value) {
                $values[IntakeKeys::package(GwgIntakeCheckPackage::KEY, $field)] = $value;
            }
        }
        $intake->fillValues($intake->fieldDocumentValues()->with(array_filter($values, static fn (string $key): bool => $schema->has($key), ARRAY_FILTER_USE_KEY)));
        $intake->forceFill(['stage' => LeadIntakeStage::Documents, 'version' => $intake->version + 1])->save();

        return ['lead_intakes' => 1];
    }

    public function purge(Organization $organization): void {
        LeadIntake::query()->withoutGlobalScopes()->where('organization_id', $organization->id)->orderByDesc('id')->get()->each->delete();
    }
}
