<?php
/*
 * Created on   : Mon Jun 08 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : AccessControlTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace Tests\Feature\Whistleblowing;

use App\Enums\Whistleblowing\CaseRole;
use App\Models\{Organization, User};
use App\Models\Whistleblowing\{CaseAssignment, EmergencyGrant, WhistleblowingCase};
use App\Services\Whistleblowing\{
    ReporterCredentialService,
    WhistleblowingAccessService,
    WhistleblowingAssignmentService,
    WhistleblowingPermissions,
};
use Illuminate\Foundation\Testing\RefreshDatabase;
use RuntimeException;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

/**
 * Interessenkonflikt-Selbstsperre und Notfallfreigabe (Abschnitt 7.4 / 25).
 */
class AccessControlTest extends TestCase {
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

    private function team(int $orgId): void {
        app(PermissionRegistrar::class)->setPermissionsTeamId($orgId);
    }

    private function handler(Organization $org): User {
        $user = User::factory()->create(['organization_id' => $org->id]);
        $this->team($org->id);
        $user->assignRole(WhistleblowingPermissions::ROLE_MELDESTELLE);
        $user->forceFill(['two_factor_secret' => 'JBSWY3DPEHPK3PXP', 'two_factor_confirmed_at' => now()])->save();

        return $user;
    }

    private function makeCase(Organization $org): WhistleblowingCase {
        $cred = app(ReporterCredentialService::class);
        $secret = $cred->generateSecret();

        $case = new WhistleblowingCase;
        $case->organization_id = $org->id;
        $case->initializeDek();
        $case->reporter_mode = 'anonymous';
        $case->category = 'fraud';
        $case->subject_ciphertext = 'S';
        $case->description_ciphertext = 'D';
        $case->forceFill([
            'case_number' => $cred->generateCaseNumber(),
            'access_code_hash' => $cred->hashSecret($secret),
            'access_code_lookup' => $cred->lookupHmac($secret),
        ]);
        $case->save();

        return $case;
    }

    private function assign(WhistleblowingCase $case, User $user): void {
        CaseAssignment::create([
            'organization_id' => $case->organization_id, 'case_id' => $case->id,
            'user_id' => $user->id, 'role' => 'processor', 'assigned_at' => now(),
        ]);
    }

    public function test_conflict_blocks_even_assigned_handler(): void {
        $org = Organization::factory()->create();
        $handler = $this->handler($org);
        $case = $this->makeCase($org);
        $this->assign($case, $handler);

        $this->team($org->id);
        $this->assertTrue($handler->can('view', $case));

        app(WhistleblowingAccessService::class)->declareConflict($case, $handler);

        $this->team($org->id);
        $this->assertFalse($handler->can('view', $case->fresh()), 'Konflikt sperrt den Zugriff.');
        $this->assertFalse($case->fresh()->isAssigned($handler), 'Zuweisung wurde widerrufen.');
    }

    public function test_conflicted_user_cannot_be_assigned(): void {
        $org = Organization::factory()->create();
        $handler = $this->handler($org);
        $approver = $this->handler($org);
        $case = $this->makeCase($org);

        app(WhistleblowingAccessService::class)->declareConflict($case, $handler);

        $this->expectException(RuntimeException::class);
        app(WhistleblowingAssignmentService::class)->assign($case, $handler, CaseRole::Processor, $approver);
    }

    public function test_emergency_grant_allows_access_until_expiry(): void {
        $org = Organization::factory()->create();
        $grantee = $this->handler($org);
        $approver = $this->handler($org);
        $case = $this->makeCase($org); // grantee NICHT zugewiesen

        $this->team($org->id);
        $this->assertFalse($grantee->can('view', $case));

        app(WhistleblowingAccessService::class)->grantEmergencyAccess($case, $grantee, $approver, 'Akuter Notfall', 60);

        $this->team($org->id);
        $this->assertTrue($grantee->can('view', $case->fresh()));

        // Abgelaufen → kein Zugriff mehr.
        EmergencyGrant::query()->update(['expires_at' => now()->subMinute()]);
        $this->team($org->id);
        $this->assertFalse($grantee->can('view', $case->fresh()));
    }

    public function test_emergency_requires_a_different_approver(): void {
        $org = Organization::factory()->create();
        $user = $this->handler($org);
        $case = $this->makeCase($org);

        $this->expectException(RuntimeException::class);
        app(WhistleblowingAccessService::class)->grantEmergencyAccess($case, $user, $user, 'x');
    }

    public function test_admin_cannot_grant_emergency_via_http(): void {
        $org = Organization::factory()->create();
        WhistleblowingPermissions::seedOrganization($org);
        $admin = User::factory()->admin()->create(['organization_id' => $org->id]);
        $grantee = $this->handler($org);
        $case = $this->makeCase($org);

        $this->actingAs($admin)
            ->post(route('whistleblowing.internal.emergency', $case), [
                'user_id' => $grantee->id, 'reason' => 'x',
            ])->assertForbidden();
    }

    public function test_named_subject_is_blocked_and_unassignable(): void {
        $org = Organization::factory()->create();
        $handler = $this->handler($org);
        $approver = $this->handler($org);
        $case = $this->makeCase($org);
        $this->assign($case, $handler);

        $this->team($org->id);
        $this->assertTrue($handler->can('view', $case));

        // Als Betroffener markieren → gesperrt, Zuweisung widerrufen.
        app(WhistleblowingAccessService::class)->markSubject($case, $handler, $approver);

        $this->team($org->id);
        $this->assertTrue($case->fresh()->isSubjectFor($handler));
        $this->assertFalse($handler->can('view', $case->fresh()), 'Betroffener hat keinen Zugriff.');
        $this->assertFalse($case->fresh()->isAssigned($handler), 'Zuweisung wurde widerrufen.');

        $this->expectException(RuntimeException::class);
        app(WhistleblowingAssignmentService::class)->assign($case->fresh(), $handler, CaseRole::Processor, $approver);
    }

    public function test_handler_can_mark_subject_via_http(): void {
        $org = Organization::factory()->create();
        $handler = $this->handler($org);
        $target = $this->handler($org);
        $case = $this->makeCase($org);
        $this->assign($case, $handler);

        $this->actingAs($handler)
            ->post(route('whistleblowing.internal.subject', $case), ['user_id' => $target->id])
            ->assertRedirect();

        $this->assertTrue($case->fresh()->isSubjectFor($target));
    }
}
