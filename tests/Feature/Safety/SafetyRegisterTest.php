<?php
/*
 * Created on   : Tue Aug 25 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : SafetyRegisterTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace Tests\Feature\Safety;

use App\Enums\Notification\{NotificationChannel, NotificationEvent};
use App\Enums\Safety\{HazardAssessmentStatus, InstructionSignatureMethod, MedicalCheckupKind};
use App\Enums\User\UserRole;
use App\Models\Notification\{NotificationDispatchLog, NotificationRule};
use App\Models\{Organization, User};
use App\Models\Safety\{HazardAssessment, MedicalCheckup, SafetyInstruction, SafetyInstructionParticipant};
use App\Services\Safety\{HazardAssessmentService, SafetyInstructionService};
use App\Support\Sqid;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\Concerns\WithOrganization;
use Tests\TestCase;

/**
 * Arbeitsschutz-Register (Feature 132, MVP-697): GBU-Lifecycle inkl.
 * Versionierung/Statusmaschine/Nummern, Unterweisung mit Teilnehmer-
 * Signatur + next_due, Vorsorge-Fälligkeit, Fristen-Scan mit Dedup,
 * Tenancy und Rechte.
 */
class SafetyRegisterTest extends TestCase {
    use RefreshDatabase;
    use WithOrganization;

    protected function setUp(): void {
        parent::setUp();
        $this->setUpOrganization();
    }

    private function lead(): User {
        return User::factory()->teamleitung()->create(['organization_id' => $this->organization->id]);
    }

    private function field(): User {
        return User::factory()->aussendienst()->create(['organization_id' => $this->organization->id]);
    }

    private function assessments(): HazardAssessmentService {
        return app(HazardAssessmentService::class);
    }

    private function instructions(): SafetyInstructionService {
        return app(SafetyInstructionService::class);
    }

    /** @param array<string, mixed> $attributes */
    private function approvedAssessment(User $lead, array $attributes = []): HazardAssessment {
        $assessment = $this->assessments()->create($this->organization, $lead, ['area' => 'Werkstatt'] + $attributes);
        $this->assessments()->addItem($assessment, ['hazard' => 'Rotierende Teile', 'severity_before' => 4, 'likelihood_before' => 3]);
        $this->assessments()->transition($assessment, HazardAssessmentStatus::InReview, $lead);

        return $this->assessments()->transition($assessment->refresh(), HazardAssessmentStatus::Approved, $lead);
    }

    // ── Rechte / Register-Seiten ─────────────────────────────────────────

    public function test_lead_can_view_all_three_registers(): void {
        $lead = $this->lead();

        $this->actingAs($lead)->get(route('safety.assessments.index'))->assertOk()->assertViewIs('safety.assessments.index');
        $this->actingAs($lead)->get(route('safety.instructions.index'))->assertOk()->assertViewIs('safety.instructions.index');
        $this->actingAs($lead)->get(route('safety.checkups.index'))->assertOk()->assertViewIs('safety.checkups.index');
    }

    public function test_field_staff_cannot_view_or_manage_registers(): void {
        $field = $this->field();

        $this->actingAs($field)->get(route('safety.assessments.index'))->assertForbidden();
        $this->actingAs($field)->get(route('safety.checkups.index'))->assertForbidden();
        $this->actingAs($field)->post(route('safety.assessments.store'), ['area' => 'Lager'])->assertForbidden();
    }

    // ── Gefährdungsbeurteilung ───────────────────────────────────────────

    public function test_assessment_no_runs_per_organization(): void {
        $lead = $this->lead();

        $first = $this->assessments()->create($this->organization, $lead, ['area' => 'Werkstatt']);
        $second = $this->assessments()->create($this->organization, $lead, ['area' => 'Lager']);

        $this->assertSame(1, $first->assessment_no);
        $this->assertSame(2, $second->assessment_no);
        $this->assertSame(1, $second->version);
        $this->assertSame(HazardAssessmentStatus::Draft, $second->status);
        $this->assertSame('GB-2 v1', $second->displayNo());
    }

