<?php
/*
 * Created on   : Sun Oct 04 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : SecurityAudit202610Wave1Test.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace Tests\Feature\Security;

use App\Enums\User\Permission;
use App\Models\Customer\Customer;
use App\Models\Diary\DiaryEntry;
use App\Models\Protocol\Protocol;
use App\Support\BranchProfileFiles;
use Database\Seeders\EntryTypeSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Tests\Concerns\{BuildsPolicyActors, WithOrganization};
use Tests\TestCase;

/**
 * Regressionstests zur Welle 1 des Sicherheitsaudits 2026-10-04
 * (WorkDiary-Architecture/security/sicherheitsaudit-2026-10-04-behebung.md).
 */
final class SecurityAudit202610Wave1Test extends TestCase {
    use BuildsPolicyActors;
    use RefreshDatabase;
    use WithOrganization;

    protected function setUp(): void {
        parent::setUp();
        $this->setUpOrganization();
    }

    protected function tearDown(): void {
        foreach (['lfi-probe.php', 'lfi-hit'] as $file) {
            @unlink(storage_path('framework/testing/' . $file));
        }
        parent::tearDown();
    }

    /** authz-b-1: der Code eines importierten Profils wird nie zu einem Dateipfad. */
    public function test_branch_profile_import_rejects_codes_that_leave_the_profile_directory(): void {
        $this->writeProbe();
        $admin = $this->orgAdmin();

        $upload = UploadedFile::fake()->createWithContent('profil.json', (string) json_encode([
            'code' => '../../../../storage/framework/testing/lfi-probe',
            'label' => 'Sonde',
        ]));

        $this->actingAs($admin)->post(route('admin.branch-profiles.import'), ['file' => $upload])
            ->assertRedirect()->assertSessionHas('error');

        $this->assertFileDoesNotExist(storage_path('framework/testing/lfi-hit'));
        $this->assertSame([], $this->organization->refresh()->installedBranchProfileCodes());
    }

    /** authz-b-1/xi-2: auch ein bereits gespeicherter Code wird nicht eingebunden. */
    public function test_stored_profile_codes_are_never_resolved_outside_the_profile_directory(): void {
        $this->writeProbe();
        $code = '../../../../storage/framework/testing/lfi-probe';

        $this->assertNull(BranchProfileFiles::profile($code));
        $this->assertSame([], BranchProfileFiles::sidecar($code));
        $this->assertSame(EntryTypeSeeder::DEFAULT_SLUGS, EntryTypeSeeder::defaultSlugsFor($code));
        $this->assertFileDoesNotExist(storage_path('framework/testing/lfi-hit'));

        $this->assertIsArray(BranchProfileFiles::profile('it'));
    }

    /** authz-b-2: der Import installiert und braucht deshalb das Installationsrecht. */
    public function test_branch_profile_import_requires_the_install_permission(): void {
        $viewer = $this->orgUser();
        $this->grantPermissions($viewer, [Permission::BranchProfileViewCatalog]);

        $upload = UploadedFile::fake()->createWithContent('profil.json', (string) json_encode([
            'code' => 'custom-y',
            'label' => 'Custom Y',
        ]));

        $this->actingAs($viewer)->post(route('admin.branch-profiles.import'), ['file' => $upload])
            ->assertForbidden();
        $this->assertSame([], $this->organization->refresh()->installedBranchProfileCodes());
    }

    /** authz-b-3: der Offline-Sync schreibt nur an Aufträge, die die Person öffnen darf. */
    public function test_sync_comment_command_respects_order_visibility(): void {
        $worker = $this->orgUser();
        $colleague = $this->orgUser();
        $own = DiaryEntry::factory()->for($worker)->create(['organization_id' => $this->organization->id]);
        $foreign = DiaryEntry::factory()->for($colleague)->create(['organization_id' => $this->organization->id]);

        $response = $this->actingAs($worker)->postJson(route('api.internal.sync.commands'), ['commands' => [
            ['client_uuid' => (string) Str::uuid(), 'type' => 'comment.diary', 'payload' => ['diary' => $foreign->sqid, 'body' => 'fremd']],
            ['client_uuid' => (string) Str::uuid(), 'type' => 'comment.diary', 'payload' => ['diary' => $own->sqid, 'body' => 'eigen']],
        ]]);

        $response->assertOk()
            ->assertJsonPath('results.0.status', 'rejected')
            ->assertJsonPath('results.1.status', 'applied');
        $this->assertSame(0, $foreign->comments()->count());
        $this->assertSame(1, $own->comments()->count());
    }

    /** authz-b-4: ein Protokoll entsteht nur an einem Bezug, den die Person öffnen darf. */
    public function test_protocol_store_requires_view_on_the_subject(): void {
        $worker = $this->orgUser();
        $colleague = $this->orgUser();
        $own = DiaryEntry::factory()->for($worker)->create(['organization_id' => $this->organization->id]);
        $foreign = DiaryEntry::factory()->for($colleague)->create(['organization_id' => $this->organization->id]);
        $payload = static fn (DiaryEntry $order): array => [
            'subject_kind' => 'diary',
            'subject_id' => $order->id,
            'type' => 'service',
            'title' => 'Sonde',
            'visibility' => 'customer',
        ];

        $this->actingAs($worker)->post(route('protocols.store'), $payload($foreign))->assertForbidden();
        $this->assertSame(0, Protocol::query()->count());

        $this->actingAs($worker)->post(route('protocols.store'), $payload($own))->assertRedirect();
        $this->assertSame(1, Protocol::query()->count());
    }

    /** authz-a-1: die Abrechnung eines Kunden führt nur die Abrechnungsrolle, nicht sein Ersteller. */
    public function test_customer_billing_requires_the_billing_role_not_ownership(): void {
        $creator = $this->orgUser();
        $customer = Customer::factory()->create(['organization_id' => $this->organization->id, 'created_by' => $creator->id]);

        $this->assertTrue(Gate::forUser($creator)->allows('update', $customer));
        $this->assertFalse(Gate::forUser($creator)->allows('manageBilling', $customer));

        $this->actingAs($creator)->get(route('customers.billing.agreement.edit', $customer))->assertForbidden();
        $this->actingAs($creator)->post(route('customers.billing.agreement.save', $customer), ['mode' => 'retainer'])->assertForbidden();
        $this->actingAs($creator)->post(route('customers.billing.payments.store', $customer), ['amount' => '100'])->assertForbidden();
        $this->actingAs($creator)->post(route('customers.billing.retainer.push', $customer))->assertForbidden();
        $this->actingAs($creator)->post(route('customers.billing.recalculate', $customer))->assertForbidden();

        $accountant = $this->userWithRole('buchhaltung');
        $this->assertTrue(Gate::forUser($accountant)->allows('manageBilling', $customer));
    }

    private function writeProbe(): void {
        $directory = storage_path('framework/testing');
        if (! is_dir($directory)) {
            mkdir($directory, 0775, true);
        }
        file_put_contents(
            $directory . '/lfi-probe.php',
            "<?php touch(" . var_export($directory . '/lfi-hit', true) . "); return [];\n",
        );
    }
}
