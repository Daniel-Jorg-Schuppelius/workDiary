<?php
/*
 * Created on   : Mon Jun 08 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : InternalCaseTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace Tests\Feature\Whistleblowing;

use App\Enums\Whistleblowing\CaseStatus;
use App\Models\Platform\{Organization, User};
use App\Models\Whistleblowing\{CaseAssignment, CaseConflict, CaseEvent, CaseTombstone, WhistleblowingCase};
use App\Services\Whistleblowing\{InvalidCaseTransition, ReporterCredentialService, WhistleblowingCaseWorkflowService, WhistleblowingMessageService, WhistleblowingPermissions};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\{DB, Storage};
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

/**
 * Interne Fallbearbeitung ueber HTTP: Autorisierung (Permission + Zuweisung +
 * Mandant, Admin ohne Zugriff) und die Kernaktionen.
 */
class InternalCaseTest extends TestCase {
    use RefreshDatabase;

    protected function setUp(): void {
        parent::setUp();
        config()->set('whistleblowing.key', base64_encode(random_bytes(32)));
        config()->set('whistleblowing.lookup_key', base64_encode(random_bytes(32)));
    }

    protected function tearDown(): void {
        app(PermissionRegistrar::class)->setPermissionsTeamId(null);
        parent::tearDown();
    }

    private function handler(Organization $org): User {
        $user = User::factory()->create(['organization_id' => $org->id]);
        app(PermissionRegistrar::class)->setPermissionsTeamId($org->id);
        $user->assignRole(WhistleblowingPermissions::ROLE_MELDESTELLE);
        $user->forceFill(['two_factor_secret' => 'JBSWY3DPEHPK3PXP', 'two_factor_confirmed_at' => now()])->save();

        return $user;
    }

    private function makeCase(Organization $org, string $subject = 'GeheimerBetreffABC'): WhistleblowingCase {
        $cred = app(ReporterCredentialService::class);
        $secret = $cred->generateSecret();

        $case = new WhistleblowingCase;
        $case->organization_id = $org->id;
        $case->initializeDek();
        $case->reporter_mode = 'anonymous';
        $case->category = 'fraud';
        $case->subject_ciphertext = $subject;
        $case->description_ciphertext = 'Beschreibung';
        $case->forceFill([
            'case_number' => $cred->generateCaseNumber(),
            'access_code_hash' => $cred->hashSecret($secret),
            'access_code_lookup' => $cred->lookupHmac($secret),
        ]);
        $case->save();

        return $case;
    }

    private function assignTo(WhistleblowingCase $case, User $user): void {
        CaseAssignment::create([
            'organization_id' => $case->organization_id,
            'case_id' => $case->id,
            'user_id' => $user->id,
            'role' => 'processor',
            'assigned_at' => now(),
        ]);
    }

    public function test_index_requires_permission(): void {
        $org = Organization::factory()->create();
        $plain = User::factory()->create(['organization_id' => $org->id]);
        $handler = $this->handler($org);

        $this->actingAs($plain)->get('/compliance/meldungen')->assertForbidden();
        $this->actingAs($handler)->get('/compliance/meldungen')->assertOk();
    }

    public function test_assigned_handler_sees_content_unassigned_does_not(): void {
        $org = Organization::factory()->create();
        $assigned = $this->handler($org);
        $other = $this->handler($org);
        $case = $this->makeCase($org);
        $this->assignTo($case, $assigned);

        $this->actingAs($assigned)->get(route('whistleblowing.internal.show', $case))
            ->assertOk()->assertSee('GeheimerBetreffABC');

        $this->actingAs($other)->get(route('whistleblowing.internal.show', $case))
            ->assertForbidden();
    }