    public function test_store_via_http_creates_draft_in_current_organization(): void {
        $lead = $this->lead();

        $this->actingAs($lead)
            ->post(route('safety.assessments.store'), [
                'area' => 'Halle 2',
                'activity' => 'Schweißen',
                'review_due_on' => now()->addYear()->toDateString(),
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('hazard_assessments', [
            'organization_id' => $this->organization->id,
            'area' => 'Halle 2',
            'status' => HazardAssessmentStatus::Draft->value,
            'created_by_user_id' => $lead->id,
            'assessment_no' => 1,
        ]);
    }

    public function test_item_risk_is_severity_times_likelihood(): void {
        $lead = $this->lead();
        $assessment = $this->assessments()->create($this->organization, $lead, ['area' => 'Werkstatt']);

        $item = $this->assessments()->addItem($assessment, [
            'hazard' => 'Lärm',
            'severity_before' => 4,
            'likelihood_before' => 5,
            'severity_after' => 2,
            'likelihood_after' => 2,
        ]);

        $this->assertSame(20, $item->risk_before);
        $this->assertSame(4, $item->risk_after);
        $this->assertSame(1, $item->position);

        $this->expectException(ValidationException::class);
        $this->assessments()->addItem($assessment, ['hazard' => 'X', 'severity_before' => 1, 'likelihood_before' => 1, 'severity_after' => 3]);
    }

    public function test_approve_requires_at_least_one_item(): void {
        $lead = $this->lead();
        $assessment = $this->assessments()->create($this->organization, $lead, ['area' => 'Werkstatt']);
        $this->assessments()->transition($assessment, HazardAssessmentStatus::InReview, $lead);

        $this->expectException(ValidationException::class);
        $this->assessments()->transition($assessment->refresh(), HazardAssessmentStatus::Approved, $lead);
    }

    public function test_invalid_transition_is_rejected(): void {
        $lead = $this->lead();
        $assessment = $this->assessments()->create($this->organization, $lead, ['area' => 'Werkstatt']);

        // draft → approved ist nicht erlaubt (erst in Prüfung).
        $this->expectException(ValidationException::class);
        $this->assessments()->transition($assessment, HazardAssessmentStatus::Approved, $lead);
    }

    public function test_approval_freezes_the_assessment(): void {
        $lead = $this->lead();
        $assessment = $this->approvedAssessment($lead);

        $this->assertSame(HazardAssessmentStatus::Approved, $assessment->status);
        $this->assertSame($lead->id, $assessment->approved_by_user_id);
        $this->assertNotNull($assessment->approved_at);

        try {
            $this->assessments()->update($assessment, ['area' => 'Geändert']);
            $this->fail('Freigegebener Stand durfte nicht geändert werden.');
        } catch (ValidationException) {
        }

        try {
            $this->assessments()->addItem($assessment, ['hazard' => 'Neu', 'severity_before' => 1, 'likelihood_before' => 1]);
            $this->fail('Freigegebener Stand durfte keine neue Position erhalten.');
        } catch (ValidationException) {
        }

        try {
            $assessment->items()->firstOrFail()->update(['hazard' => 'Direkt geändert']);
            $this->fail('Model-Guard: Position eines freigegebenen Standes durfte nicht geändert werden.');
        } catch (ValidationException) {
        }

        $this->expectException(ValidationException::class);
        $this->assessments()->delete($assessment);
    }

    public function test_new_version_copies_items_and_archives_the_original(): void {
        $lead = $this->lead();
        $original = $this->approvedAssessment($lead, ['review_due_on' => now()->addYear()->toDateString()]);

        $copy = $this->assessments()->newVersion($original, $lead);

        $this->assertSame($original->assessment_no, $copy->assessment_no);
        $this->assertSame(2, $copy->version);
        $this->assertSame($original->id, $copy->supersedes_id);
        $this->assertSame(HazardAssessmentStatus::Draft, $copy->status);
        $this->assertNull($copy->approved_at);
        $this->assertSame(1, $copy->items()->count());
        $this->assertSame(12, $copy->items()->firstOrFail()->risk_before);
        $this->assertSame(HazardAssessmentStatus::Archived, $original->refresh()->status);
        $this->assertSame('GB-1 v2', $copy->displayNo());

        // Folgeversion nur aus approved — der Entwurf v2 kann keine v3 abzweigen.
        $this->expectException(ValidationException::class);
        $this->assessments()->newVersion($copy, $lead);
    }

    public function test_assessment_detail_page_renders_items_and_version_chain(): void {
        $lead = $this->lead();
        $original = $this->approvedAssessment($lead);
        $copy = $this->assessments()->newVersion($original, $lead);

        $this->actingAs($lead)
            ->get(route('safety.assessments.show', $copy))
            ->assertOk()
            ->assertViewIs('safety.assessments.show')
            ->assertSee('GB-1 v2')
            ->assertSee('Rotierende Teile')
            ->assertSee(route('safety.assessments.show', $original), false);
    }

    public function test_new_version_via_http_redirects_to_the_copy(): void {
        $lead = $this->lead();
        $original = $this->approvedAssessment($lead);

        $this->actingAs($lead)
            ->post(route('safety.assessments.new-version', $original))
            ->assertRedirect();

        $this->assertDatabaseHas('hazard_assessments', [
            'assessment_no' => $original->assessment_no,
            'version' => 2,
            'supersedes_id' => $original->id,
            'status' => HazardAssessmentStatus::Draft->value,
        ]);
    }

    // ── Unterweisung ─────────────────────────────────────────────────────

    public function test_instruction_creates_participants_with_next_due(): void {
        $lead = $this->lead();
        $a = $this->field();
        $b = $this->field();

        $instruction = $this->instructions()->create($this->organization, $lead, [
            'topic' => 'Brandschutz',
            'held_on' => '2026-03-10',
            'repeat_interval_months' => 12,
        ], [$a->id, $b->id, $a->id]);

        $this->assertSame(1, $instruction->instruction_no);
        $this->assertSame('UW-1', $instruction->displayNo());
        $this->assertSame(2, $instruction->participants()->count());
        $this->assertSame('2027-03-10', $instruction->participants()->where('user_id', $a->id)->firstOrFail()->next_due_on?->toDateString());
        $this->assertSame($lead->id, $instruction->instructor_user_id);
    }

    public function test_participant_signs_own_row_via_http(): void {
        $lead = $this->lead();
        $participantUser = $this->field();
        $instruction = $this->instructions()->create($this->organization, $lead, [
            'topic' => 'PSA',
            'held_on' => now()->toDateString(),
            'repeat_interval_months' => 6,
        ], [$participantUser->id]);
        $participant = $instruction->participants()->firstOrFail();

        // Teilnehmer sieht die Nachweis-Ansicht, aber nicht das Register.
        $this->actingAs($participantUser)->get(route('safety.instructions.show', $instruction))->assertOk();
        $this->actingAs($participantUser)->get(route('safety.instructions.index'))->assertForbidden();

        $this->actingAs($participantUser)
            ->post(route('safety.instructions.participants.sign', [$instruction, $participant]))
            ->assertRedirect();

        $participant->refresh();
        $this->assertTrue($participant->isSigned());
        $this->assertSame($participantUser->name, $participant->signer_name);
        $this->assertSame(InstructionSignatureMethod::Confirmed, $participant->method);
        $this->assertNotNull($participant->ip);
        $this->assertSame(64, strlen((string) $participant->hash));

        // Zweite Bestätigung ist nicht möglich (Policy: bereits signiert).
        $this->actingAs($participantUser)
            ->post(route('safety.instructions.participants.sign', [$instruction, $participant]))
            ->assertForbidden();
    }

    public function test_nobody_signs_for_others_not_even_the_lead(): void {
        $lead = $this->lead();
        $participantUser = $this->field();
        $instruction = $this->instructions()->create($this->organization, $lead, [
            'topic' => 'PSA',
            'held_on' => now()->toDateString(),
        ], [$participantUser->id]);
        $participant = $instruction->participants()->firstOrFail();

        $this->actingAs($lead)
            ->post(route('safety.instructions.participants.sign', [$instruction, $participant]))
            ->assertForbidden();

        $this->expectException(ValidationException::class);
        $this->instructions()->sign($participant, $lead);
    }

    public function test_instruction_with_signed_proof_cannot_be_deleted_and_keeps_signed_rows_on_sync(): void {
        $lead = $this->lead();
        $signer = $this->field();
        $other = $this->field();
        $instruction = $this->instructions()->create($this->organization, $lead, [
            'topic' => 'Gefahrstoffe',
            'held_on' => now()->toDateString(),
        ], [$signer->id, $other->id]);
        $this->instructions()->sign($instruction->participants()->where('user_id', $signer->id)->firstOrFail(), $signer, ip: '10.0.0.1');

        // Abgleich ohne den Signierer: signierte Zeile bleibt, unsignierte fällt weg.
        $this->instructions()->update($instruction, [], [$other->id]);
        $this->assertSame(2, $instruction->participants()->count());

        $this->instructions()->update($instruction, [], []);
        $this->assertSame(1, $instruction->participants()->count());
        $this->assertSame($signer->id, $instruction->participants()->firstOrFail()->user_id);

        $this->expectException(ValidationException::class);
        $this->instructions()->delete($instruction);
    }

    // ── Vorsorge ─────────────────────────────────────────────────────────

    public function test_checkup_store_via_http_and_due_scope(): void {
        $lead = $this->lead();
        $person = $this->field();

        $this->actingAs($lead)
            ->post(route('safety.checkups.store'), [
                'user_id' => Sqid::encode(User::class, $person->id),
                'kind' => MedicalCheckupKind::Mandatory->value,
                'occasion' => 'Lärm',
                'performed_on' => now()->subYears(3)->toDateString(),
                'next_due_on' => now()->subDay()->toDateString(),
                'certificate_on_file' => '1',
            ])
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $this->assertDatabaseHas('medical_checkups', [
            'organization_id' => $this->organization->id,
            'user_id' => $person->id,
            'kind' => MedicalCheckupKind::Mandatory->value,
            'certificate_on_file' => 1,
            'created_by_user_id' => $lead->id,
        ]);
        $this->assertSame(1, MedicalCheckup::query()->due()->count());
    }

    public function test_checkup_rejects_user_from_other_organization(): void {
        $lead = $this->lead();
        $foreign = User::factory()->user()->create(['organization_id' => Organization::factory()->create()->id]);

        $this->actingAs($lead)
            ->post(route('safety.checkups.store'), [
                'user_id' => Sqid::encode(User::class, $foreign->id),
                'kind' => MedicalCheckupKind::Offered->value,
                'performed_on' => now()->toDateString(),
            ])
            ->assertSessionHasErrors('user_id');
    }

    // ── Fristen-Scan ─────────────────────────────────────────────────────

    private function enableInAppRules(): void {
        foreach ([NotificationEvent::SafetyAssessmentReviewDue, NotificationEvent::SafetyInstructionDue, NotificationEvent::SafetyCheckupDue] as $event) {
            NotificationRule::factory()->forEvent($event)->create([
                'organization_id' => $this->organization->id,
                'channels' => [NotificationChannel::InApp->value],
                'notify_affected' => $event !== NotificationEvent::SafetyAssessmentReviewDue,
                'recipient_roles' => [UserRole::Teamleitung->value],
            ]);
        }
    }

    private function logCount(NotificationEvent $event): int {
        return NotificationDispatchLog::query()->withoutGlobalScopes()->where('event', $event->value)->count();
    }

    public function test_deadline_scan_fires_each_register_exactly_once(): void {
        $this->enableInAppRules();
        $lead = $this->lead();
        $person = $this->field();

        $this->approvedAssessment($lead, ['review_due_on' => now()->subDay()->toDateString()]);

        $instruction = $this->instructions()->create($this->organization, $lead, [
            'topic' => 'Erste Hilfe',
            'held_on' => now()->subMonths(13)->toDateString(),
            'repeat_interval_months' => 12,
        ], [$person->id]);
        $this->assertTrue($instruction->participants()->firstOrFail()->isDueOverdue());

        MedicalCheckup::factory()->create([
            'organization_id' => $this->organization->id,
            'user_id' => $person->id,
            'next_due_on' => now()->addDays(10)->toDateString(),
        ]);

        $this->artisan('notifications:scan-deadlines')->assertExitCode(0);
        $this->artisan('notifications:scan-deadlines')->assertExitCode(0);

        $this->assertSame(1, $this->logCount(NotificationEvent::SafetyAssessmentReviewDue));
        $this->assertSame(1, $this->logCount(NotificationEvent::SafetyInstructionDue));
        $this->assertSame(1, $this->logCount(NotificationEvent::SafetyCheckupDue));

        // Betroffene Person: Unterweisung + Vorsorge; Teamleitung: alle drei.
        $this->assertSame(2, $person->notifications()->count());
        $this->assertSame(3, $lead->notifications()->count());
    }

    public function test_deadline_scan_ignores_superseded_instruction_and_archived_assessment(): void {
        $this->enableInAppRules();
        $lead = $this->lead();
        $person = $this->field();

        // Ältere Unterweisung überfällig, neuere zum selben Thema löst sie ab.
        $this->instructions()->create($this->organization, $lead, [
            'topic' => 'Erste Hilfe',
            'held_on' => now()->subMonths(20)->toDateString(),
            'repeat_interval_months' => 12,
        ], [$person->id]);
        $this->instructions()->create($this->organization, $lead, [
            'topic' => 'Erste Hilfe',
            'held_on' => now()->subMonths(2)->toDateString(),
            'repeat_interval_months' => 12,
        ], [$person->id]);

        // Archivierte (abgelöste) GBU mit überschrittener Wiedervorlage stößt nichts an.
        $original = $this->approvedAssessment($lead, ['review_due_on' => now()->subDay()->toDateString()]);
        $this->assessments()->newVersion($original, $lead);

        $this->artisan('notifications:scan-deadlines')->assertExitCode(0);

        $this->assertSame(0, $this->logCount(NotificationEvent::SafetyInstructionDue));
        $this->assertSame(0, $this->logCount(NotificationEvent::SafetyAssessmentReviewDue));
    }

    // ── Tenancy ──────────────────────────────────────────────────────────

    public function test_foreign_organization_records_are_invisible(): void {
        $lead = $this->lead();
        $foreignOrg = Organization::factory()->create();
        $foreignAssessment = HazardAssessment::factory()->create(['organization_id' => $foreignOrg->id]);
        $foreignInstruction = SafetyInstruction::factory()->create(['organization_id' => $foreignOrg->id]);
        SafetyInstructionParticipant::factory()->create([
            'organization_id' => $foreignOrg->id,
            'safety_instruction_id' => $foreignInstruction->id,
            'user_id' => User::factory()->user()->create(['organization_id' => $foreignOrg->id])->id,
        ]);

        $this->actingAs($lead)->get(route('safety.assessments.show', $foreignAssessment))->assertNotFound();
        $this->actingAs($lead)->get(route('safety.instructions.show', $foreignInstruction))->assertNotFound();
        $this->assertSame(0, HazardAssessment::query()->count());
        $this->assertSame(0, SafetyInstruction::query()->count());
    }
}
