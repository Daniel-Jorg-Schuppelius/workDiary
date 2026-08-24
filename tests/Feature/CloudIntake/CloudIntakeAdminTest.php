<?php
/*
 * Created on   : Tue Jul 14 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : CloudIntakeAdminTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace Tests\Feature\CloudIntake;

use App\Enums\CloudIntake\{CloudIntakeConnectionStatus, CloudIntakeProvider, CloudIntakeRouteTarget};
use App\Enums\User\Permission;
use App\Models\CloudIntake\{CloudDocumentConnection, CloudDocumentRoute};
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\WithOrganization;
use Tests\Support\FakePluginHttp;
use Tests\TestCase;

/**
 * Admin-UX des Cloud-Dokumenteingangs (Feature 080, MVP-358): Rechte,
 * Ordner-Auswahl inkl. Überlappungs-Preflight, Regel-CRUD mit
 * Muster-Validierung, Statuswechsel Draft⇄Active, Trennung.
 */
class CloudIntakeAdminTest extends TestCase {
    use RefreshDatabase;
    use WithOrganization;

    private User $admin;

    protected function setUp(): void {
        parent::setUp();
        $this->setUpOrganization();

        $this->admin = User::factory()->user()->create(['organization_id' => $this->organization->id]);
        $this->admin->givePermissionTo([
            Permission::CloudIntakeConnectionManage->value,
            Permission::CloudIntakeRouteManage->value,
            Permission::CloudIntakeRunPreview->value,
        ]);
    }

    private function connection(array $attributes = []): CloudDocumentConnection {
        return CloudDocumentConnection::factory()->create($attributes + [
            'organization_id' => $this->organization->id,
            'created_by_user_id' => $this->admin->id,
        ]);
    }

    public function test_index_requires_permission(): void {
        $stranger = User::factory()->user()->create(['organization_id' => $this->organization->id]);

        $this->actingAs($stranger)->get(route('admin.cloud-intake.index'))->assertForbidden();
        $this->actingAs($this->admin)->get(route('admin.cloud-intake.index'))->assertOk();
    }

    /**
     * Vollscan 2026-08-23, F1: Ohne datetime-Cast kam `last_error_at` als String
     * aus der DB und die Liste crashte mit „Call to a member function ftime()“,
     * sobald ein Fehler hinterlegt war.
     */
    public function test_index_renders_a_connection_with_a_recorded_error(): void {
        $connection = $this->connection();
        $connection->recordConnectionFailure('Token abgelaufen');

        $this->assertInstanceOf(\Carbon\CarbonInterface::class, $connection->fresh()?->last_error_at);

        $this->actingAs($this->admin)
            ->get(route('admin.cloud-intake.index'))
            ->assertOk()
            ->assertSee('Token abgelaufen');
    }

    public function test_index_loads_container_picker_options_on_demand(): void {
        config(['plugins.msgraph.client_id' => 'cid', 'plugins.msgraph.client_secret' => 'sec']);
        $connection = $this->connection([
            'provider' => CloudIntakeProvider::Microsoft,
            'external_account_id' => 'acc-1',
        ]);

        FakePluginHttp::fake([
            'https://graph.microsoft.com/v1.0/me/drives' => FakePluginHttp::response([
                'value' => [['id' => 'drive-7', 'name' => 'Picker-OneDrive', 'driveType' => 'business']],
            ]),
            'https://graph.microsoft.com/v1.0/sites?search=Projekt' => FakePluginHttp::response(['value' => []]),
        ]);

        $this->actingAs($this->admin)
            ->get(route('admin.cloud-intake.index', ['containers' => $connection->id, 'container_search' => 'Projekt']))
            ->assertOk()
            ->assertSee('Picker-OneDrive');

        // Ohne Picker-Parameter keine API-Aufrufe beim Seitenaufruf.
        $fresh = FakePluginHttp::fake();
        $this->actingAs($this->admin)->get(route('admin.cloud-intake.index'))->assertOk();
        $fresh->assertNothingSent();
    }

    public function test_index_shows_fallback_warning_when_container_load_fails(): void {
        config(['plugins.msgraph.client_id' => 'cid', 'plugins.msgraph.client_secret' => 'sec']);
        $connection = $this->connection([
            'provider' => CloudIntakeProvider::Microsoft,
            'external_account_id' => 'acc-1',
        ]);

        FakePluginHttp::fake([
            'https://graph.microsoft.com/v1.0/*' => FakePluginHttp::response(['error' => 'boom'], 500),
        ]);

        $this->actingAs($this->admin)
            ->get(route('admin.cloud-intake.index', ['containers' => $connection->id]))
            ->assertOk()
            ->assertSee(__('cloud_intake.picker.load_failed'));
    }