    public function test_category_and_priority_are_listed_only_after_assignment(): void {
        // MVP-802 (Entscheid P13-26): In kleinen Organisationen verraten Kategorie
        // und Priorität sonst schon in der Liste, wer gemeldet haben könnte.
        $org = Organization::factory()->create();
        $assigned = $this->handler($org);
        $other = $this->handler($org);
        $case = $this->makeCase($org);
        $this->assignTo($case, $assigned);
        $category = __('whistleblowing.category.fraud');

        $this->actingAs($assigned)->get(route('whistleblowing.internal.index'))
            ->assertOk()
            ->assertSee($case->case_number)
            ->assertSee($category);

        $this->actingAs($other)->get(route('whistleblowing.internal.index'))
            ->assertOk()
            ->assertSee($case->case_number)
            ->assertDontSee($category)
            ->assertSee(__('Sichtbar nach Zuweisung'));
    }

    public function test_other_organization_gets_404(): void {
        $orgA = Organization::factory()->create();
        $orgB = Organization::factory()->create();
        $handlerB = $this->handler($orgB);
        $caseA = $this->makeCase($orgA);

        $this->actingAs($handlerB)->get(route('whistleblowing.internal.show', $caseA))
            ->assertNotFound();
    }

    public function test_platform_admin_without_permission_is_forbidden(): void {
        $org = Organization::factory()->create();
        WhistleblowingPermissions::seedOrganization($org);
        $admin = User::factory()->admin()->create(['organization_id' => $org->id]);

        $this->actingAs($admin)->get('/compliance/meldungen')->assertForbidden();
    }

    public function test_acknowledge_and_status_actions(): void {
        $org = Organization::factory()->create();
        $handler = $this->handler($org);
        $case = $this->makeCase($org);
        $this->assignTo($case, $handler);

        $this->actingAs($handler)
            ->post(route('whistleblowing.internal.acknowledge', $case))
            ->assertRedirect();
        $this->assertSame('acknowledged', $case->fresh()->status->value);

        $this->actingAs($handler)
            ->post(route('whistleblowing.internal.status', $case), ['to' => 'triage'])
            ->assertRedirect();
        $this->assertSame('triage', $case->fresh()->status->value);
    }

    public function test_note_action_creates_encrypted_internal_note(): void {
        $org = Organization::factory()->create();
        $handler = $this->handler($org);
        $case = $this->makeCase($org);
        $this->assignTo($case, $handler);

        $this->actingAs($handler)
            ->post(route('whistleblowing.internal.note', $case), ['body' => 'Interne Beobachtung XYZ'])
            ->assertRedirect();

        $message = $case->messages()->where('visibility', 'internal')->firstOrFail();
        $this->assertSame('Interne Beobachtung XYZ', $message->body_ciphertext);

        $raw = DB::table('whistleblowing_messages')->where('id', $message->id)->first();
        $this->assertStringNotContainsString('Interne Beobachtung', (string) $raw->body_ciphertext);
    }

    // ── Interessenkonflikt (Konzept 7.4) ────────────────────────────────────

    public function test_case_file_offers_the_conflict_declaration_and_it_locks_the_handler_out(): void {
        $org = Organization::factory()->create();
        $handler = $this->handler($org);
        $colleague = $this->handler($org);
        $case = $this->makeCase($org);
        $this->assignTo($case, $handler);
        $this->assignTo($case, $colleague);

        $this->actingAs($handler)->get(route('whistleblowing.internal.show', $case))
            ->assertOk()
            ->assertSee('action="' . route('whistleblowing.internal.conflict', $case) . '"', false)
            ->assertSee(__('Interessenkonflikt melden'));

        $this->actingAs($handler)
            ->post(route('whistleblowing.internal.conflict', $case), ['reason' => 'Beschuldigter ist mein Schwager'])
            ->assertRedirect(route('whistleblowing.internal.index'))
            ->assertSessionHas('success', __('Sie haben sich wegen Interessenkonflikts gesperrt.'));

        // Sperre steht, Grund ist nur verschlüsselt abgelegt.
        $conflict = CaseConflict::query()->where('case_id', $case->id)->where('user_id', $handler->id)->sole();
        $this->assertNotNull($conflict->declared_at);
        $raw = (string) DB::table('whistleblowing_case_conflicts')->where('id', $conflict->id)->value('reason_ciphertext');
        $this->assertNotSame('', $raw);
        $this->assertStringNotContainsString('Schwager', $raw);

        // Eigene Zuweisung widerrufen, die der Kollegin bleibt.
        $this->assertFalse($case->fresh()->isAssigned($handler));
        $this->assertTrue($case->fresh()->isAssigned($colleague));

        // Ereignis in der Kette: wer, ohne Inhalt.
        $event = CaseEvent::query()->where('case_id', $case->id)->where('event', 'case.conflict_declared')->sole();
        $this->assertSame((int) $handler->id, (int) $event->actor_user_id);
        $this->assertSame(['user_id' => (int) $handler->id], $event->metadata);

        $this->actingAs($handler)->get(route('whistleblowing.internal.show', $case))->assertForbidden();
        $this->actingAs($colleague)->get(route('whistleblowing.internal.show', $case))->assertOk();
    }

