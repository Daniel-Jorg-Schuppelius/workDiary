<?php
/*
 * Created on   : Wed Nov 11 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : CloudRouteTestAndStartTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace Tests\Feature\CloudIntake;

use App\Enums\CloudIntake\{CloudIntakeConnectionStatus, CloudIntakeRouteTarget};
use App\Enums\User\Permission;
use App\Models\CloudIntake\{CloudDocumentConnection, CloudDocumentRoute};
use App\Models\Customer\Customer;
use App\Models\Platform\{Organization, User};
use App\Support\{MorphMap, Sqid};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\WithOrganization;
use Tests\Support\FakePluginHttp;
use Tests\TestCase;

/** MVP-1613: feste Zielreferenz, Regeltest, Überschneidung und bestätigter Start. */
class CloudRouteTestAndStartTest extends TestCase {
    use RefreshDatabase;
    use WithOrganization;

    private User $admin;

    private CloudDocumentConnection $connection;

    private Customer $customer;

    protected function setUp(): void {
        parent::setUp();
        $this->setUpOrganization();
        $this->admin = User::factory()->user()->create(['organization_id' => $this->organization->id]);
        $this->admin->givePermissionTo([
            Permission::CloudIntakeConnectionManage->value,
            Permission::CloudIntakeRouteManage->value,
            Permission::CloudIntakeRunPreview->value,
            Permission::CustomerViewAny->value,
            Permission::CustomerView->value,
        ]);
        $this->connection = CloudDocumentConnection::factory()->create([
            'organization_id' => $this->organization->id,
            'created_by_user_id' => $this->admin->id,
            'root_folder_path' => '/WorkDiary',
        ]);
        $this->customer = Customer::factory()->create(['organization_id' => $this->organization->id, 'number' => 'K-4711', 'name' => 'Beispiel Bau GmbH']);
    }

    /** @param array<string, mixed> $overrides */
    private function store(array $overrides = []): \Illuminate\Testing\TestResponse {
        return $this->actingAs($this->admin)->from(route('admin.cloud-intake.index'))->post(route('admin.cloud-intake.routes.store', $this->connection), $overrides + [
            'path_pattern' => 'Kunden/{customer_number}/**',
            'priority' => 10,
            'target' => CloudIntakeRouteTarget::Document->value,
            'document_type' => 'contract',
            'test_path' => 'Kunden/K-4711/Vertraege/rahmen.pdf',
            'active' => '1',
        ]);
    }

    public function test_route_is_saved_only_with_a_successful_test_and_reports_the_target(): void {
        $this->store(['test_path' => 'Lieferanten/X/a.pdf'])->assertSessionHasErrors(['test_path' => __('cloud_intake.test.no_match')]);
        $this->store(['test_path' => 'Kunden/K-0000/a.pdf'])->assertSessionHasErrors('test_path');
        $this->store(['path_pattern' => 'Kunden/{customer_number}/{project_number}/**', 'test_path' => 'Kunden/K-4711/P1/a.pdf'])->assertSessionHasErrors('path_pattern');
        $this->assertSame(0, CloudDocumentRoute::query()->count());

        // Vollständiger Pfad samt Stammordner wird gekürzt.
        $this->store(['test_path' => '/WorkDiary/Kunden/K-4711/Vertraege/rahmen.pdf'])
            ->assertSessionHasNoErrors()
            ->assertSessionHas('success', fn (string $message): bool => str_contains($message, 'Beispiel Bau GmbH'));
        $route = CloudDocumentRoute::query()->sole();
        $this->assertSame('/WorkDiary/Kunden/K-4711/Vertraege/rahmen.pdf', $route->test_path);
        $this->assertNotNull($route->tested_at);

        $this->actingAs($this->admin)->get(route('admin.cloud-intake.routes.edit', $route))
            ->assertOk()
            ->assertSee('{customer_number} = K-4711', false)
            ->assertSee('Beispiel Bau GmbH');
    }

