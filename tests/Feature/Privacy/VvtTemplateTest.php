<?php
/*
 * Created on   : Sat Jul 18 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : VvtTemplateTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace Tests\Feature\Privacy;

use App\Models\{Organization, User};
use App\Models\Privacy\ProcessingActivity;
use App\Services\Privacy\{DataProtectionPermissions, ProcessingActivityService};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

/**
 * Vollaudit 2026-07 (M17): VVT-Vorlagenkatalog (Feature 043 MVP 1) inkl. der
 * drei Finanz-Verarbeitungstätigkeiten aus Feature 045 — Anlage als Entwurf,
 * org-scoped, idempotent über den Namen.
 */
final class VvtTemplateTest extends TestCase {
    use RefreshDatabase;

    protected function tearDown(): void {
        app(PermissionRegistrar::class)->setPermissionsTeamId(null);
        parent::tearDown();
    }

    private function officer(Organization $org): User {
        DataProtectionPermissions::seedOrganization($org);
        $user = User::factory()->create(['organization_id' => $org->id]);
        app(PermissionRegistrar::class)->setPermissionsTeamId($org->id);
        $user->assignRole(DataProtectionPermissions::ROLE_DATENSCHUTZ);

        return $user;
    }

    public function test_catalog_contains_finance_activities_from_feature_045(): void {
        $templates = app(ProcessingActivityService::class)->templates();

        $this->assertArrayHasKey('zahlungsabgleich', $templates);
        $this->assertArrayHasKey('buchhaltungsuebergabe_kanzlei', $templates);
        $this->assertArrayHasKey('lohndatenuebermittlung', $templates);
        $this->assertArrayHasKey('it_helpdesk_fernwartung', $templates);
        $this->assertArrayHasKey('handwerk_auftragsabwicklung', $templates);
    }

    public function test_create_from_template_is_draft_and_idempotent(): void {
        $org = Organization::factory()->create();
        $service = app(ProcessingActivityService::class);

        $first = $service->createFromTemplate($org, 'zahlungsabgleich');
        $this->assertSame('draft', $first->status->value);
        $this->assertSame((int) $org->id, (int) $first->organization_id);
        $this->assertSame(1, $first->versions()->count());

        $second = $service->createFromTemplate($org, 'zahlungsabgleich');
        $this->assertSame($first->id, $second->id);
        $this->assertSame(1, ProcessingActivity::query()->where('organization_id', $org->id)->count());
    }

    public function test_endpoint_creates_from_template(): void {
        $org = Organization::factory()->create();

        $this->actingAs($this->officer($org))
            ->post(route('dataprotection.activities.template'), ['template' => 'lohndatenuebermittlung'])
            ->assertRedirect();

        $this->assertDatabaseHas('privacy_processing_activities', [
            'organization_id' => $org->id,
            'name' => 'Lohndatenübermittlung',
        ]);

        $this->actingAs($this->officer($org))
            ->post(route('dataprotection.activities.template'), ['template' => 'gibts-nicht'])
            ->assertNotFound();
    }
}
