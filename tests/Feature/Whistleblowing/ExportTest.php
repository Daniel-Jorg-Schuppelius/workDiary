<?php
/*
 * Created on   : Tue Jun 09 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ExportTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace Tests\Feature\Whistleblowing;

use App\Models\{Organization, User};
use App\Models\Whistleblowing\{CaseAssignment, WhistleblowingCase};
use App\Services\OrganizationLifecycleService;
use App\Services\Whistleblowing\{ReporterCredentialService, WhistleblowingPermissions};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;
use ZipArchive;

/**
 * Autorisierter Fallakten-Export (Abschnitt 17) und Ausschluss der
 * Hinweisgeberdaten aus dem Standard-Mandantenexport.
 */
class ExportTest extends TestCase {
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
            'organization_id' => $case->organization_id, 'case_id' => $case->id,
            'user_id' => $user->id, 'role' => 'processor', 'assigned_at' => now(),
        ]);
    }

    public function test_export_requires_permission_and_assignment(): void {
        $org = Organization::factory()->create();
        $unassigned = $this->handler($org);
        $case = $this->makeCase($org);

        $this->actingAs($unassigned)
            ->post(route('whistleblowing.internal.export', $case), ['reason' => 'Pruefung'])
            ->assertForbidden();
    }

    public function test_export_requires_reason(): void {
        $org = Organization::factory()->create();
        $handler = $this->handler($org);
        $case = $this->makeCase($org);
        $this->assign($case, $handler);

        $this->actingAs($handler)
            ->post(route('whistleblowing.internal.export', $case), [])
            ->assertSessionHasErrors('reason');
    }

    public function test_export_downloads_and_records_event(): void {
        $org = Organization::factory()->create();
        $handler = $this->handler($org);
        $case = $this->makeCase($org);
        $this->assign($case, $handler);

        $this->actingAs($handler)
            ->post(route('whistleblowing.internal.export', $case), ['reason' => 'Behoerdenanfrage'])
            ->assertOk()
            ->assertHeader('content-disposition', 'attachment; filename=hinweisgeber-fall-' . $case->case_number . '.zip');

        $this->assertDatabaseHas('whistleblowing_case_events', [
            'case_id' => $case->id, 'event' => 'case.exported',
        ]);
    }

    public function test_standard_org_export_excludes_whistleblowing(): void {
        Storage::fake('local');
        $org = Organization::factory()->create();
        $this->makeCase($org);

        $relPath = app(OrganizationLifecycleService::class)->export($org, null);
        $absPath = Storage::disk('local')->path($relPath);

        $zip = new ZipArchive;
        $this->assertTrue($zip->open($absPath) === true);
        $names = [];
        for ($i = 0; $i < $zip->numFiles; $i++) {
            $names[] = $zip->getNameIndex($i);
        }
        $zip->close();

        $wb = array_filter($names, fn($n) => str_contains((string) $n, 'whistleblowing_'));
        $this->assertEmpty($wb, 'Hinweisgeberdaten duerfen nicht im Standard-Export sein.');
    }
}