    public function test_fixed_target_reference_is_stored_as_alias_and_checked_against_the_organization(): void {
        $foreign = Customer::factory()->create(['organization_id' => Organization::factory()->create()->id]);

        $this->store([
            'path_pattern' => 'Rahmenvertraege/**',
            'test_path' => 'Rahmenvertraege/2026.pdf',
            'target_ref' => 'customer:' . Sqid::encode(Customer::class, (int) $foreign->id),
        ])->assertSessionHasErrors('target_ref');

        $this->store([
            'path_pattern' => 'Rahmenvertraege/**',
            'test_path' => 'Rahmenvertraege/2026.pdf',
            'target_ref' => 'customer:' . $this->customer->sqid,
        ])->assertSessionHasNoErrors();

        $route = CloudDocumentRoute::query()->sole();
        $this->assertSame(MorphMap::alias(Customer::class), $route->target_ref_type);
        $this->assertSame($this->customer->id, (int) $route->target_ref_id);
        $this->assertTrue($route->targetRef?->is($this->customer));

        $this->actingAs($this->admin)->get(route('admin.cloud-intake.routes.edit', $route))
            ->assertOk()
            ->assertSee('customer:' . $this->customer->sqid, false);
    }

    public function test_overlapping_active_routes_block_activation(): void {
        $this->store()->assertSessionHasNoErrors();

        // Gleiches Muster, aktiv: blockiert; inaktiv: erlaubt.
        $this->store(['priority' => 20])->assertSessionHasErrors('active');
        $this->store(['priority' => 20, 'active' => '0'])->assertSessionHasNoErrors();

        // Testpfad der neuen Regel trifft auch die bestehende.
        $this->store(['path_pattern' => '**', 'priority' => 30, 'test_path' => 'Kunden/K-4711/x.pdf'])
            ->assertSessionHasErrors(['active']);
        // Eine Endungsliste schützt nicht, solange die bestehende Regel jede Endung nimmt.
        $this->store(['path_pattern' => 'Kunden/**', 'priority' => 30, 'test_path' => 'Kunden/K-4711/x.xml', 'allowed_extensions' => 'xml', 'target' => CloudIntakeRouteTarget::IncomingInvoice->value])
            ->assertSessionHasErrors('active');
        $this->assertSame(2, CloudDocumentRoute::query()->count());
    }

    public function test_first_import_waits_for_a_confirmed_preview(): void {
        config(['plugins.dropbox.client_id' => 'key', 'plugins.dropbox.client_secret' => 'secret']);
        $this->store()->assertSessionHasNoErrors();
        $this->assertSame(CloudIntakeConnectionStatus::Draft, $this->connection->fresh()?->status);
        $this->assertFalse((bool) $this->connection->fresh()?->isRunnable());

        // Ohne Vorschau keine Bestätigung.
        $this->actingAs($this->admin)->post(route('admin.cloud-intake.confirm-start', $this->connection))
            ->assertSessionHas('error', __('cloud_intake.flash.preview_required'));
        $this->assertNull($this->connection->fresh()?->start_confirmed_at);

        FakePluginHttp::fake([
            'https://api.dropboxapi.com/2/files/list_folder' => FakePluginHttp::response([
                'entries' => [['.tag' => 'file', 'id' => 'id:1', 'name' => 'rahmen.pdf', 'path_display' => '/WorkDiary/Kunden/K-4711/rahmen.pdf', 'rev' => '01', 'size' => 1200, 'server_modified' => '2026-11-11T10:00:00Z']],
                'cursor' => 'c-1',
                'has_more' => false,
            ]),
        ]);
        $this->actingAs($this->admin)->post(route('admin.cloud-intake.preview', $this->connection))->assertSessionHas('success');
        $this->actingAs($this->admin)->get(route('admin.cloud-intake.index'))->assertOk()->assertSee(__('cloud_intake.start.action'));

        $this->actingAs($this->admin)->post(route('admin.cloud-intake.confirm-start', $this->connection))
            ->assertSessionHas('success', __('cloud_intake.flash.start_confirmed'));
        $fresh = $this->connection->fresh();
        $this->assertNotNull($fresh?->start_confirmed_at);
        $this->assertSame(CloudIntakeConnectionStatus::Active, $fresh?->status);
        $this->assertDatabaseHas('audit_logs', ['event' => 'cloudIntake.startConfirmed', 'auditable_id' => $this->connection->id]);

        // Ein neuer Stammordner ist ein neuer Erstlauf.
        $this->actingAs($this->admin)->post(route('admin.cloud-intake.folder', $this->connection), [
            'container_id' => 'root',
            'root_folder_path' => '/Archiv',
        ])->assertRedirect();
        $this->assertNull($this->connection->fresh()?->start_confirmed_at);
        $this->assertSame(CloudIntakeConnectionStatus::Draft, $this->connection->fresh()?->status);
    }
}
