<?php
/*
 * Created on   : Thu Nov 12 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : LeadIntakePrivacyTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace Tests\Feature\Sales;

use App\Enums\Privacy\RetentionProposalStatus;
use App\Enums\Sales\LeadIntakeDecision;
use App\Enums\User\Permission;
use App\Http\Controllers\Attachments\AttachmentController;
use App\Models\Attachments\Attachment;
use App\Models\Platform\User;
use App\Models\Privacy\RetentionProposal;
use App\Models\Sales\LeadIntake;
use App\Services\Privacy\SubjectData\LeadIntakeSection;
use App\Services\Retention\{LegalHoldService, RetentionScanService};
use App\Services\Sales\Intake\Dto\IntakeRetentionRule;
use App\Services\Sales\Intake\{IntakeKeys, LeadIntakeRetention, LeadIntakeService, LeadIntakeVisibility};
use App\Services\Sales\LeadService;
use Carbon\{Carbon, CarbonImmutable};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Spatie\Permission\PermissionRegistrar;
use Tests\Concerns\WithOrganization;
use Tests\Feature\Sales\Concerns\BuildsLeadIntakes;
use Tests\TestCase;

/**
 * Datenschutz der Aufnahmeakte (Feature 200, MVP-1642/1643) — Abnahme 3
 * (Aufbewahrung über die Anonymisierung hinweg, Löschung danach) und
 * Abnahme 4 (Ausweiskopie und Gesundheitsangaben nur mit eigenem Recht, auch
 * in Speichern je Feld, Anhang, PDF-Daten und Auskunft).
 */
final class LeadIntakePrivacyTest extends TestCase {
    use BuildsLeadIntakes;
    use RefreshDatabase;
    use WithOrganization;

    private User $admin;

    private User $clerk;

    protected function setUp(): void {
        parent::setUp();
        Carbon::setTestNow('2026-03-02 09:00:00');
        Storage::fake('local');
        $this->setUpOrganization();
        app(PermissionRegistrar::class)->setPermissionsTeamId($this->organization->id);
        $this->admin = $this->orgAdmin();
        $this->clerk = $this->userWithRole('buchhaltung');
        $this->enableGwg();
    }

    protected function tearDown(): void {
        Carbon::setTestNow();
        parent::tearDown();
    }

    /** § 8 Abs. 4 GwG: fünf Jahre ab Schluss des Kalenderjahres. */
    public function test_retention_rule_counts_from_the_end_of_the_year(): void {
        $rule = new IntakeRetentionRule(5, '§ 8 Abs. 4 GwG');
        $this->assertSame('2031-12-31', $rule->until(CarbonImmutable::parse('2026-03-15'))->toDateString());
        $this->assertSame('2031-12-31', $rule->until(CarbonImmutable::parse('2026-12-31'))->toDateString());
        $this->assertNull(IntakeRetentionRule::fromArray(['years' => 0]));
    }

    /** Abnahme 3: Anonymisierung lässt die GwG-Angaben bis zum Fristende bestehen; der Aufbewahrungslauf löscht sie danach. */
    public function test_gwg_file_survives_anonymisation_until_the_period_ends(): void {
        $lead = $this->makeLead();
        $intake = $this->fill($this->openIntake($lead, $this->admin), $this->gwgValues());
        app(LeadIntakeService::class)->decide($intake, LeadIntakeDecision::Accepted, 'Annahme (Test).', $this->admin);
        $customer = app(LeadService::class)->convert($lead->refresh(), $this->admin);
        $intake->refresh();
        $this->assertNull($intake->retention_until, 'Solange die Geschäftsbeziehung besteht, läuft keine Frist.');

        // Ende der Geschäftsbeziehung am Kunden → Frist bis 31.12.2031.
        $this->actingAs($this->admin)->post(route('leads.intakes.relationship.update', $customer), ['relationship_ended_on' => '2026-02-28'])->assertSessionHasNoErrors();
        $this->assertSame('2026-02-28', $customer->refresh()->relationship_ended_on?->toDateString());
        $this->assertSame('2031-12-31', $intake->refresh()->retention_until?->toDateString());

        // Anonymisierung (Lead) und Archivieren (Kunde) lassen die Akte stehen.
        app(LeadService::class)->anonymize($lead->refresh());
        $customer->forceFill(['archived_at' => now()])->save();
        $this->assertTrue(LeadIntake::query()->whereKey($intake->id)->exists());
        $this->assertSame('Erika', $intake->refresh()->fieldDocumentValues()->get('gwg__first_name'));

        $scan = app(RetentionScanService::class);
        $scan->scan($this->organization);
        $this->assertFalse(RetentionProposal::query()->where('area', 'lead_intakes')->exists());

        Carbon::setTestNow('2032-01-02 09:00:00');
        $scan->scan($this->organization->refresh());
        $proposal = RetentionProposal::query()->where('area', 'lead_intakes')->sole();
        $this->assertSame($intake->getMorphClass(), $proposal->subject_type);
        $scan->approve($proposal, $this->admin);
        $scan->purge($proposal->refresh(), $this->admin);
        $this->assertSame(RetentionProposalStatus::Purged, $proposal->refresh()->status);
        $this->assertFalse(LeadIntake::query()->whereKey($intake->id)->exists());
    }

