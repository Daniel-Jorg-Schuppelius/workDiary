<?php
/*
 * Created on   : Thu Nov 12 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : LeadIntakeConversionTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace Tests\Feature\Sales;

use App\Enums\Sales\{LeadIntakeDecision, LeadIntakeStage, LeadStatus};
use App\Enums\User\Permission;
use App\Models\Classification\Classification;
use App\Models\Customer\{Customer, CustomerDeadlineProfile};
use App\Models\Platform\User;
use App\Models\Sales\LeadIntake;
use App\Services\Customer\Deadlines\CustomerDeadlineRuleSets;
use App\Services\Customer\Deadlines\RuleSets\HomeCareDeadlineRuleSet;
use App\Services\Sales\Intake\Install\IntakePackageInstallStep;
use App\Services\Sales\Intake\{IntakeKeys, IntakePackages, LeadIntakeService};
use App\Services\Sales\LeadService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use RuntimeException;
use Spatie\Permission\PermissionRegistrar;
use Tests\Concerns\WithOrganization;
use Tests\Feature\Sales\Concerns\BuildsLeadIntakes;
use Tests\TestCase;

/**
 * Umwandlung mit Aufnahmeakte (Feature 200, MVP-1641/1642/1675) —
 * Abnahmekriterien 1, 2, 5 und 6 der Feature-Seite sowie der Regressionstest
 * „ohne aktives Paket bleibt die Umwandlung wie heute“.
 */
final class LeadIntakeConversionTest extends TestCase {
    use BuildsLeadIntakes;
    use RefreshDatabase;
    use WithOrganization;

    private User $admin;

    protected function setUp(): void {
        parent::setUp();
        Carbon::setTestNow('2026-03-02 09:00:00');
        $this->setUpOrganization();
        app(PermissionRegistrar::class)->setPermissionsTeamId($this->organization->id);
        $this->admin = $this->orgAdmin();
    }

    protected function tearDown(): void {
        Carbon::setTestNow();
        parent::tearDown();
    }

    /** Regression: ohne Prüfpaket bleibt die Umwandlung exakt wie vor Feature 200 — auch mit geöffneter Akte. */
    public function test_without_package_the_conversion_is_unchanged(): void {
        $lead = $this->makeLead();
        $this->actingAs($this->admin)->post(route('leads.convert', $lead))->assertRedirect();

        $lead->refresh();
        $customer = Customer::query()->where('organization_id', $this->organization->id)->sole();
        $this->assertSame(LeadStatus::Converted, $lead->status);
        $this->assertSame($customer->id, $lead->customer_id);
        $this->assertSame('Beispiel Werkstatt GmbH (Test)', $customer->name);
        $this->assertSame('erika.beispiel@example.com', $customer->email);
        $this->assertSame(0, LeadIntake::query()->count());
        $this->assertSame([], app(LeadService::class)->conversionNotices());

        // Geöffnete Akte ohne Paket mit Entscheidungspflicht sperrt nicht.
        $second = $this->makeLead(['company' => 'Zweite Probe KG (Test)', 'email' => 'zweite@example.com']);
        $intake = $this->openIntake($second, $this->admin);
        $this->assertFalse($intake->requiresDecision());
        $this->actingAs($this->admin)->post(route('leads.convert', $second))->assertSessionHasNoErrors()->assertSessionMissing('error');
        $this->assertSame(LeadStatus::Converted, $second->refresh()->status);
        $this->assertSame(LeadIntakeStage::Inquiry, $intake->refresh()->stage);
    }

    /** Abnahme 1: mit aktivem GwG-Paket keine Umwandlung ohne Annahme. */
    public function test_with_gwg_the_lead_converts_only_after_acceptance(): void {
        $this->enableGwg();
        $lead = $this->makeLead();

        $this->actingAs($this->admin)->post(route('leads.convert', $lead))->assertSessionHas('error', __('lead_intake.block.no_intake'));
        $this->assertSame(LeadStatus::Qualified, $lead->refresh()->status);

        $intake = $this->openIntake($lead, $this->admin);
        $this->assertTrue($intake->requiresDecision());
        $this->actingAs($this->admin)->post(route('leads.convert', $lead))->assertSessionHas('error', __('lead_intake.block.not_accepted'));
        // Auch „Angebot aus dem Lead“ wandelt nicht vorbei an der Akte um.
        $this->actingAs($this->admin)->post(route('leads.quote', $lead))->assertSessionHas('error');

        $this->fill($intake, $this->gwgValues());
        $this->actingAs($this->admin)->post(route('leads.intakes.decide', [$lead, $intake]), ['decision' => 'accepted', 'reason' => 'Identifizierung vollständig (Test).'])
            ->assertSessionHasNoErrors();
        $this->assertSame(LeadIntakeStage::Setup, $intake->refresh()->stage);
        $this->assertNotNull($intake->protocol_id, 'Die Annahme legt den GwG-Vermerk an.');
        $this->assertSame('2027-03-02', $intake->refresh_due_on?->toDateString(), 'Vorgabe: jährlich.');

        $this->actingAs($this->admin)->post(route('leads.convert', $lead))->assertRedirect();
        $intake->refresh();
        $this->assertSame(LeadStatus::Converted, $lead->refresh()->status);
        $this->assertSame(LeadIntakeStage::Completed, $intake->stage);
        $this->assertSame($lead->customer_id, $intake->customer_id);
    }