    public function test_conflict_declaration_needs_the_view_permission_and_the_own_organization(): void {
        $org = Organization::factory()->create();
        $case = $this->makeCase($org);
        $route = route('whistleblowing.internal.conflict', $case);

        // Ohne Meldestellen-Recht, auch als Plattform-Admin: 403.
        $plain = User::factory()->create(['organization_id' => $org->id]);
        $this->actingAs($plain)->post($route)->assertForbidden();
        WhistleblowingPermissions::seedOrganization($org);
        $admin = User::factory()->admin()->create(['organization_id' => $org->id]);
        $this->actingAs($admin)->post($route)->assertForbidden();

        // Meldestelle einer fremden Organisation: der Fall löst nicht auf.
        $foreign = $this->handler(Organization::factory()->create());
        $this->actingAs($foreign)->post($route)->assertNotFound();

        $this->assertSame(0, CaseConflict::query()->where('case_id', $case->id)->count());
        $this->assertSame(0, CaseEvent::query()->where('case_id', $case->id)->where('event', 'case.conflict_declared')->count());

        // Mit Recht, aber ohne Zuweisung: Die Selbstsperre ist auch vorab zulässig (Policy declareConflict).
        $unassigned = $this->handler($org);
        $this->actingAs($unassigned)->post($route)->assertRedirect(route('whistleblowing.internal.index'));
        $this->assertTrue($case->fresh()->hasConflictFor($unassigned));
    }

    // ── Kontrollierte Löschung (Konzept 16) ─────────────────────────────────

    public function test_case_file_offers_deletion_only_during_retention_review(): void {
        $org = Organization::factory()->create();
        $handler = $this->handler($org);
        $case = $this->makeCase($org);
        $this->assignTo($case, $handler);
        $deleteForm = 'action="' . route('whistleblowing.internal.destroy', $case) . '"';

        foreach ([CaseStatus::Investigating, CaseStatus::ClosedSubstantiated, CaseStatus::LegalHold] as $status) {
            $case->forceFill(['status' => $status->value])->save();
            $this->actingAs($handler)->get(route('whistleblowing.internal.show', $case))
                ->assertOk()
                ->assertDontSee($deleteForm, false);
        }

        $case->forceFill(['status' => CaseStatus::RetentionReview->value])->save();
        $this->actingAs($handler)->get(route('whistleblowing.internal.show', $case))
            ->assertOk()
            ->assertSee($deleteForm, false)
            ->assertSee(__('Fall löschen'));
    }

