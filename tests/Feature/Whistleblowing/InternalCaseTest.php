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

use App\Models\{Organization, User};
use App\Models\Whistleblowing\{CaseAssignment, WhistleblowingCase};
use App\Services\Whistleblowing\{ReporterCredentialService, WhistleblowingPermissions};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
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
}