    /** Abnahme 2: „hoch“ verlangt eine Begründung und setzt die Aktualisierung nach der Einstellung. */
    public function test_high_risk_needs_a_reason_and_uses_the_configured_refresh(): void {
        $this->enableGwg();
        app(IntakePackages::class)->saveRiskRefreshMonths($this->organization, ['low' => 24, 'normal' => 12, 'high' => 6]);
        $lead = $this->makeLead();
        $intake = $this->fill($this->openIntake($lead, $this->admin), $this->gwgValues(['risk_level' => 'high']));

        try {
            app(LeadIntakeService::class)->decide($intake, LeadIntakeDecision::Accepted, 'Annahme (Test).', $this->admin);
            $this->fail('Ohne Begründung der Risikostufe darf nicht angenommen werden.');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey(IntakeKeys::package('gwg', 'risk_reason'), $e->errors());
        }

        $intake = $this->fill($intake, [IntakeKeys::package('gwg', 'risk_reason') => 'Bargeldintensives Geschäft (Test).']);
        app(LeadIntakeService::class)->decide($intake, LeadIntakeDecision::Accepted, 'Annahme (Test).', $this->admin);
        $this->assertSame('2026-09-02', $intake->refresh()->refresh_due_on?->toDateString());
    }

    /** § 15 Abs. 3 Nr. 1 GwG: politisch exponierte Person nur mit Risikostufe hoch; mit Ausweis geprüft nur mit Kopie. */
    public function test_gwg_package_rules_before_acceptance(): void {
        $this->enableGwg();
        $lead = $this->makeLead();
        $intake = $this->fill($this->openIntake($lead, $this->admin), $this->gwgValues([
            'pep_status' => 'pep',
            'pep_details' => 'Mitglied eines Landtags (erfunden)',
            'verification_method' => 'id_document',
            'id_document_kind' => 'Personalausweis',
            'id_document_number' => 'TEST-0001',
            'id_issuer' => 'Stadt Musterstadt',
        ]));

        $errors = app(LeadIntakeService::class)->acceptanceErrors($intake);
        $this->assertSame(__('lead_intake.gwg.error.pep_requires_high'), $errors[IntakeKeys::package('gwg', 'risk_level')] ?? null);
        $this->assertSame(__('lead_intake.gwg.error.copy_missing'), $errors[IntakeKeys::document('gwg', 'id_copy')] ?? null);
    }

    /** Zweiter Blick: die zweite Person bestätigt, nie die, die angenommen hat. */
    public function test_second_review_requires_another_person(): void {
        $this->enableGwg();
        app(IntakePackages::class)->setSecondReview($this->organization, true);
        $partner = $this->userWithRole('geschaeftsfuehrung');
        $lead = $this->makeLead();
        $intake = $this->fill($this->openIntake($lead, $this->admin), $this->gwgValues());

        app(LeadIntakeService::class)->decide($intake, LeadIntakeDecision::Accepted, 'Annahme (Test).', $this->admin);
        $intake->refresh();
        $this->assertTrue($intake->awaitsSecondReview());
        $this->assertSame(__('lead_intake.block.second_review'), app(\App\Services\Sales\Intake\LeadIntakeConversionCheck::class)->blockingReason($lead));

        $this->actingAs($this->admin)->post(route('leads.intakes.second-review', [$lead, $intake]), ['action' => 'confirm'])
            ->assertSessionHas('error', __('lead_intake.error.same_person'));
        $this->assertTrue($partner->can(Permission::LeadIntakeDecide->value));
        $this->actingAs($partner)->post(route('leads.intakes.second-review', [$lead, $intake]), ['action' => 'confirm'])->assertSessionHasNoErrors();
        $intake->refresh();
        $this->assertSame(LeadIntakeStage::Setup, $intake->stage);
        $this->assertSame($partner->id, $intake->second_reviewer_user_id);
    }