    public function test_select_folder_resets_checkpoint_and_blocks_overlap(): void {
        $connection = $this->connection(['checkpoint' => 'cp-alt', 'external_account_id' => 'acc-1']);

        $this->actingAs($this->admin)->post(route('admin.cloud-intake.folder', $connection), [
            'container_id' => 'personal',
            'root_folder_path' => '/WorkDiary/Eingang',
        ])->assertRedirect();

        $fresh = $connection->fresh();
        $this->assertNull($fresh->checkpoint);
        $this->assertSame('/WorkDiary/Eingang', $fresh->root_folder_path);

        // Überlappender Stammordner desselben Kontos wird blockiert.
        $second = $this->connection(['external_account_id' => 'acc-1']);
        $this->actingAs($this->admin)->from(route('admin.cloud-intake.index'))
            ->post(route('admin.cloud-intake.folder', $second), [
                'container_id' => 'personal',
                'root_folder_path' => '/WorkDiary/Eingang/Unterordner',
            ])->assertSessionHas('error');
        // Pfad bleibt unverändert (Factory-Default), Überlappung blockiert.
        $this->assertSame('/WorkDiary', $second->fresh()->root_folder_path);
    }

    public function test_route_crud_with_pattern_validation_and_status_refresh(): void {
        $connection = $this->connection([
            'external_account_id' => 'acc-1',
            'container_id' => 'personal',
            'root_folder_path' => '/WorkDiary',
        ]);

        // Unbekannte Variable blockiert das Speichern.
        $this->actingAs($this->admin)->from(route('admin.cloud-intake.index'))
            ->post(route('admin.cloud-intake.routes.store', $connection), [
                'path_pattern' => 'Kunden/{foo}/**',
                'priority' => 10,
                'target' => CloudIntakeRouteTarget::Document->value,
                'document_type' => 'other',
            ])->assertSessionHasErrors('path_pattern');

        // Gültige Regel aktiviert die Verbindung (Konto+Ordner+Regel).
        $this->actingAs($this->admin)->post(route('admin.cloud-intake.routes.store', $connection), [
            'path_pattern' => 'Kunden/{customer_number}/**',
            'priority' => 10,
            'target' => CloudIntakeRouteTarget::Document->value,
            'document_type' => 'contract',
            'allowed_extensions' => 'PDF, docx',
        ])->assertRedirect();

        $route = CloudDocumentRoute::query()->sole();
        $this->assertSame(['pdf', 'docx'], $route->allowed_extensions);
        $this->assertSame(CloudIntakeConnectionStatus::Active, $connection->fresh()->status);

        // Dialoge rendern (Modal-Konvention).
        $this->actingAs($this->admin)->get(route('admin.cloud-intake.routes.create', $connection))->assertOk();
        $this->actingAs($this->admin)->get(route('admin.cloud-intake.routes.edit', $route))->assertOk();

        // Letzte Regel löschen ⇒ zurück auf Entwurf.
        $this->actingAs($this->admin)->delete(route('admin.cloud-intake.routes.destroy', $route))->assertRedirect();
        $this->assertSame(CloudIntakeConnectionStatus::Draft, $connection->fresh()->status);
    }

    public function test_disconnect_keeps_evidence_and_requires_permission(): void {
        $connection = $this->connection();
        $item = \App\Models\CloudIntake\CloudDocumentItem::factory()->create([
            'organization_id' => $this->organization->id,
            'connection_id' => $connection->id,
        ]);

        $viewer = User::factory()->user()->create(['organization_id' => $this->organization->id]);
        $viewer->givePermissionTo([Permission::CloudIntakeRunPreview->value]);
        $this->actingAs($viewer)->delete(route('admin.cloud-intake.disconnect', $connection))->assertForbidden();

        $this->actingAs($this->admin)->delete(route('admin.cloud-intake.disconnect', $connection))->assertRedirect();
        $this->assertDatabaseMissing('cloud_document_connections', ['id' => $connection->id]);
        $this->assertDatabaseHas('cloud_document_items', ['id' => $item->id, 'connection_id' => null]);
    }
}
