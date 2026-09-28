<?php
/*
 * Created on   : Mon Sep 28 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ClubMemberSubjectExportTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace Tests\Feature\Privacy;

use App\Enums\Privacy\{DataSubjectKind, DataSubjectRequestType};
use App\Models\Club\{ClubDonation, ClubGuardian, ClubMember};
use App\Models\Platform\{Organization, User};
use App\Models\Privacy\DataSubjectRequest;
use App\Services\Privacy\{DataProtectionPermissions, DataSubjectRequestService, SubjectDataExporter};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

/** MVP-1009: Vereinsmitglieder als Betroffenenart der Auskunft, mit Suche. */
final class ClubMemberSubjectExportTest extends TestCase {
    use RefreshDatabase;

    protected function setUp(): void {
        parent::setUp();
        config()->set('dataprotection.key', base64_encode(random_bytes(32)));
    }

    protected function tearDown(): void {
        app(PermissionRegistrar::class)->setPermissionsTeamId(null);
        parent::tearDown();
    }

    /** @return array{0: User, 1: DataSubjectRequest} */
    private function officerWithRequest(Organization $org): array {
        DataProtectionPermissions::seedOrganization($org);
        $officer = User::factory()->create(['organization_id' => $org->id]);
        app(PermissionRegistrar::class)->setPermissionsTeamId($org->id);
        $officer->assignRole(DataProtectionPermissions::ROLE_DATENSCHUTZ);
        $dsr = app(DataSubjectRequestService::class)->open($org, DataSubjectRequestType::Access, 'Max Mitglied', 'Auskunft bitte.', null, $officer);

        return [$officer, $dsr];
    }

    public function test_club_members_are_searchable_and_exported_with_club_families(): void {
        $org = Organization::factory()->create(['plan' => Organization::PLAN_ENTERPRISE]);
        [$officer, $dsr] = $this->officerWithRequest($org);
        app()->instance('currentOrganization', $org);
        $member = ClubMember::factory()->create(['organization_id' => $org->id, 'first_name' => 'Max', 'last_name' => 'Mitglied', 'email' => 'max@example.org']);
        ClubMember::factory()->create(['organization_id' => $org->id, 'first_name' => 'Erika', 'last_name' => 'Andere']);
        ClubMember::factory()->create(['organization_id' => Organization::factory()->create()->id, 'first_name' => 'Max', 'last_name' => 'Fremd']);
        ClubGuardian::query()->create(['organization_id' => $org->id, 'club_member_id' => $member->id, 'name' => 'Maria Mitglied', 'email' => 'maria@example.org']);
        ClubDonation::query()->create(['organization_id' => $org->id, 'club_member_id' => $member->id, 'kind' => 'donation', 'amount' => '50.00', 'currency' => 'EUR', 'received_on' => '2026-05-01']);

        $this->actingAs($officer)->get(route('dataprotection.requests.show', $dsr))->assertOk()->assertSee(__('Vereinsmitglied'));
        $items = $this->actingAs($officer)->getJson(route('dataprotection.requests.subject-search', ['dsr' => $dsr, 'kind' => 'club_member', 'q' => 'Mitglied']))
            ->assertOk()->json('items');
        $this->assertSame([['sqid' => $member->sqid, 'label' => 'Max Mitglied (' . $member->member_no . ')']], $items);
        $this->assertSame([$member->sqid], array_column($this->actingAs($officer)->getJson(route('dataprotection.requests.subject-search', ['dsr' => $dsr, 'kind' => 'club_member', 'q' => (string) $member->member_no]))->json('items'), 'sqid'));

        $exporter = app(SubjectDataExporter::class);
        $payload = $exporter->build($dsr, DataSubjectKind::ClubMember, $exporter->resolve(DataSubjectKind::ClubMember, (int) $org->id, (int) $member->id));
        $this->assertSame('Max Mitglied', $payload['subject_label']);
        $sections = array_column($payload['sections'], null, 'key');
        $this->assertSame('max@example.org', $sections['master_data']['fields']['email']['value']);
        $this->assertSame('Maria Mitglied', $sections['master_data']['lists'][__('Erziehungsberechtigte')][0]['name']);
        $this->assertArrayHasKey('contact_details', $sections);
        $donations = collect($sections['club_records']['families'])->firstWhere('table', 'club_donations');
        $this->assertSame(1, $donations['count']);
        $this->assertSame('2026-05-01', $donations['from']);
    }

    public function test_club_members_are_not_offered_without_the_club_module(): void {
        config()->set('license.feature_overrides', ['module.club' => false]);
        $org = Organization::factory()->create(['plan' => Organization::PLAN_ENTERPRISE]);
        [$officer, $dsr] = $this->officerWithRequest($org);

        $this->actingAs($officer)->get(route('dataprotection.requests.show', $dsr))->assertOk()->assertDontSee(__('Vereinsmitglied'));
        $this->actingAs($officer)->getJson(route('dataprotection.requests.subject-search', ['dsr' => $dsr, 'kind' => 'club_member']))->assertUnprocessable();
    }
}
