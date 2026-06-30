<?php
/*
 * Created on   : Mon Jun 08 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : CasePolicyTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace Tests\Feature\Whistleblowing;

use App\Models\{Organization, User};
use App\Models\Whistleblowing\{CaseAssignment, WhistleblowingCase};
use App\Services\Whistleblowing\{ReporterCredentialService, WhistleblowingPermissions};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

/**
 * Kern der Autorisierung (Abschnitt 5 / 25): inhaltlicher Zugriff verlangt
 * Permission UND aktive Fall-Zuweisung; Mandantentrennung; Plattform-/Org-Admins
 * erhalten KEINEN automatischen Zugriff.
 */
class CasePolicyTest extends TestCase {
    use RefreshDatabase;

    protected function setUp(): void {
        parent::setUp();
        config()->set('whistleblowing.key', base64_encode(random_bytes(32)));
        config()->set('whistleblowing.lookup_key', base64_encode(random_bytes(32)));
    }

    protected function tearDown(): void {
        // Spatie-Team-Kontext zuruecksetzen, damit er nicht in andere
        // Testklassen leakt (sonst flaky Permission-Checks).
        app(PermissionRegistrar::class)->setPermissionsTeamId(null);
        parent::tearDown();
    }

    private function team(int $orgId): void {
        app(PermissionRegistrar::class)->setPermissionsTeamId($orgId);
    }

    private function meldestelleUser(Organization $org): User {
        WhistleblowingPermissions::seedOrganization($org);
        $user = User::factory()->create(['organization_id' => $org->id]);
        $this->team($org->id);
        $user->assignRole(WhistleblowingPermissions::ROLE_MELDESTELLE);

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
        $case->subject_ciphertext = 'Betreff';
        $case->description_ciphertext = 'Beschreibung';
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
            'organization_id' => $case->organization_id,
            'case_id' => $case->id,
            'user_id' => $user->id,
            'role' => 'processor',
            'assigned_at' => now(),
        ]);
    }

    public function test_assigned_handler_can_view(): void {
        $org = Organization::factory()->create();
        $user = $this->meldestelleUser($org);
        $case = $this->makeCase($org);
        $this->assign($case, $user);

        $this->team($org->id);
        $this->assertTrue($user->can('viewAny', WhistleblowingCase::class));
        $this->assertTrue($user->can('view', $case));
        $this->assertTrue($user->can('process', $case));
    }

    public function test_unassigned_handler_cannot_view_content(): void {
        $org = Organization::factory()->create();
        $user = $this->meldestelleUser($org);
        $case = $this->makeCase($org); // keine Zuweisung

        $this->team($org->id);
        // Liste (viewAny) ja – aber kein Inhalt ohne Zuweisung.
        $this->assertTrue($user->can('viewAny', WhistleblowingCase::class));
        $this->assertFalse($user->can('view', $case));
        $this->assertFalse($user->can('process', $case));
    }

    public function test_other_organization_cannot_view(): void {
        $orgA = Organization::factory()->create();
        $orgB = Organization::factory()->create();
        $userB = $this->meldestelleUser($orgB);
        $caseA = $this->makeCase($orgA);

        $this->team($orgB->id);
        $this->assertFalse($userB->can('view', $caseA));
    }

    public function test_platform_admin_has_no_automatic_access(): void {
        $org = Organization::factory()->create();
        WhistleblowingPermissions::seedOrganization($org);
        $admin = User::factory()->admin()->create(['organization_id' => $org->id]);
        $case = $this->makeCase($org);

        $this->team($org->id);
        $this->assertTrue($admin->isAdmin());
        $this->assertFalse($admin->can('viewAny', WhistleblowingCase::class), 'Admin sieht die Fallliste NICHT automatisch.');
        $this->assertFalse($admin->can('view', $case), 'Admin sieht Meldeinhalte NICHT automatisch.');
    }
}