    public function test_destroy_shreds_the_case_and_leaves_tombstone_and_event(): void {
        Storage::fake('whistleblowing');
        $org = Organization::factory()->create();
        $handler = $this->handler($org);
        $case = $this->makeCase($org);
        $this->assignTo($case, $handler);
        app(WhistleblowingMessageService::class)->addInternalNote($case, 'Interne Notiz', $handler);
        $case->forceFill(['status' => CaseStatus::RetentionReview->value])->save();

        $this->actingAs($handler)
            ->post(route('whistleblowing.internal.destroy', $case))
            ->assertRedirect(route('whistleblowing.internal.index'))
            ->assertSessionHas('success', __('Fall kontrolliert gelöscht (Crypto-Shredding).'));

        $row = DB::table('whistleblowing_cases')->where('id', $case->id)->first();
        $this->assertSame(CaseStatus::Deleted->value, $row->status);
        $this->assertNull($row->dek_wrapped, 'Schlüssel vernichtet.');
        $this->assertNull($row->subject_ciphertext);
        $this->assertNull($row->description_ciphertext);
        $this->assertSame(0, DB::table('whistleblowing_messages')->where('case_id', $case->id)->count());
        $this->assertSame(0, DB::table('whistleblowing_case_assignments')->where('case_id', $case->id)->count());

        // Ereignis in der Kette; sein Hash steht im Grabstein.
        $event = CaseEvent::query()->where('case_id', $case->id)->where('event', 'case.deleted')->sole();
        $this->assertSame((int) $handler->id, (int) $event->actor_user_id);
        $tombstone = CaseTombstone::query()->where('public_id', $case->public_id)->sole();
        $this->assertSame($case->case_number, $tombstone->case_number);
        $this->assertSame((int) $org->id, (int) $tombstone->organization_id);
        $this->assertSame($event->hash, $tombstone->audit_hash);

        // Mit den Zuweisungen endet der Zugriff auf die Akte.
        $this->actingAs($handler)->get(route('whistleblowing.internal.show', $case))->assertForbidden();
    }

    public function test_destroy_needs_permission_assignment_and_the_own_organization(): void {
        Storage::fake('whistleblowing');
        $org = Organization::factory()->create();
        $case = $this->makeCase($org);
        $case->forceFill(['status' => CaseStatus::RetentionReview->value])->save();
        $route = route('whistleblowing.internal.destroy', $case);

        $plain = User::factory()->create(['organization_id' => $org->id]);
        $this->actingAs($plain)->post($route)->assertForbidden();

        // Plattform-Admin ohne Meldestellen-Recht.
        WhistleblowingPermissions::seedOrganization($org);
        $admin = User::factory()->admin()->create(['organization_id' => $org->id]);
        $this->actingAs($admin)->post($route)->assertForbidden();

        // Meldestelle ohne Zuweisung zu diesem Fall.
        $unassigned = $this->handler($org);
        $this->actingAs($unassigned)->post($route)->assertForbidden();

        // Zugewiesen, aber wegen Interessenkonflikts gesperrt.
        $conflicted = $this->handler($org);
        $this->assignTo($case, $conflicted);
        DB::table('whistleblowing_case_conflicts')->insert([
            'organization_id' => $org->id, 'case_id' => $case->id, 'user_id' => $conflicted->id, 'declared_at' => now(),
        ]);
        $this->actingAs($conflicted)->post($route)->assertForbidden();

        // Meldestelle einer fremden Organisation.
        $foreign = $this->handler(Organization::factory()->create());
        $this->actingAs($foreign)->post($route)->assertNotFound();

        $row = DB::table('whistleblowing_cases')->where('id', $case->id)->first();
        $this->assertSame(CaseStatus::RetentionReview->value, $row->status);
        $this->assertNotNull($row->dek_wrapped);
        $this->assertNotNull($row->subject_ciphertext);
        $this->assertSame(0, CaseTombstone::query()->count());
        $this->assertSame(0, CaseEvent::query()->where('case_id', $case->id)->where('event', 'case.deleted')->count());
    }