    /** Abnahme 5: Pflege-Erstaufnahme setzt Pflegegrad und Leistungsbezug als Merkmale des Pflege-Regelsatzes. */
    public function test_care_admission_sets_care_grade_and_benefit_on_the_new_customer(): void {
        foreach ([1, 2, 3, 4, 5] as $grade) {
            Classification::query()->create(['organization_id' => $this->organization->id, 'domain' => 'pflegegrad', 'code' => 'pg' . $grade, 'label' => 'Pflegegrad ' . $grade]);
        }
        app(CustomerDeadlineRuleSets::class)->enable($this->organization, [HomeCareDeadlineRuleSet::KEY]);
        $result = app(IntakePackageInstallStep::class)->install($this->organization->refresh(), [['code' => 'care_admission', 'enabled' => true]], $this->admin);
        $this->assertSame(1, $result['created']);

        $lead = $this->makeLead(['company' => null, 'contact_name' => 'Hanna Probe (Test)']);
        $intake = $this->openIntake($lead, $this->admin);
        $this->assertTrue($intake->requiresDecision());
        $this->assertTrue($intake->fullSchema()->has('rs_home_care__quarterly_requested'));
        $this->assertFalse($intake->fullSchema()->has('rs_home_care__benefit'), 'Leistungsbezug erhebt das Paket selbst.');
        $intake = $this->fill($intake, [
            'request' => 'Grundpflege morgens (Test)',
            'care_admission__care_grade' => 'pg3',
            'care_admission__benefit' => 'care_allowance',
            'care_admission__emergency_contacts' => 'Angehörige, erfunden',
        ]);
        $this->assertSame('pg3', $intake->sensitive_values['care_admission__care_grade'] ?? null, 'Gesundheitsangaben liegen verschlüsselt.');
        $this->assertNull($intake->form?->values->get('care_admission__care_grade'));

        app(LeadIntakeService::class)->decide($intake, LeadIntakeDecision::Accepted, 'Kapazität vorhanden (Test).', $this->admin);
        $customer = app(LeadService::class)->convert($lead->refresh(), $this->admin);

        $this->assertSame(['pg3'], $customer->classifications()->where('domain', 'pflegegrad')->pluck('code')->all());
        $profile = CustomerDeadlineProfile::query()->where('customer_id', $customer->id)->where('rule_set', HomeCareDeadlineRuleSet::KEY)->sole();
        $this->assertSame('care_allowance', $profile->traits?->values->get('benefit'));
        $this->assertSame(3, app(HomeCareDeadlineRuleSet::class)->careGrade($customer));
    }

    /** Abnahme 6: formal ungültige USt-IdNr. wird am Feld gemeldet; Übernahme als Vorschlag an den Kunden. */
    public function test_b2b_vat_id_is_checked_at_the_field_and_suggested_to_the_customer(): void {
        app(IntakePackageInstallStep::class)->install($this->organization, [['code' => 'b2b_new_customer', 'enabled' => false]], $this->admin);
        $this->assertSame([], app(IntakePackages::class)->enabledKeys($this->organization->refresh()), 'B2B ist im Profil nur einschaltbar.');
        app(IntakePackages::class)->enable($this->organization, ['b2b_new_customer']);

        $clerk = $this->userWithRole('buchhaltung');
        $lead = $this->makeLead();
        $intake = $this->openIntake($lead, $clerk);
        $this->assertFalse($intake->requiresDecision());
        $url = static fn (string $field): string => route('field-documents.autosave', ['type' => $intake->getMorphClass(), 'document' => $intake->sqid, 'field' => $field]);

        $this->actingAs($clerk)->patchJson($url('b2b_new_customer__vat_id'), ['version' => 1, 'value' => 'DE999999990'])
            ->assertStatus(422)
            ->assertJsonPath('errors.value.0', __('lead_intake.vat_id.invalid'));
        $this->actingAs($clerk)->patchJson($url('b2b_new_customer__vat_id'), ['version' => 1, 'value' => 'DE 999 999 995'])->assertOk()->assertJsonPath('version', 2);
        $this->actingAs($clerk)->patchJson($url('b2b_new_customer__payment_terms_days'), ['version' => 2, 'value' => '21'])->assertOk();
        $this->assertSame('DE999999995', $intake->refresh()->fieldDocumentValues()->get('b2b_new_customer__vat_id'));

        $this->fill($intake, ['request' => 'IT-Betreuung (Test)']);
        app(LeadIntakeService::class)->decide($intake->refresh(), LeadIntakeDecision::Accepted, 'Bonität ausreichend (Test).', $this->admin);
        $customer = app(LeadService::class)->convert($lead->refresh(), $this->admin);
        $this->assertSame('DE999999995', (string) $customer->refresh()->vat_id);
        $this->assertSame(21, $customer->payment_terms_days);
    }

    public function test_rejected_intake_blocks_the_conversion(): void {
        $this->enableGwg();
        $lead = $this->makeLead();
        $intake = $this->openIntake($lead, $this->admin);
        app(LeadIntakeService::class)->decide($intake, LeadIntakeDecision::Rejected, 'Zweck der Geschäftsbeziehung unklar (Test).', $this->admin);

        $this->assertSame(LeadIntakeStage::Rejected, $intake->refresh()->stage);
        $this->expectException(RuntimeException::class);
        app(LeadService::class)->convert($lead->refresh(), $this->admin);
    }
}
