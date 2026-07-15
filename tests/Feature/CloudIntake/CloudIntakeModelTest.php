<?php
/*
 * Created on   : Tue Jul 14 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : CloudIntakeModelTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace Tests\Feature\CloudIntake;

use App\Enums\CloudIntake\CloudIntakeConnectionStatus;
use App\Enums\User\Permission;
use App\Models\CloudIntake\{CloudDocumentConnection, CloudDocumentItem, CloudDocumentRoute};
use App\Models\{Organization, User};
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;
use Tests\Concerns\WithOrganization;
use Tests\TestCase;

/**
 * Datenmodell des Cloud-Dokumenteingangs (Feature 080, MVP-352): Org-Scope,
 * Idempotenz-Unique je Item-Revision, Token-Verschlüsselung/-Verbergen,
 * Nachweis überlebt Verbindungs-Trennung, Policies.
 */
class CloudIntakeModelTest extends TestCase {
    use RefreshDatabase;
    use WithOrganization;

    protected function setUp(): void {
        parent::setUp();
        $this->setUpOrganization();
    }

    public function test_item_revision_is_unique_per_org_and_connection(): void {
        $connection = CloudDocumentConnection::factory()->create(['organization_id' => $this->organization->id]);

        CloudDocumentItem::factory()->create([
            'organization_id' => $this->organization->id,
            'connection_id' => $connection->id,
            'external_item_id' => 'id:abc',
            'revision' => 'rev-1',
        ]);

        // Neue Revision desselben Items ist erlaubt …
        CloudDocumentItem::factory()->create([
            'organization_id' => $this->organization->id,
            'connection_id' => $connection->id,
            'external_item_id' => 'id:abc',
            'revision' => 'rev-2',
        ]);
        $this->assertSame(2, CloudDocumentItem::query()->count());

        // … dieselbe Revision nicht (Wiederholung nach Abbruch ⇒ duplicate).
        $this->expectException(QueryException::class);
        CloudDocumentItem::factory()->create([
            'organization_id' => $this->organization->id,
            'connection_id' => $connection->id,
            'external_item_id' => 'id:abc',
            'revision' => 'rev-1',
        ]);
    }

    public function test_tokens_are_encrypted_at_rest_and_hidden_in_serialization(): void {
        $connection = CloudDocumentConnection::factory()->create([
            'organization_id' => $this->organization->id,
            'access_token' => 'geheimes-token',
        ]);

        $raw = \Illuminate\Support\Facades\DB::table('cloud_document_connections')
            ->where('id', $connection->id)
            ->value('access_token');
        $this->assertNotSame('geheimes-token', $raw);
        $this->assertSame('geheimes-token', $connection->fresh()->access_token);

        $serialized = $connection->fresh()->toArray();
        $this->assertArrayNotHasKey('access_token', $serialized);
        $this->assertArrayNotHasKey('refresh_token', $serialized);
        $this->assertArrayNotHasKey('webhook_secret', $serialized);
    }

    public function test_connections_are_organization_scoped(): void {
        $mine = CloudDocumentConnection::factory()->create(['organization_id' => $this->organization->id]);
        $otherOrg = Organization::factory()->create();
        CloudDocumentConnection::factory()->create(['organization_id' => $otherOrg->id]);

        app()->instance('currentOrganization', $this->organization);

        $this->assertSame([$mine->id], CloudDocumentConnection::query()->pluck('id')->all());
    }

    public function test_disconnecting_keeps_items_as_evidence(): void {
        $connection = CloudDocumentConnection::factory()->create(['organization_id' => $this->organization->id]);
        $item = CloudDocumentItem::factory()->create([
            'organization_id' => $this->organization->id,
            'connection_id' => $connection->id,
        ]);

        $connection->delete();

        $this->assertDatabaseHas('cloud_document_items', ['id' => $item->id, 'connection_id' => null]);
    }

    public function test_is_runnable_requires_active_status_and_active_route(): void {
        $connection = CloudDocumentConnection::factory()->create([
            'organization_id' => $this->organization->id,
            'status' => CloudIntakeConnectionStatus::Active,
        ]);
        $this->assertFalse($connection->isRunnable()); // keine Route

        CloudDocumentRoute::factory()->create([
            'organization_id' => $this->organization->id,
            'connection_id' => $connection->id,
            'active' => true,
        ]);
        $this->assertTrue($connection->fresh()->isRunnable());

        $connection->update(['status' => CloudIntakeConnectionStatus::ReauthRequired]);
        $this->assertFalse($connection->fresh()->isRunnable());
    }

    public function test_policies_gate_connection_and_route_management(): void {
        $user = User::factory()->user()->create(['organization_id' => $this->organization->id]);
        $admin = User::factory()->user()->create(['organization_id' => $this->organization->id]);
        $admin->givePermissionTo([
            Permission::CloudIntakeConnectionManage->value,
            Permission::CloudIntakeRouteManage->value,
        ]);
        $preview = User::factory()->user()->create(['organization_id' => $this->organization->id]);
        $preview->givePermissionTo([Permission::CloudIntakeRunPreview->value]);

        $this->assertFalse(Gate::forUser($user)->allows('viewAny', CloudDocumentConnection::class));
        $this->assertTrue(Gate::forUser($preview->fresh())->allows('viewAny', CloudDocumentConnection::class));
        $this->assertFalse(Gate::forUser($preview->fresh())->allows('create', CloudDocumentConnection::class));
        $this->assertTrue(Gate::forUser($admin->fresh())->allows('create', CloudDocumentConnection::class));
        $this->assertTrue(Gate::forUser($admin->fresh())->allows('create', CloudDocumentRoute::class));
    }
}