    /** Löschsperre oder falscher Stand: Der Dienst lehnt ab — als Meldung, nicht als Serverfehler. */
    public function test_destroy_is_refused_under_legal_hold_and_outside_retention_review(): void {
        Storage::fake('whistleblowing');
        $org = Organization::factory()->create();
        $handler = $this->handler($org);
        $case = $this->makeCase($org);
        $this->assignTo($case, $handler);

        foreach ([CaseStatus::LegalHold, CaseStatus::ClosedSubstantiated, CaseStatus::Investigating] as $status) {
            $case->forceFill(['status' => $status->value])->save();

            $this->actingAs($handler)->from(route('whistleblowing.internal.show', $case))
                ->post(route('whistleblowing.internal.destroy', $case))
                ->assertRedirect(route('whistleblowing.internal.show', $case))
                ->assertSessionHas('error');

            $row = DB::table('whistleblowing_cases')->where('id', $case->id)->first();
            $this->assertSame($status->value, $row->status);
            $this->assertNotNull($row->dek_wrapped);
            $this->assertNotNull($row->subject_ciphertext);
        }

        $this->assertSame(0, CaseTombstone::query()->count());
        $this->assertSame(0, CaseEvent::query()->where('case_id', $case->id)->where('event', 'case.deleted')->count());
    }

    /**
     * „Gelöscht" ließ sich über das Statusformular setzen: Inhalte und Schlüssel
     * blieben stehen, ein Grabstein fehlte, und der Löschdienst nahm den Fall
     * danach nicht mehr an.
     */
    /** Das Statusfeld bot jeden Status an; ein unzulässiger Wechsel oder ein Abschluss ohne Begründung endete in HTTP 500. */
    public function test_status_form_offers_only_allowed_targets_and_reports_instead_of_failing(): void {
        $org = Organization::factory()->create();
        $handler = $this->handler($org);
        $case = $this->makeCase($org);
        $this->assignTo($case, $handler);
        $case->forceFill(['status' => CaseStatus::Triage->value])->save();

        $this->actingAs($handler)->get(route('whistleblowing.internal.show', $case))
            ->assertOk()
            ->assertSee('value="investigating"', false)
            ->assertDontSee('value="retention_review"', false)
            ->assertDontSee('value="submitted"', false);

        // Unzulässig aus „Triage": Meldung am Feld, Status bleibt.
        $this->actingAs($handler)
            ->post(route('whistleblowing.internal.status', $case), ['to' => CaseStatus::RetentionReview->value])
            ->assertRedirect()
            ->assertSessionHasErrors('to');
        $this->assertSame(CaseStatus::Triage, $case->fresh()->status);

        // Abschluss ohne Begründung: Validierungsfehler, Status bleibt.
        $this->actingAs($handler)
            ->post(route('whistleblowing.internal.status', $case), ['to' => CaseStatus::ClosedOutOfScope->value])
            ->assertSessionHasErrors('reason');
        $this->assertSame(CaseStatus::Triage, $case->fresh()->status);

        $this->actingAs($handler)
            ->post(route('whistleblowing.internal.status', $case), ['to' => CaseStatus::ClosedOutOfScope->value, 'reason' => 'Kein Verstoß im Sinne des Gesetzes.'])
            ->assertRedirect()
            ->assertSessionHasNoErrors();
        $this->assertSame(CaseStatus::ClosedOutOfScope, $case->fresh()->status);
    }

    public function test_status_form_cannot_mark_a_case_deleted(): void {
        $org = Organization::factory()->create();
        $handler = $this->handler($org);
        $case = $this->makeCase($org);
        $this->assignTo($case, $handler);
        $case->forceFill(['status' => CaseStatus::RetentionReview->value])->save();

        $this->actingAs($handler)->get(route('whistleblowing.internal.show', $case))
            ->assertOk()
            ->assertSee('value="legal_hold"', false)
            ->assertDontSee('value="deleted"', false);

        $this->actingAs($handler)
            ->post(route('whistleblowing.internal.status', $case), ['to' => CaseStatus::Deleted->value])
            ->assertSessionHasErrors('to');

        $row = DB::table('whistleblowing_cases')->where('id', $case->id)->first();
        $this->assertSame(CaseStatus::RetentionReview->value, $row->status);
        $this->assertNotNull($row->dek_wrapped);
        $this->assertSame(0, CaseTombstone::query()->count());

        $this->expectException(InvalidCaseTransition::class);
        app(WhistleblowingCaseWorkflowService::class)->transition($case->fresh(), CaseStatus::Deleted, $handler);
    }
}
