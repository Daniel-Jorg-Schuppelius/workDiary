<?php
/*
 * Created on   : Tue Jul 21 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : AssetMergeTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\Asset\AssetClass;
use App\Models\{Asset, Customer, Organization, RemotePendingSession};
use App\Plugins\RemoteSupport\Providers\AnyDeskClient;
use App\Plugins\RemoteSupport\RemoteSupportService;
use App\Services\AssetMergeService;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use InvalidArgumentException;
use Tests\Concerns\WithOrganization;
use Tests\TestCase;

class AssetMergeTest extends TestCase {
    use RefreshDatabase;
    use WithOrganization;

    protected function setUp(): void {
        parent::setUp();
        $this->setUpOrganization();
    }

    private function makeAsset(array $attributes = []): Asset {
        return Asset::factory()->create(array_merge([
            'organization_id' => $this->organization->id,
            'asset_class' => AssetClass::Device->value,
        ], $attributes));
    }

    public function test_merge_moves_references_fills_fields_and_deletes_source(): void {
        $customer = Customer::factory()->create(['organization_id' => $this->organization->id]);
        $source = $this->makeAsset(['customer_id' => $customer->id, 'manufacturer' => 'Dell', 'shared_remote' => true]);
        $target = $this->makeAsset(['manufacturer' => null]);

        $remote = new RemoteSupportService;
        $remote->setRemoteId($source, AnyDeskClient::ID, '111000111');
        $remote->setRemoteId($target, AnyDeskClient::ID, '222000222');

        RemotePendingSession::query()->create([
            'organization_id' => $this->organization->id,
            'asset_id' => $source->id,
            'provider' => AnyDeskClient::ID,
            'remote_id' => '111000111',
            'session_id' => 'ad-merge-1',
            'started_at' => CarbonImmutable::parse('2026-07-20 09:00:00'),
            'ended_at' => CarbonImmutable::parse('2026-07-20 09:30:00'),
            'status' => RemotePendingSession::STATUS_OPEN,
        ]);

        app(AssetMergeService::class)->merge($source, $target, []);

        $this->assertDatabaseMissing('assets', ['id' => $source->id]);

        // Primär-ID-Kollision → Quell-ID wird Alias des Ziels; beide IDs matchen weiter.
        $target->refresh();
        $ids = $remote->remoteIds($target, AnyDeskClient::ID);
        $this->assertContains('111000111', $ids);
        $this->assertContains('222000222', $ids);

        $this->assertDatabaseHas('remote_pending_sessions', [
            'session_id' => 'ad-merge-1',
            'asset_id' => $target->id,
        ]);

        // Leere Ziel-Felder aus der Quelle aufgefüllt; Konsistenz-Hooks greifen.
        $this->assertSame('Dell', $target->manufacturer);
        $this->assertSame($customer->id, (int) $target->customer_id);
        $this->assertSame(\App\Enums\Asset\AssetOwnership::Customer, $target->owned_by);
        $this->assertTrue($target->shared_remote);
    }

    public function test_merge_respects_field_overrides(): void {
        $source = $this->makeAsset(['manufacturer' => 'Lenovo']);
        $target = $this->makeAsset(['manufacturer' => 'HP']);

        app(AssetMergeService::class)->merge($source, $target, ['manufacturer' => 'Lenovo']);

        $this->assertSame('Lenovo', $target->refresh()->manufacturer);
    }

    public function test_merge_rejects_cross_organization(): void {
        $other = Organization::factory()->create();
        $source = $this->makeAsset();
        $target = Asset::factory()->create([
            'organization_id' => $other->id,
            'asset_class' => AssetClass::Device->value,
        ]);

        $this->expectException(InvalidArgumentException::class);
        app(AssetMergeService::class)->merge($source, $target);
    }

    public function test_asset_index_search_matches_customer_name(): void {
        $customer = Customer::factory()->create([
            'organization_id' => $this->organization->id,
            'name' => 'Kanzlei Meierhoff',
        ]);
        $this->makeAsset(['name' => 'PC-Empfang', 'customer_id' => $customer->id]);
        $this->makeAsset(['name' => 'PC-Lager']);

        $response = $this->actingAs($this->orgAdmin())->get(route('assets.index', ['q' => 'Meierhoff']));

        $response->assertOk()
            ->assertSee('PC-Empfang')
            ->assertDontSee('PC-Lager');
    }

    public function test_merge_endpoint_merges_and_redirects(): void {
        $source = $this->makeAsset(['name' => 'Duplikat']);
        $target = $this->makeAsset(['name' => 'Original']);

        $response = $this->actingAs($this->orgAdmin())->post(route('assets.merge'), [
            'source' => $source->sqid,
            'target' => $target->sqid,
        ]);

        $response->assertRedirect(route('assets.show', $target));
        $this->assertDatabaseMissing('assets', ['id' => $source->id]);
    }
}