    /** Ein Legal Hold am Kunden sperrt den Löschvorschlag; abgelehnte Aufnahmen laufen ab der Ablehnung. */
    public function test_legal_hold_and_rejected_files(): void {
        $lead = $this->makeLead();
        $intake = $this->fill($this->openIntake($lead, $this->admin), $this->gwgValues());
        app(LeadIntakeService::class)->decide($intake, LeadIntakeDecision::Rejected, 'Wirtschaftlich Berechtigte nicht feststellbar (Test).', $this->admin);
        $this->assertSame('2031-12-31', $intake->refresh()->retention_until?->toDateString());

        app(LeadService::class)->anonymize($lead->refresh());
        $this->assertTrue(LeadIntake::query()->whereKey($intake->id)->exists(), 'Abgelehnte GwG-Akte bleibt bis zum Fristende.');

        $customer = \App\Models\Customer\Customer::factory()->create(['organization_id' => $this->organization->id]);
        $intake->forceFill(['customer_id' => $customer->id])->saveQuietly();
        app(LegalHoldService::class)->place($customer, 'Betriebsprüfung (Test)', null, $this->admin);
        Carbon::setTestNow('2032-01-02 09:00:00');
        $result = app(RetentionScanService::class)->scan($this->organization->refresh());
        $this->assertFalse(RetentionProposal::query()->where('area', 'lead_intakes')->exists());
        $this->assertGreaterThanOrEqual(1, $result['exempt']);
    }

    /** Ohne Paket mit Aufbewahrung geht die Akte mit der Anonymisierung des Leads. */
    public function test_file_without_retention_goes_with_the_lead(): void {
        app(\App\Services\Sales\Intake\IntakePackages::class)->setEnabled($this->organization, []);
        $lead = $this->makeLead();
        $intake = $this->fill($this->openIntake($lead, $this->admin), ['request' => 'Nur Gespräch (Test)']);
        $this->assertFalse(app(LeadIntakeRetention::class)->retains($intake));

        app(LeadService::class)->anonymize($lead->refresh());
        $this->assertFalse(LeadIntake::query()->whereKey($intake->id)->exists());
    }

    /** Abnahme 4: Ausweisangaben und -kopie nur mit eigenem Recht — Bogen, Speichern je Feld, Anhang, PDF-Daten, Auskunft. */
    public function test_identity_details_need_their_own_permission(): void {
        $lead = $this->makeLead();
        $intake = $this->fill($this->openIntake($lead, $this->admin), $this->gwgValues([
            'verification_method' => 'id_document',
            'id_document_kind' => 'Personalausweis',
            'id_document_number' => 'TEST-4711',
            'id_issuer' => 'Stadt Musterstadt',
        ]));
        $this->assertSame('TEST-4711', $intake->sensitive_values['gwg__id_document_number'] ?? null);
        $this->assertStringNotContainsString('TEST-4711', (string) \Illuminate\Support\Facades\DB::table('lead_intakes')->where('id', $intake->id)->value('sensitive_values'), 'verschlüsselt gespeichert');
        $this->assertStringNotContainsString('TEST-4711', (string) \Illuminate\Support\Facades\DB::table('lead_intakes')->where('id', $intake->id)->value('form'));

        // Kopie über die Akte ablegen (mit Recht).
        $this->actingAs($this->admin)->post(route('leads.intakes.document', [$lead, $intake]), [
            'document' => IntakeKeys::document('gwg', 'id_copy'),
            'file' => UploadedFile::fake()->image('ausweis.jpg'),
        ])->assertSessionHasNoErrors();
        $copy = Attachment::query()->where('meta_type', IntakeKeys::ATTACHMENT_META . IntakeKeys::document('gwg', 'id_copy'))->sole();
        $this->assertTrue($intake->refresh()->fieldDocumentValues()->get('gwg__doc_id_copy'));

        // Ohne Recht: Bogen zeigt die Angaben nicht, Speichern je Feld und Upload verweigert, Kopie nicht abrufbar.
        $this->actingAs($this->clerk)->get(route('leads.intakes.show', [$lead, $intake]))
            ->assertOk()
            ->assertDontSee('TEST-4711')
            ->assertDontSee('ausweis.jpg')
            ->assertSee(__('lead_intake.title'));
        $this->actingAs($this->clerk)->patchJson(route('field-documents.autosave', ['type' => $intake->getMorphClass(), 'document' => $intake->sqid, 'field' => 'gwg__id_document_number']), ['version' => $intake->version, 'value' => 'X'])
            ->assertStatus(422);
        $this->actingAs($this->clerk)->post(route('leads.intakes.document', [$lead, $intake]), [
            'document' => IntakeKeys::document('gwg', 'id_copy'),
            'file' => UploadedFile::fake()->image('zweite.jpg'),
        ])->assertForbidden();
        $this->actingAs($this->clerk)->get(AttachmentController::downloadUrl($copy))->assertForbidden();
        $this->assertArrayNotHasKey('gwg__id_document_number', app(LeadIntakeVisibility::class)->valuesFor($intake, $this->clerk)->toArray());

        // Auskunft: ohne Recht nur der Hinweis, mit Recht der Wert.
        $this->actingAs($this->clerk);
        $rows = collect((new LeadIntakeSection)->build($lead)['lists'] ?? [])->flatten(1);
        $this->assertFalse($rows->contains(static fn (array $row): bool => in_array('TEST-4711', $row, true)));
        $this->assertTrue($rows->contains(static fn (array $row): bool => in_array(__('lead_intake.dsar.restricted', ['right' => Permission::LeadIntakeIdentity->label()]), $row, true)));

        // Mit eigenem Recht: sichtbar und abrufbar.
        $this->clerk->givePermissionTo(Permission::LeadIntakeIdentity->value);
        $this->clerk->unsetRelation('permissions');
        app(PermissionRegistrar::class)->forgetCachedPermissions();
        $this->actingAs($this->clerk)->get(route('leads.intakes.show', [$lead, $intake]))->assertOk()->assertSee('TEST-4711')->assertSee('ausweis.jpg');
        $this->actingAs($this->clerk)->get(AttachmentController::downloadUrl($copy))->assertOk();
        $rows = collect((new LeadIntakeSection)->build($lead)['lists'] ?? [])->flatten(1);
        $this->assertTrue($rows->contains(static fn (array $row): bool => in_array('TEST-4711', $row, true)));
        $this->assertTrue($rows->contains(static fn (array $row): bool => in_array(__('lead_intake.dsar.legal_basis_value', ['basis' => '§ 8 Abs. 4 GwG']), $row, true)));
    }

    /** Abnahme 4: Gesundheitsangaben der Pflege-Erstaufnahme nur mit eigenem Recht. */
    public function test_health_details_need_their_own_permission(): void {
        app(\App\Services\Sales\Intake\Install\IntakePackageInstallStep::class)->install($this->organization, [['code' => 'care_admission']], $this->admin);
        app(\App\Services\Sales\Intake\IntakePackages::class)->setEnabled($this->organization->refresh(), ['care_admission']);
        $lead = $this->makeLead(['company' => null, 'contact_name' => 'Hanna Probe (Test)']);
        $intake = $this->fill($this->openIntake($lead, $this->admin), [
            'request' => 'Grundpflege (Test)',
            'care_admission__family_doctor' => 'Praxis Erfunden',
            'care_admission__emergency_contacts' => 'Nachbarin (Test)',
        ]);

        $this->actingAs($this->clerk)->get(route('leads.intakes.show', [$lead, $intake]))
            ->assertOk()
            ->assertDontSee('Praxis Erfunden')
            ->assertSee('Nachbarin (Test)');

        $this->clerk->givePermissionTo(Permission::LeadIntakeHealth->value);
        $this->clerk->unsetRelation('permissions');
        app(PermissionRegistrar::class)->forgetCachedPermissions();
        $this->actingAs($this->clerk)->get(route('leads.intakes.show', [$lead, $intake]))->assertOk()->assertSee('Praxis Erfunden');
    }

    /** Die Ablage am Kunden bzw. Lead trägt nie enge Angaben. */
    public function test_archived_pdf_has_no_restricted_values(): void {
        $renderer = app(\App\Services\Sales\Intake\LeadIntakePdfRenderer::class);
        $lead = $this->makeLead();
        $intake = $this->fill($this->openIntake($lead, $this->admin), $this->gwgValues(['birth_place' => 'Geheimstadt']));

        $html = view('leads.intakes.pdf', [
            'intake' => $intake,
            'schema' => $intake->fullSchema(),
            'values' => app(LeadIntakeVisibility::class)->valuesFor($intake, null),
            'hidden' => app(LeadIntakeVisibility::class)->hiddenFor($intake, null),
            'reference' => $renderer->reference($intake),
            'pages' => 2,
        ])->render();
        $this->assertStringNotContainsString('Geheimstadt', $html);
        $this->assertStringContainsString('Erika', $html);
        $this->assertStringContainsString(__('lead_intake.pdf.restricted', ['kind' => \App\Enums\Sales\IntakeSensitivity::Identity->label()]), $html);

        $this->actingAs($this->admin)->post(route('leads.intakes.archive', [$lead, $intake]))->assertSessionHasNoErrors();
        $stored = Attachment::query()->where('attachable_type', $lead->getMorphClass())->where('attachable_id', $lead->id)->sole();
        $this->assertStringStartsWith('aufnahme-gwg-', $stored->original_name);
        $this->assertStringEndsWith('.pdf', $stored->original_name);
    }
}
